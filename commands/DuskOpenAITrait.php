<?php

namespace Blocs\Commands;

use OpenAI\Laravel\Facades\OpenAI;

trait DuskOpenAITrait
{
    private function guessCode($request, $additionalRequest)
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
            $this->browser->storeSource('blocs');
            $htmlContent = file_get_contents(base_path('tests/Browser/source/blocs.txt'));
            // $htmlContent = $this->minifyHtml($htmlContent);

            $assistantContent[] = [
                'type' => 'text',
                'text' => "# 現在表示されているページの HTML\n```html\n".$htmlContent."\n```",
            ];

            unlink(base_path('tests/Browser/source/blocs.txt'));
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
        empty(trim($this->currentScript)) || $userContent[] = [
            'type' => 'text',
            'text' => "# Current Code\n```php\n".$this->currentScript."\n```",
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
                $model = empty($additionalRequest) ? 'gpt-5-mini' : 'gpt-5';
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

        // Remove attributes
        preg_match_all('/\s*([a-zA-Z\-]+)\s*=\s*("|\').*?\2/', $htmlContent, $matchs);
        $attributes = array_unique($matchs[1]);

        foreach ($attributes as $attribute) {
            if (in_array($attribute, ['href', 'id', 'class', 'name'])) {
                continue;
            }

            $htmlContent = preg_replace('/\s*'.$attribute.'\s*=\s*("|\').*?\1/', '', $htmlContent);
        }

        // Remove multibite characters
        $htmlContent = preg_replace('/[^\x20-\x7E]{10,}/u', '', $htmlContent);

        return $htmlContent;
    }
}
