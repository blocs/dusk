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

        $this->app->singleton('command.openai.test', function ($app) {
            return new Commands\OpenAITest();
        });

        $this->commands('command.openai.test');

        // Publish
        $this->publishes([base_path('vendor/blocs/dusk/tests') => base_path('tests')]);
    }
}
