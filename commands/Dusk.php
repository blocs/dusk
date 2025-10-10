<?php

namespace Blocs\Commands;

use Facebook\WebDriver\Exception\InvalidSessionIdException;
use Facebook\WebDriver\Exception\NoSuchWindowException;
use Illuminate\Console\Command;

class Dusk extends Command
{
    use DuskTestTrait;
    use OpenAITrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blocs:dusk {script?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Support laravel dusk browser tests';

    private $browser;
    private $indent;
    private $errorMessage;
    private $scripts = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->install();

        file_exists(storage_path('framework/cache/chatOpenAI.log')) && unlink(storage_path('framework/cache/chatOpenAI.log'));

        $script = $this->argument('script');
        if (file_exists($script)) {
            $this->scripts[] = $script;
        }

        while (1) {
            // Retrieve all functions from $script
            if (file_exists($script)) {
                $originalScript = file_get_contents($script);
            } else {
                $originalScript = '';
            }

            $functions = [];
            preg_match_all('/private\s+function\s+(.*?)\(/', $originalScript, $functions);

            // Choose action
            $actions = [];
            foreach ($functions[1] as $function) {
                // Add functions
                $function = trim($function);
                empty($function) || $actions[] = $function;
            }
            foreach ($this->scripts as $targetScript) {
                // Add scripts
                $targetScript = trim($targetScript);
                empty($targetScript) || $targetScript == $script || $actions[] = $targetScript;
            }
            $actions[] = 'quit';
            $action = $this->anticipate('Function', $actions);

            // quit
            if ('quit' === strtolower($action) || 'exit' === strtolower($action) || 'bye' === strtolower($action)) {
                isset($this->browser) && $this->browser->quit();
                exit;
            }

            // Switch script
            if (file_exists(str_replace("'", '', $action))) {
                $script = str_replace("'", '', $action);
                $this->scripts[] = $script;
                continue;
            }

            if (empty($action)) {
                continue;
            }

            // Target function
            $function = $action;

            // Get comment
            list($buff, $buff, $comments) = $this->getComment($script, $function);
            if (!count($comments)) {
                continue;
            }

            // ブラウザを開く
            if (empty($this->browser)) {
                $this->openBrowser();
            } else {
                try {
                    // ブラウザの状態を確認
                    $this->browser->driver->getCurrentURL();
                } catch (NoSuchWindowException $e) {
                    $this->openBrowser();
                }
            }

            $commentNum = 0;
            while (count($comments) > $commentNum) {
                // Get comment
                list($buff, $buff, $comments) = $this->getComment($script, $function);
                $comment = $comments[$commentNum];

                if (empty($comment['comment'])) {
                    $this->infoScript($comment['script']);
                    eval($comment['script']);

                    ++$commentNum;
                    continue;
                }

                $additionalRequest = '';
                while (1) {
                    list($this->indent, $request) = explode('//', $comment['comment'], 2);
                    $request = trim($request);

                    $this->line(trim($comment['comment']));
                    empty(trim($comment['script'])) || $this->infoScript($comment['script']);

                    if ($this->confirm('Generate Code ?', empty(trim($comment['script'])))) {
                        // コード生成
                        $newCode = $this->guessCode($request, $additionalRequest, $comment['script'], $commentNum, $comments);
                        if (false === $newCode) {
                            $this->error('Can not generate code');
                            break;
                        }

                        $comment['script'] = $this->addIndent($newCode)."\n";
                        $this->infoScript($comment['script']);
                    }

                    // skip
                    if (empty(trim($comment['script']))) {
                        $action = $this->anticipate('Action', ['skip', 'quit'], 'skip');

                        // quit
                        if ('quit' === strtolower($action)) {
                            // 終了
                            break 2;
                        }

                        break;
                    }

                    $action = $this->anticipate('Action', ['execute', 'update', 'skip', 'quit'], 'execute');

                    // execute
                    if ('execute' === strtolower($action)) {
                        // 実行
                        $browser = $this->browser;
                        $this->errorMessage = '';

                        try {
                            eval($comment['script']);

                            $this->updateScript($script, $function, $commentNum, $comment);
                            break;
                        } catch (NoSuchWindowException $e) {
                            $this->error('Browser closed');
                            exit;
                        } catch (InvalidSessionIdException $e) {
                            $this->error('Browser closed');
                            exit;
                        } catch (\Throwable $e) {
                            // Error happend
                            $this->error($e->getMessage());
                            $this->newLine();

                            $this->errorMessage = $e->getMessage();
                            continue;
                        }
                    }

                    // update
                    if ('update' === strtolower($action)) {
                        // 実行せずに更新
                        $this->updateScript($script, $function, $commentNum, $comment);
                        break;
                    }

                    // skip
                    if ('skip' === strtolower($action)) {
                        // 実行も更新もなし
                        break;
                    }

                    // quit
                    if ('quit' === strtolower($action) || 'exit' === strtolower($action) || 'bye' === strtolower($action)) {
                        // 終了
                        break 2;
                    }

                    // Additional request
                    empty($action) || $additionalRequest = $action;
                    $comment['script'] = '';
                }
                ++$commentNum;
            }
        }
    }

