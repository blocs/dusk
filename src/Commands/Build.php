<?php

namespace Blocs\Commands;

use Illuminate\Console\Command;

class Build extends Command
{
    protected $signature = 'blocs:dusk {path}';
    protected $description = 'Build laravel dusk browser tests';

    public function handle()
    {
        $path = $this->argument('path');
        if (!file_exists($path)) {
            return;
        }
    }
}
