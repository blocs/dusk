<?php

namespace Blocs;

use Illuminate\View\ViewServiceProvider;

class DuskServiceProvider extends ViewServiceProvider
{
    public function register()
    {
        $this->app->singleton('command.blocs.dusk', function ($app) {
            return new Commands\DuskTest();
        });

        $this->commands('command.blocs.dusk');
    }
}
