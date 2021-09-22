<?php

namespace RoobieBoobieee\Translatables;

use Illuminate\Database\Schema\Blueprint;
use RoobieBoobieee\Translatables\Commands\TranslatablesCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TranslatablesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('translatables')
            ->hasConfigFile();


        Blueprint::macro('translations', function ($table) {
            $this->foreignId('id');
            $this->string('locale', 5);

            $this->unique(['id', 'locale']);
            $this->foreign('id')->references('id')->on($table);

            return $this;
        });
    }
}
