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

it('can filter models using in operator', function () {
    $names = $this->models->take(2)->pluck('name')->toArray();
    $models = createQueryFromParams(filter: "name IN ('{$names[0]}', '{$names[1]}')")
        ->get();

    expect($models)->toHaveCount(2);
    expect($models->pluck('name')->toArray())->toEqual($names);
});

it('can filter models using in operator on related models', function () {
    // Create test models
    $testModels = \Aqqo\OData\Tests\Testclasses\TestModel::factory()
        ->count(3)
        ->create();

    // Create related models for the first test model
    $relatedModel1 = new \Aqqo\OData\Tests\Testclasses\RelatedModel(['name' => 'Related1']);
    $relatedModel1->testModel()->associate($testModels[0]);
    $relatedModel1->save();

    $relatedModel2 = new \Aqqo\OData\Tests\Testclasses\RelatedModel(['name' => 'Related2']);
    $relatedModel2->testModel()->associate($testModels[0]);
    $relatedModel2->save();

    // Filter test models where related models have these names
    $models = createQueryFromParams(filter: "relatedModels/any(s:s/name in ('Related1', 'Related2'))")
        ->get();

    // We should get at least the first test model
    expect($models)->toHaveCount(1);
    expect($models[0]['id'])->toEqual($testModels[0]->id);
});

it('can filter models using in operator on related models with numbers', function () {
    // Create test models
    $testModels = \Aqqo\OData\Tests\Testclasses\TestModel::factory()
        ->count(3)
        ->create();

    // Create related models for the first test model with numeric values
    $relatedModel1 = new \Aqqo\OData\Tests\Testclasses\RelatedModel([
        'name' => 'Related1',
        'cost' => 100
    ]);
    $relatedModel1->testModel()->associate($testModels[0]);
    $relatedModel1->save();

    $relatedModel2 = new \Aqqo\OData\Tests\Testclasses\RelatedModel([
        'name' => 'Related2',
        'cost' => 200
    ]);
    $relatedModel2->testModel()->associate($testModels[0]);
    $relatedModel2->save();

    // Filter test models where related models have these costs
    $models = createQueryFromParams(filter: "relatedModels/any(s:s/cost in (100, 200))")
        ->get();

    // We should get at least the first test model
    expect($models)->toHaveCount(1);
    expect($models[0]['id'])->toEqual($testModels[0]->id);
});