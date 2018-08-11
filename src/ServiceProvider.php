<?php

namespace Tohtamysh\ImagePreview;


class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ImagePreview::class, function () {
            return new ImagePreview();
        });
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}