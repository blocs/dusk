<?php

namespace Blocs\Commands;

use Facebook\WebDriver\Exception\InvalidSessionIdException;
use Facebook\WebDriver\Exception\NoSuchWindowException;
use Illuminate\Console\Command;

class Dusk extends Command
{
    use DuskOpenAiTrait;
    use DuskTestTrait;

    protected $signature = 'blocs:dusk {script?}';
    protected $description = 'Support laravel dusk browser tests';
    private $browser;
    private $indent;

    public function handle()
    {
        $this->install();

        $script = $this->argument('script');
        if (empty($script)) {
            exit;
        }

        file_exists($script) || $script = base_path($script);
        if (!file_exists($script)) {
            $this->error('Script not found');

            return;
        }

        do {
            // Retrieve all functions from $script
            $originalScript = file_get_contents($script);

            $functions = [];
            preg_match_all('/\s*function\s*(.*?)\(/', $originalScript, $functions);

            // Choose action
            $actions = [];
            foreach ($functions[1] as $function) {
                // Add test functions
                if (0 === strpos($function, 'test')) {
                    $actions[] = $function.' update';
                    $actions[] = $function.' run';
                }
            }
            $actions[] = 'exit';
            $action = $this->anticipate('Action', $actions);

            // run
            if (' run' === substr($action, -4)) {
                $function = substr($action, 0, -4);
                \Artisan::call('dusk --browse '.$script.' --filter '.$function);
                continue;
            }

            // exit
            if ('exit' === strtolower($action) || 'bye' === strtolower($action)) {
                isset($this->browser) && $this->browser->quit();
                exit;
            }

            // update
            if (' update' !== substr($action, -7)) {
                continue;
            }

            // Target function
            $function = substr($action, 0, -7);

            $originalScript = file_get_contents($script);
            list($beforeFunction, $functionContent, $afterFunction) = $this->splitScript($originalScript, $function);

            // Retrieve comments
            $funstionContents = explode("\n", $functionContent);
            strlen(last($funstionContents)) || array_pop($funstionContents);

            $beforeComment = '';
            while ($funstionContents) {
                if (preg_match('/^\s*\/\/\s*/', $funstionContents[0])) {
                    break;
                }

                $beforeComment .= array_shift($funstionContents)."\n";
            }

            $afterComment = '';
            while ($funstionContents) {
                if (preg_match('/[^\s\}\)\;]/', last($funstionContents))) {
                    break;
                }

                $afterComment = array_pop($funstionContents)."\n".$afterComment;
            }

            $comments = [];
            foreach ($funstionContents as $funstionContent) {
                if (preg_match('/^\s*\/\/\s*/', $funstionContent)) {
                    $comments[] = [
                        'comment' => $funstionContent,
                        'script' => '',
                    ];
                    continue;
                }

                $comments[count($comments) - 1]['script'] .= $funstionContent."\n";
            }

            // Open browser
            $this->openBrowser();

            $updateFlag = true;
            foreach ($comments as $num => $comment) {
                $scriptContent = $comment['script'];
                while (1) {
                    list($this->indent, $operation) = explode('//', $comment['comment'], 2);
                    $operation = trim($operation);

                    $this->line(trim($comment['comment']));

                    if (empty(trim($scriptContent))) {
                        // generate
                        $scriptContent = $this->generateCode($operation);

                        if (false === strpos($scriptContent, '$browser')) {
                            $comments[$num]['script'] = $this->addIndent($scriptContent, '// ')."\n";
                            $comment['script'] = $comments[$num]['script'];
                        } else {
                            $comments[$num]['script'] = $this->addIndent($scriptContent)."\n";
                            $comment['script'] = $comments[$num]['script'];
                        }
                    }

                    if (false === strpos($scriptContent, '$browser')) {
                        // Additional question
                        $action = $this->anticipate(trim($scriptContent)."\n", [$operation, 'skip', 'stop']);
                    } else {
                        $action = $this->anticipate(trim($scriptContent)."\n", ['execute', $operation, 'update', 'skip', 'stop'], 'execute');
                    }

                    // execute
                    if ('execute' === strtolower($action)) {
                        if ($this->executeScript($scriptContent)) {
                            break;
                        }
                        continue;
                    }

                    // update
                    if ('update' === strtolower($action)) {
                        break 2;
                    }

                    // skip
                    if ('skip' === strtolower($action)) {
                        break;
                    }

                    // stop
                    if ('stop' === strtolower($action)) {
                        $updateFlag = false;
                        break 2;
                    }

                    // generate
                    $comments[$num]['comment'] = $this->addIndent($action, '// ');
                    $comment['comment'] = $comments[$num]['comment'];

                    $scriptContent = $this->generateCode($action);

                    if (false === strpos($scriptContent, '$browser')) {
                        $comments[$num]['script'] = $this->addIndent($scriptContent, '// ')."\n";
                        $comment['script'] = $comments[$num]['script'];
                    } else {
                        $comments[$num]['script'] = $this->addIndent($scriptContent)."\n";
                        $comment['script'] = $comments[$num]['script'];
                    }
                }
            }

            if ($updateFlag) {
                $this->line('Update script');

                $updatedScript = '';
                foreach ($comments as $comment) {
                    empty($comment['comment']) || $updatedScript .= $comment['comment']."\n";
                    empty($comment['script']) || $updatedScript .= $comment['script'];
                }

                $originalScript = $beforeFunction.$beforeComment.$updatedScript.$afterComment.$afterFunction;
                file_put_contents($script, $originalScript);
            }

            isset($this->browser) && $this->browser->quit();
        } while (1);
    }

