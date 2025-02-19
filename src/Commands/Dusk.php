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
    private $errorMessage;
    private $currentScript;

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
                // Add functions
                $function = trim($function);
                empty($function) || $actions[] = $function.' edit';
            }
            $actions[] = 'exit';
            $action = $this->anticipate('Command', $actions);

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

            // edit
            if (' edit' !== substr($action, -5)) {
                continue;
            }

            // Target function
            $function = substr($action, 0, -5);

            // Get comment
            list($buff, $buff, $comments) = $this->getComment($script, $function);
            if (!count($comments)) {
                continue;
            }

            // Open browser
            $this->openBrowser();

            $commentNum = 0;
            while (1) {
                // Get comment
                list($buff, $buff, $comments) = $this->getComment($script, $function);

                if (count($comments) > $commentNum) {
                    $comment = $comments[$commentNum];
                } else {
                    if (!config('openai.api_key')) {
                        break;
                    }

                    // Add step
                    $action = $this->anticipate('Add step', ['stop']);

                    // stop
                    if ('stop' === strtolower($action)) {
                        break;
                    }

                    $comment = [
                        'comment' => $this->addIndent($action, '// '),
                        'script' => '',
                    ];
                }
                $additionalRequest = '';

                $this->currentScript = $comment['script'];

                while (1) {
                    list($this->indent, $request) = explode('//', $comment['comment'], 2);
                    $request = trim($request);

                    $this->line(trim($comment['comment']));

                    if (!empty(trim($comment['script']))) {
                        $action = 'execute';
                    } else {
                        // generate
                        list($result, $comment['script']) = $this->generateCode($request, $additionalRequest);
                        $this->currentScript = $comment['script'];

                        if (false === $result) {
                            $comment['script'] = $this->addIndent($comment['script'], '// ')."\n";
                            $action = $this->anticipate(trim($comment['script'])."\n", ['skip', 'stop'], 'skip');
                        } else {
                            $comment['script'] = $this->addIndent($comment['script'])."\n";
                            $action = $this->anticipate(trim($comment['script'])."\n", ['execute', 'skip', 'stop'], 'execute');
                        }
                    }

                    // execute
                    if ('execute' === strtolower($action)) {
                        $browser = $this->browser;
                        $this->errorMessage = '';

                        try {
                            eval($comment['script']);

                            $this->updateScript($script, $function, $commentNum, $comment);
                            break;
                        } catch (NoSuchWindowException $e) {
                            $browser->quit();
                            $this->error('Browser closed');
                            exit;
                        } catch (InvalidSessionIdException $e) {
                            $browser->quit();
                            $this->error('Browser closed');
                            exit;
                        } catch (\BadMethodCallException $e) {
                            $this->info('skip');
                            break;
                        } catch (\Throwable $e) {
                            // Error happend
                            $this->error($e->getMessage());
                            $this->errorMessage = $e->getMessage();

                            $action = $this->anticipate(trim($comment['script'])."\n", ['retry', 'skip', 'stop']);

                            // retry
                            if ('retry' === strtolower($action)) {
                                continue;
                            }
                        }
                    }

                    // skip
                    if ('skip' === strtolower($action)) {
                        break;
                    }

                    // stop
                    if ('stop' === strtolower($action)) {
                        break 2;
                    }

                    // Additional request
                    empty($action) || $additionalRequest = $action;
                    $comment['script'] = '';
                }
                ++$commentNum;
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

    private function getComment($script, $function)
    {
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

        if (count($comments)) {
            empty($comments[count($comments) - 1]['script']) || $comments[count($comments) - 1]['script'] .= "\n";
        }

        return [$beforeFunction, $beforeComment, $comments, $afterComment, $afterFunction];
    }

    private function updateScript($script, $function, $commentNum, $comment)
    {
        list($beforeFunction, $beforeComment, $comments, $afterComment, $afterFunction) = $this->getComment($script, $function);
        $comments[$commentNum] = $comment;

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
