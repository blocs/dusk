<?php

namespace Blocs\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAITest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openai:test {repeat?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chat OpenAI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $repeat = $this->argument('repeat') ?? 0;

        $chatOpenAIs = [];
        $chatOpenAILog = explode("\n", file_get_contents(storage_path('framework/cache/chatOpenAI.log')));
        foreach ($chatOpenAILog as $chatOpenAI) {
            $chatOpenAI = json_decode($chatOpenAI, true);
            if (! $chatOpenAI) {
                continue;
            }

            $chatOpenAIs[] = $chatOpenAI;
        }
        $repeat || dump($chatOpenAIs);

        for ($i = 1; $i <= $repeat; $i++) {
            echo '## '.$i."\n";
            $this->testOpenAI($chatOpenAIs);
        }
    }

    private function test_open_ai($chatOpenAIs)
    {
        foreach ($chatOpenAIs as $chatOpenAI) {
            $time_start = microtime(true);
            $result = OpenAI::chat()->create($chatOpenAI);

            if ($result->choices[0]->message->toolCalls) {
                $function = $result->choices[0]->message->toolCalls[0]->function;
                echo $function->name."\n";
                echo $function->arguments."\n";
            } else {
                echo $result->choices[0]->message->content."\n";
            }
            echo '-- '.(microtime(true) - $time_start)."\n\n";
        }
    }
}