    private function addIndent($scriptContent, $preset = '')
    {
        $result = '';
        foreach (explode("\n", $scriptContent) as $line) {
            $result .= $this->indent.$preset.$line."\n";
        }

        return $result;
    }

    private function executeScript($scriptContent)
    {
        $browser = $this->browser;

        try {
            eval($scriptContent);
        } catch (NoSuchWindowException $e) {
            $browser->quit();
            $this->error('Browser closed');
            exit;
        } catch (InvalidSessionIdException $e) {
            $browser->quit();
            $this->error('Browser closed');
            exit;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return false;
        }

        return true;
    }

    private function splitScript($scriptContent, $function)
    {
        $scriptContent = explode("\n", rtrim($scriptContent));
        $beforeFunction = '';
        $functionContent = '';
        $afterFunction = '';

        $flag = false;
        foreach ($scriptContent as $num => $line) {
            if ($flag && (preg_match('/\s+function\s+/', $line) || count($scriptContent) <= $num + 1)) {
                $flag = false;
            }
            if (preg_match('/\s+function\s+'.$function.'\(/', $line)) {
                $flag = true;
            }

            if (!$flag && empty($functionContent)) {
                $beforeFunction .= $line."\n";
            }
            if ($flag) {
                $functionContent .= $line."\n";
            }
            if (!$flag && !empty($functionContent)) {
                $afterFunction .= $line."\n";
            }
        }

        return [$beforeFunction, $functionContent, $afterFunction];
    }

    private function install()
    {
        // Publish
        file_exists(base_path('tests/Browser/blocs')) || \Artisan::call('vendor:publish', ['--provider' => 'Blocs\DuskServiceProvider']);

        // Install laravel/dusk
        if (!file_exists(base_path('tests/DuskTestCase.php'))) {
            $this->info('Please execute below command to install laravel/dusk');
            $this->line('php artisan dusk:install');
            exit;
        }

        // Install openai-php/laravel
        if (!file_exists(config_path('openai.php'))) {
            $this->info('Please execute below command to install openai-php/laravel');
            $this->line('php artisan openai:install');
            exit;
        }
    }
}
