<?php

namespace Organi\Translatables\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Database\Eloquent\Factories\Factory;
use Organi\Translatables\TranslatablesServiceProvider;

/**
 * @internal
 * @coversNothing
 */
class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Organi\\Translatables\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        include_once __DIR__.'/../database/migrations/create_translatables_table.php.stub';
        (new \CreatePackageTable())->up();
        */
    }

    protected function getPackageProviders($app)
    {
        return [
            TranslatablesServiceProvider::class,
        ];
    }
}
