<?php

namespace Blocs\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class Dusk extends Command
{
    protected $signature = 'blocs:dusk {trait} {manual}';
    protected $description = 'Develop laravel dusk browser tests';

    public function handle()
    {
        $trait = $this->argument('trait');
        if (!file_exists($trait)) {
            return;
        }

        $manual = $this->argument('manual');
        if (!file_exists($manual)) {
            return;
        }

        $this->updateTrait($trait, $manual);
    }

    private function updateTrait($trait, $manual)
    {
        $messageContent = [
            [
                'type' => 'text',
                'text' => file_get_contents(base_path('tests/Browser/role/user.md'))."\n",
            ],
            [
                'type' => 'text',
                'text' => "# 操作メソッド\n".file_get_contents($manual.'/method.md')."\n",
            ],
            [
                'type' => 'text',
                'text' => "# 入力トレイト\n".file_get_contents($trait)."\n",
            ],
            [
                'type' => 'text',
                'text' => file_get_contents(base_path('tests/Browser/role/sample.md'))."\n",
            ],
        ];

        if (file_exists($manual.'/source.html')) {
            $messageContent[] = [
                'type' => 'text',
                'text' => "# 画面のHTML\n```html\n".file_get_contents($manual.'/source.html')."\n```\n",
            ];
        }

        if (file_exists($manual.'/screenshot.png')) {
            $messageContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:image/png; base64, '.base64_encode(file_get_contents($manual.'/screenshot.png')),
                ],
            ];
        }

        $result = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => file_get_contents(base_path('tests/Browser/role/system.md')),
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
                            'text' => file_get_contents(base_path('tests/Browser/role/assistant.md')),
                        ],
                    ],
                ],
            ],
        ]);

        $traitContent = $result->choices[0]->message->content;
        $traitContent = str_replace('```php', '', $traitContent);
        $traitContent = str_replace('```', '', $traitContent);
        $traitContent = trim($traitContent);

        if (false !== strpos($traitContent, '<?php')) {
            file_put_contents($trait, $traitContent);
        }
    }
}
