<?php

namespace Aqqo\OData\Tests;

use Aqqo\OData\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Database\Eloquent\Factories\Factory;

class Testcase extends \Orchestra\Testbench\TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase($this->app);

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Aqqo\\OData\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function setUpDatabase(?Application $app): void
    {
        if (is_null($app)) {
            return;
        }

        $app['db']->connection()->getSchemaBuilder()->create('test_models', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('name')->nullable();
            $table->string('full_name')->nullable();
            $table->double('salary')->nullable();
            $table->boolean('is_visible')->default(true);
        });

        $app['db']->connection()->getSchemaBuilder()->create('append_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('firstname');
            $table->string('lastname');
        });

        $app['db']->connection()->getSchemaBuilder()->create('soft_delete_models', function (Blueprint $table) {
            $table->increments('id');
            $table->softDeletes();
            $table->string('name');
        });

        $app['db']->connection()->getSchemaBuilder()->create('scope_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        $app['db']->connection()->getSchemaBuilder()->create('related_models', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('test_model_id');
            $table->string('name');
            $table->string('full_name')->nullable();
            $table->integer('cost')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('nested_related_models', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('related_model_id');
            $table->string('name');
        });

        $app['db']->connection()->getSchemaBuilder()->create('pivot_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('test_model_id');
            $table->integer('related_through_pivot_model_id');
            $table->string('location')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('related_through_pivot_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        $app['db']->connection()->getSchemaBuilder()->create('morph_models', function (Blueprint $table) {
            $table->increments('id');
            $table->morphs('parent');
            $table->string('name');
        });
    }

    /**
     * @param $app
     * @return \class-string[]
     */
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
