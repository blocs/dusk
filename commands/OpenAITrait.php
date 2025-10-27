<?php

namespace Blocs\Commands;

use OpenAI\Laravel\Facades\OpenAI;

trait OpenAITrait
{
    private function guessCode($request, $additionalRequest, $currentCode, $commentNum, $comments)
    {
        try {
            $url = $this->browser->driver->getCurrentURL();
        } catch (NoSuchWindowException $e) {
            $this->error('Browser closed');
            exit;
        }

        $messages = [
            [
                'role' => 'developer',
                'content' => file_get_contents(base_path('tests/Browser/prompt/developer.md')),
            ],
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
                'content' => trim($comment['comment']),
            ];
            $messages[] = [
                'role' => 'assistant',
                'content' => trim($comment['script']),
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
        $userContent[] = [
            'type' => 'text',
            'text' => file_get_contents(base_path('tests/Browser/prompt/user.md')),
        ];
        empty(trim($currentCode)) || $userContent[] = [
            'type' => 'text',
            'text' => "# Current Code\n```php\n".$currentCode."\n```",
        ];
        empty($this->errorMessage) || $userContent[] = [
            'type' => 'text',
            'text' => "# Error\n".$this->errorMessage,
        ];
        if (strpos($url, 'http://') === 0 || strpos($url, 'https:') === 0) {
            $this->browser->storeSource('blocsDusk');
            $htmlContent = file_get_contents(base_path('tests/Browser/source/blocsDusk.txt'));
            $htmlContent = $this->minifyHtml($htmlContent);

            $userContent[] = [
                'type' => 'text',
                'text' => "# Current Page\n```html\n".$htmlContent."\n```",
            ];

            unlink(base_path('tests/Browser/source/blocsDusk.txt'));
        }

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
            $this->storeLog($chatOpenAI);

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
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML($htmlContent);

        // コメントを削除
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//comment()') as $commentNode) {
            if ($commentNode->parentNode) {
                $commentNode->parentNode->removeChild($commentNode);
            }
        }

        // 指定タグをまとめて削除
        $tagsToRemove = ['script', 'style', 'meta', 'pre', 'path', 'svg', 'noscript', 'iframe'];
        foreach ($tagsToRemove as $tagName) {
            while (true) {
                $nodes = $dom->getElementsByTagName($tagName);
                if ($nodes->length === 0) {
                    break;
                }
                $node = $nodes->item(0);
                if ($node && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                } else {
                    break;
                }
            }
        }

        // <link rel="stylesheet"> や CSS用のlink要素を削除
        $linkNodes = $dom->getElementsByTagName('link');
        $nodesToRemove = [];
        foreach ($linkNodes as $link) {
            $rel = $link->getAttribute('rel');
            $as = $link->getAttribute('as');
            $type = $link->getAttribute('type');
            $isStylesheetRel = preg_match('/(^|\s)stylesheet(\s|$)/i', $rel) === 1;
            $isPreloadStyle = preg_match('/(^|\s)preload(\s|$)/i', $rel) === 1 && strcasecmp($as, 'style') === 0;
            $isCssType = stripos($type, 'css') !== false;
            if ($isStylesheetRel || $isPreloadStyle || $isCssType) {
                $nodesToRemove[] = $link;
            }
        }
        foreach ($nodesToRemove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        // on*属性（イベントハンドラ）を削除し、style属性・aria*も削除、javascript: URLを無効化
        $allElements = $dom->getElementsByTagName('*');
        foreach ($allElements as $element) {
            if (! $element->hasAttributes()) {
                continue;
            }
            $attributeNames = [];
            foreach ($element->attributes as $attribute) {
                $attributeNames[] = $attribute->name;
            }
            foreach ($attributeNames as $attributeName) {
                $lowerName = strtolower($attributeName);
                // style属性の削除
                if ($lowerName === 'style') {
                    $element->removeAttribute($attributeName);

                    continue;
                }
                // aria- の属性を削除
                if (strpos($lowerName, 'aria-') === 0) {
                    $element->removeAttribute($attributeName);

                    continue;
                }
                // on* イベント属性の削除
                if (stripos($attributeName, 'on') === 0) {
                    $element->removeAttribute($attributeName);

                    continue;
                }
                // javascript: を無効化
                if ($lowerName === 'href' || $lowerName === 'src' || $lowerName === 'xlink:href') {
                    $value = $element->getAttribute($attributeName);
                    if (preg_match('/^\s*javascript\s*:/i', $value) === 1) {
                        $element->setAttribute($attributeName, '#');
                    }
                }
            }
        }

        $sanitizedHtml = $dom->saveHTML();

        // Remove spaces
        $sanitizedHtml = str_replace(["\r\n", "\r", "\n"], ' ', $sanitizedHtml);
        $sanitizedHtml = preg_replace('/\s+/', ' ', $sanitizedHtml);

        // 30文字以上連続する日本語（ひらがな/カタカナ/漢字/半角カナ/記号）を削除
        $sanitizedHtml = html_entity_decode($sanitizedHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sanitizedHtml = preg_replace('/[\x{3040}-\x{30FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}\x{FF66}-\x{FF9F}\x{3000}-\x{303F}]{30,}/u', '', $sanitizedHtml);
        $sanitizedHtml = preg_replace('/[\x21-\x7E]{100,}/u', '', $sanitizedHtml);

        return $sanitizedHtml;
    }

    private function storeLog($chatOpenAI)
    {
        file_put_contents(storage_path('framework/cache/chatOpenAI.log'), json_encode($chatOpenAI, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
    }
}