    private function addIndent($scriptContent)
    {
        $result = '';
        foreach (explode("\n", $scriptContent) as $line) {
            $result .= $this->indent.$line."\n";
        }

        return $result;
    }

    private function infoScript($scriptContent)
    {
        $result = '';
        foreach (explode("\n", $scriptContent) as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $result .= substr($line, strlen($this->indent))."\n";
        }

        $this->info($result);
    }

    private function splitScript($scriptContent, $function)
    {
        $scriptContent = explode("\n", rtrim($scriptContent));
        $beforeFunction = '';
        $functionContents = [];
        $afterFunction = '';

        $flag = false;
        foreach ($scriptContent as $num => $line) {
            if ($flag && (preg_match('/private\s+function\s+/', $line) || count($scriptContent) <= $num + 1)) {
                $flag = false;
            }
            if (preg_match('/private\s+function\s+'.str_replace('/', '', $function).'\(/', $line)) {
                $flag = true;
            }

            if (!$flag && empty($functionContents)) {
                $beforeFunction .= $line."\n";
            }
            if ($flag) {
                $functionContents[] = $line."\n";
            }
            if (!$flag && !empty($functionContents)) {
                $afterFunction .= $line."\n";
            }
        }

        while ($functionContents && false === strpos($functionContents[count($functionContents) - 1], '}')) {
            $afterFunction = array_pop($functionContents).$afterFunction;
        }
        $functionContent = implode('', $functionContents);

        return [$beforeFunction, $functionContent, $afterFunction];
    }

    private function getComment($script, $function)
    {
        $originalScript = file_get_contents($script);
        list($beforeFunction, $functionContent, $afterFunction) = $this->splitScript($originalScript, $function);

        // Retrieve comments
        $funstionContents = explode("\n", $functionContent);
        strlen(last($funstionContents)) || array_pop($funstionContents);

        $comments = [];
        $scriptFlag = false;

        $beforeComment = '';
        while ($funstionContents) {
            list($scriptFlag, $comments) = $this->checkScriptContent($scriptFlag, $comments, $funstionContents[0]);

            if (preg_match('/^\s*\/\/\s*/', $funstionContents[0])) {
                break;
            }

            $beforeComment .= array_shift($funstionContents)."\n";
        }

        $num = 0;
        $numMax = substr_count($beforeComment, '{');
        $afterComment = '';
        while ($funstionContents) {
            if (preg_match('/[^\s\}]/', last($funstionContents)) || $num >= $numMax) {
                break;
            }

            $afterComment = array_pop($funstionContents)."\n".$afterComment;
            ++$num;
        }

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

    private function checkScriptContent($scriptFlag, $comments, $funstionContent)
    {
        if (preg_match('/^\s*\/\*/', $funstionContent)) {
            $scriptFlag = true;
        } elseif (preg_match('/\*\/\s*$/', $funstionContent)) {
            $scriptFlag = false;
        } elseif ($scriptFlag) {
            $comments[] = [
                'script' => $funstionContent,
            ];
        }

        return [$scriptFlag, $comments];
    }

    private function updateScript($script, $function, $commentNum, $comment)
    {
        list($beforeFunction, $beforeComment, $comments, $afterComment, $afterFunction) = $this->getComment($script, $function);
        $comments[$commentNum] = $comment;

        $updatedScript = '';
        foreach ($comments as $comment) {
            if (empty($comment['comment'])) {
                continue;
            }

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
        file_exists(base_path('tests/Browser/prompt')) || \Artisan::call('vendor:publish', ['--provider' => 'Blocs\DuskServiceProvider']);

        // Install laravel/dusk
        if (!file_exists(base_path('tests/DuskTestCase.php'))) {
            $this->error('Please execute below command to install laravel/dusk');
            $this->line('php artisan dusk:install');
            exit;
        }

        // Install openai-php/laravel
        if (!file_exists(config_path('openai.php'))) {
            $this->error('Please execute below command to install openai-php/laravel');
            $this->line('php artisan openai:install');
            exit;
        }
    }
}
