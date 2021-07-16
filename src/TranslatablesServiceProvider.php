<?php

namespace Roobieboobieee\Translatables;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Roobieboobieee\Translatables\Commands\TranslatablesCommand;

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
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_translatables_table')
            ->hasCommand(TranslatablesCommand::class);
    }
}
