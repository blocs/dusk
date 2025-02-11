<?php

namespace Blocs;

use Illuminate\View\ViewServiceProvider;

class DuskServiceProvider extends ViewServiceProvider
{
    public function register()
    {
        $this->app->singleton('command.blocs.dusk', function ($app) {
            return new Commands\Dusk();
        });

        $this->commands('command.blocs.dusk');

        // Publish
        $this->publishes([
            __DIR__.'/../tests/Browser/blocs' => base_path('tests/Browser/blocs'),
        ]);
    }
}
