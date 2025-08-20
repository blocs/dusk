<?php

namespace Blocs\Commands;

use OpenAI\Laravel\Facades\OpenAI;

trait DuskOpenAITrait
{
    private function guessCode($request, $additionalRequest, $currentScript)
    {
        try {
            $url = $this->browser->driver->getCurrentURL();
        } catch (NoSuchWindowException $e) {
            $this->error('Browser closed');
            exit;
        }

        $systemContent = [];
        $systemContent[] = [
            'type' => 'text',
            'text' => file_get_contents(base_path('tests/Browser/blocs/system.md')),
        ];

        $assistantContent = [];
        $assistantContent[] = [
            'type' => 'text',
            'text' => file_get_contents(base_path('tests/Browser/blocs/assistant.md')),
        ];

        if (0 === strpos($url, 'http://') || 0 === strpos($url, 'https:')) {
            $this->browser->storeSource('blocsDusk');
            $htmlContent = file_get_contents(base_path('tests/Browser/source/blocsDusk.txt'));

            $assistantContent[] = [
                'type' => 'text',
                'text' => "# 現在表示されているページの HTML\n```html\n".$htmlContent."\n```",
            ];

            unlink(base_path('tests/Browser/source/blocsDusk.txt'));
        }

        $userContent = [];
        $userContent[] = [
            'type' => 'text',
            'text' => file_get_contents(base_path('tests/Browser/blocs/user.md')),
        ];
        $userContent[] = [
            'type' => 'text',
            'text' => "# Request\n".$request,
        ];
        empty($additionalRequest) || $userContent[] = [
            'type' => 'text',
            'text' => "# Additional Request\n".$additionalRequest,
        ];
        empty(trim($currentScript)) || $userContent[] = [
            'type' => 'text',
            'text' => "# Current Code\n```php\n".$currentScript."\n```",
        ];
        empty($this->errorMessage) || $userContent[] = [
            'type' => 'text',
            'text' => "# Error\n".$this->errorMessage,
        ];

        $message = [
            [
                'role' => 'system',
                'content' => $systemContent,
            ],
            [
                'role' => 'assistant',
                'content' => $assistantContent,
            ],
            [
                'role' => 'user',
                'content' => $userContent,
            ],
        ];

        try {
            if (empty(config('openai.model'))) {
                $model = empty($currentScript) ? 'gpt-5-mini' : 'gpt-5';
            } else {
                $model = config('openai.model');
            }

            $chatOpenAI = [
                'model' => $model,
                'messages' => $message,
            ];

            $result = OpenAI::chat()->create($chatOpenAI);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return false;
        }

        // Get updated function
        $scriptContent = $result->choices[0]->message->content;

        $scriptContent = str_replace('```php', '', $scriptContent);
        $scriptContent = str_replace('```', '', $scriptContent);
        $scriptContent = trim($scriptContent);

        return $scriptContent;
    }
}
