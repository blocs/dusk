<?php

namespace Blocs\Commands;

use OpenAI\Laravel\Facades\OpenAI;

trait DuskOpenAITrait
{
    private function generateCode($request, $additionalRequest)
    {
        $messageContent = [
            [
                'type' => 'text',
                'text' => file_get_contents(base_path('tests/Browser/blocs/user.md'))."\n\n",
            ],
            [
                'type' => 'text',
                'text' => "# Request\n".$request."\n\n",
            ],
        ];
        if (!empty($additionalRequest)) {
            $messageContent[] = [
                'type' => 'text',
                'text' => "# Additional request\n".$additionalRequest."\n\n",
            ];
        }
        if (!empty(trim($this->currentScript))) {
            $messageContent[] = [
                'type' => 'text',
                'text' => "# Current code\n```php\n".$this->currentScript."\n```\n\n",
            ];
        }
        if (!empty($this->errorMessage)) {
            $messageContent[] = [
                'type' => 'text',
                'text' => "# Error\n".$this->errorMessage."\n\n",
            ];
        }

        $messageContent[] = [
            'type' => 'text',
            'text' => file_get_contents(base_path('tests/Browser/blocs/sample.md'))."\n\n",
        ];

        try {
            $url = $this->browser->driver->getCurrentURL();
        } catch (NoSuchWindowException $e) {
            $this->error('Browser closed');
            exit;
        }

        if (0 === strpos($url, 'http://') || 0 === strpos($url, 'https:')) {
            $this->browser->storeSource('blocs');
            $htmlContent = file_get_contents(base_path('tests/Browser/source/blocs.txt'));
            $htmlContent = $this->minifyHtml($htmlContent);

            $messageContent[] = [
                'type' => 'text',
                'text' => "# HTML\n```html\n".$htmlContent."\n```\n\n",
            ];

            unlink(base_path('tests/Browser/source/blocs.txt'));
        }

        try {
            if (empty(config('openai.model'))) {
                if (empty($additionalRequest)) {
                    $model = 'gpt-5-mini';
                } else {
                    $model = 'gpt-5';
                }
            } else {
                $model = config('openai.model');
            }

            $result = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => file_get_contents(base_path('tests/Browser/blocs/system.md')),
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => $messageContent,
                    ],
                    [
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => file_get_contents(base_path('tests/Browser/blocs/assistant.md')),
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return false;
        }

        // Get updated function
        $scriptContent = $result->choices[0]->message->content;
        $result = strpos($scriptContent, '```php');

        $scriptContent = str_replace('```php', '', $scriptContent);
        $scriptContent = str_replace('```', '', $scriptContent);
        $scriptContent = trim($scriptContent);

        return [$result, $scriptContent];
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
