<?php

namespace Blocs\Commands;

use OpenAI\Laravel\Facades\OpenAI;

trait DuskOpenAiTrait
{
    private function generateCode($action)
    {
        $messageContent = [
            [
                'type' => 'text',
                'text' => file_get_contents(base_path('tests/Browser/blocs/user.md'))."\n\n",
            ],
            [
                'type' => 'text',
                'text' => file_get_contents(base_path('tests/Browser/blocs/sample.md'))."\n\n",
            ],
            [
                'type' => 'text',
                'text' => "# Action\n".$action."\n\n",
            ],
        ];
        if (!empty($this->error)) {
            $messageContent[] = [
                [
                    'type' => 'text',
                    'text' => "# Error\n".$this->error."\n\n",
                ],
            ];
        }

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
            $result = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
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
            $htmlContent = preg_replace('/<'.$tag.'\b[^<]*(?:(?!<\/'.$tag.'>)<[^<]*)*<\/'.$tag.'>/i', '', $htmlContent);
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
