<?php

namespace Bonnier\SystemText;

use Illuminate\Support\ServiceProvider;

class SystemTextProvider extends ServiceProvider
{
    private static $translationPath;

    protected $commands = [
        'Bonnier\SystemText\Console\Commands\FetchTranslationsCommand',
    ];

    public function boot()
    {
        $this->loadTranslationsFrom(self::getTranslationPath(), 'bonnier');
    }

    public function register()
    {
        $this->commands($this->commands);
    }

    public static function getTranslationPath()
    {
        if(!self::$translationPath) {
            self::$translationPath = __DIR__.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'lang';
        }
        return self::$translationPath;
    }
}