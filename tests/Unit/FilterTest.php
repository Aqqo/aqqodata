<?php

use function Aqqo\OData\Tests\Feature\createQueryFromParams;

beforeEach(function () {
    $this->models = \Aqqo\OData\Tests\Testclasses\TestModel::factory()->count(5)->create();
});

it('can filter models by name', function () {
    $name = $this->models->first()->name;
    $models = createQueryFromParams(filter: "name eq '{$name}'")
        ->get();

    expect($models)->toHaveCount(1);
    expect($models->first()['name'])->toEqual($name);
});

it('can filter models by name not equals', function () {
    $name = $this->models->first()->name;
    $models = createQueryFromParams(filter: "name ne '{$name}'")
        ->get();

    expect($models)->toHaveCount(4);
    expect($models->first()['name'])->not()->toEqual($name);
});