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

        $app['db']->connection()->getSchemaBuilder()->create('sub_models', function (Blueprint $table) {
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

        // ---------------------------------------------------------------------
        // OData "spec" models used for hard URL queries (feature coverage)
        // ---------------------------------------------------------------------
        $app['db']->connection()->getSchemaBuilder()->create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('discount_limit', 12, 2)->nullable();
            $table->timestamps();
        });

        $app['db']->connection()->getSchemaBuilder()->create('order_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->decimal('price', 12, 2)->default(0);
        });

        $app['db']->connection()->getSchemaBuilder()->create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('name')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('category_id')->nullable();
            $table->unsignedInteger('category_int_id')->nullable();
            $table->string('name')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('suppliers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country')->nullable();
            $table->integer('rating')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('product_supplier', function (Blueprint $table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('supplier_id');
        });

        $app['db']->connection()->getSchemaBuilder()->create('reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('reviewer_id')->nullable();
            $table->integer('rating')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('sales', function (Blueprint $table) {
            $table->increments('id');
            $table->string('region')->nullable();
            $table->integer('year')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
        });

        $app['db']->connection()->getSchemaBuilder()->create('subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('status')->nullable();
            $table->dateTime('renewal_date')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('manager_id')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('projects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('owner_id')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('assignee_id')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('documents', function (Blueprint $table) {
            $table->increments('id');
            $table->text('body')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('document_id')->nullable();
            $table->string('t')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('blogs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('blog_id')->nullable();
            $table->dateTime('published_date')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('events', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('attendees_count')->nullable();
            $table->text('optional_note')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);
        });

        $app['db']->connection()->getSchemaBuilder()->create('invoice_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_id')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('taxes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_item_id')->nullable();
            $table->decimal('rate', 5, 4)->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('flights', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('segments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('flight_id')->nullable();
            $table->dateTime('departure_time')->nullable();
            $table->dateTime('arrival_time')->nullable();
            $table->integer('delay_minutes')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('warehouses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('stocks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('warehouse_id')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->integer('quantity')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('messages', function (Blueprint $table) {
            $table->increments('id');
            $table->text('body')->nullable();
            $table->dateTime('created_at')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country')->nullable();
            $table->decimal('balance', 12, 2)->default(0);
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
