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
    private $error;

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

        while (1) {
            // Retrieve all functions from $script
            $originalScript = file_get_contents($script);

            $functions = [];
            preg_match_all('/\s*function\s*(.*?)\(/', $originalScript, $functions);

            // Choose action
            $actions = ['run'];
            foreach ($functions[1] as $function) {
                // Add test functions
                if (0 === strpos($function, 'test')) {
                    $actions[] = $function.' update';
                }
            }
            $actions[] = 'exit';
            $action = $this->anticipate('Action', $actions);

            // run
            if ('run' === strtolower($action)) {
                \Artisan::call('dusk --browse '.$script);
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
                        'comment' => $funstionContent."\n",
                        'script' => '',
                    ];
                    continue;
                }

                $comments[count($comments) - 1]['script'] .= $funstionContent."\n";
            }

            if (!count($comments)) {
                continue;
            }
            empty($comments[count($comments) - 1]['script']) || $comments[count($comments) - 1]['script'] .= "\n";

            // Open browser
            $this->openBrowser();

            $num = 0;
            while (1) {
                if (count($comments) <= $num) {
                    if (!config('openai.api_key')) {
                        break;
                    }

                    // Add comment
                    $action = $this->anticipate('Comment', ['stop']);

                    // stop
                    if ('stop' === strtolower($action)) {
                        break;
                    }

                    $comments[] = [
                        'comment' => $this->addIndent($action, '// '),
                        'script' => '',
                    ];
                }
                $comment = $comments[$num];

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
                        if (config('openai.api_key')) {
                            $action = $this->anticipate(trim($scriptContent)."\n", [$operation, 'skip', 'stop']);
                        } else {
                            $action = $this->anticipate(trim($scriptContent)."\n", ['skip', 'stop']);
                        }
                    } else {
                        if (config('openai.api_key')) {
                            $action = $this->anticipate(trim($scriptContent)."\n", ['execute', $operation, 'skip', 'stop'], 'execute');
                        } else {
                            $action = $this->anticipate(trim($scriptContent)."\n", ['execute', 'skip', 'stop'], 'execute');
                        }
                    }

                    // execute
                    if ('execute' === strtolower($action)) {
                        if ($this->executeScript($scriptContent)) {
                            $this->updateScript($script, $beforeFunction, $beforeComment, $comments, $afterComment, $afterFunction);
                            break;
                        }
                        continue;
                    }

                    // skip
                    if ('skip' === strtolower($action)) {
                        break;
                    }

                    // stop
                    if ('stop' === strtolower($action)) {
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
                ++$num;
            }

            isset($this->browser) && $this->browser->quit();
        }
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
        $this->error = '';

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
            $this->error = $e->getMessage();

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

    private function updateScript($script, $beforeFunction, $beforeComment, $comments, $afterComment, $afterFunction)
    {
        $updatedScript = '';
        foreach ($comments as $comment) {
            empty($comment['comment']) || $updatedScript .= $comment['comment'];
            empty($comment['script']) || $updatedScript .= $comment['script'];
        }
        $updatedScript = preg_replace('/\n{2,}$/', "\n", $updatedScript);

        $originalScript = $beforeFunction.$beforeComment.$updatedScript.$afterComment.$afterFunction;
        file_put_contents($script, $originalScript);
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
