<?php

namespace Blocs\Commands;

use OpenAI\Laravel\Facades\OpenAI;

trait DuskOpenAITrait
{
    private function guessCode($request, $additionalRequest, $currentCode, $commentNum, $comments)
    {
        try {
            $url = $this->browser->driver->getCurrentURL();
        } catch (NoSuchWindowException $e) {
            $this->error('Browser closed');
            exit;
        }

        $messages = [];
        $messages[] = [
            'role' => 'developer',
            'content' => file_get_contents(base_path('tests/Browser/prompt/developer.md')),
        ];

        foreach ($comments as $num => $comment) {
            if ($num >= $commentNum) {
                break;
            }
            if (empty($comment['comment']) || empty($comment['script'])) {
                continue;
            }

            $messages[] = [
                'role' => 'user',
                'content' => $comment['comment'],
            ];
            $messages[] = [
                'role' => 'assistant',
                'content' => $comment['script'],
            ];
        }

        $userContent = [];
        $userContent[] = [
            'type' => 'text',
            'text' => "# Request\n".$request,
        ];
        empty($additionalRequest) || $userContent[] = [
            'type' => 'text',
            'text' => "# Additional Request\n".$additionalRequest,
        ];
        if (0 === strpos($url, 'http://') || 0 === strpos($url, 'https:')) {
            $this->browser->storeSource('blocsDusk');
            $htmlContent = file_get_contents(base_path('tests/Browser/source/blocsDusk.txt'));
            $htmlContent = $this->minifyHtml($htmlContent);

            $userContent[] = [
                'type' => 'text',
                'text' => "# Current Page\n```html\n".$htmlContent."\n```",
            ];

            unlink(base_path('tests/Browser/source/blocsDusk.txt'));
        }
        empty(trim($currentCode)) || $userContent[] = [
            'type' => 'text',
            'text' => "# Current Code\n```php\n".$currentCode."\n```",
        ];
        empty($this->errorMessage) || $userContent[] = [
            'type' => 'text',
            'text' => "# Error\n".$this->errorMessage,
        ];
        $userContent[] = [
            'type' => 'text',
            'text' => file_get_contents(base_path('tests/Browser/prompt/user.md')),
        ];

        $messages[] = [
            'role' => 'user',
            'content' => $userContent,
        ];

        try {
            if (empty(config('openai.model'))) {
                $model = empty($currentCode) ? 'gpt-5-chat-latest' : 'gpt-5';
            } else {
                $model = config('openai.model');
            }

            $chatOpenAI = [
                'model' => $model,
                'messages' => $messages,
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

    private function minifyHtml($htmlContent)
    {
        // Remove spaces
        $htmlContent = str_replace(["\r\n", "\r", "\n"], ' ', $htmlContent);
        $htmlContent = preg_replace('/\s+/', ' ', $htmlContent);

        // Remove comment tags
        $htmlContent = preg_replace('/<!--.*?-->/', '', $htmlContent);

        // Remove tags
        foreach (['head', 'script', 'style', 'pre', 'path', 'svg'] as $tag) {
            $htmlList = preg_split('/<\s*'.$tag.'/i', $htmlContent);
            $htmlContent = array_shift($htmlList);
            foreach ($htmlList as $html) {
                $html = preg_split('/<\s*\/\s*'.$tag.'\s*>/i', $html, 2);
                if (count($html) > 1) {
                    $htmlContent .= $html[1];
                } else {
                    $htmlContent .= $html[0];
                }
            }
        }

        // Remove multibite characters
        $htmlContent = preg_replace('/[^\x20-\x7E]{20,}/u', '', $htmlContent);

        return $htmlContent;
    }
}
