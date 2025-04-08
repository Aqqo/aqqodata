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
    $models = createQueryFromParams(filter: "name in ('{$names[0]}', '{$names[1]}')")
        ->get();

    expect($models)->toHaveCount(2);
    expect($models->pluck('name')->toArray())->toEqual($names);
});

it('can filter models using in operator on related models', function () {
    // This test verifies that:
    // 1. We can filter parent models based on their related models' names using the IN operator
    // 2. The related models are properly loaded in the results
    // 3. The relationship between parent and related models is maintained
    
    // Create test models - only the first one will have related models
    $testModels = \Aqqo\OData\Tests\Testclasses\TestModel::factory()
        ->count(3)
        ->create();

    // Create two related models with specific names, both associated with the first test model
    $relatedModel1 = new \Aqqo\OData\Tests\Testclasses\RelatedModel(['name' => 'Related1']);
    $relatedModel1->testModel()->associate($testModels[0]);
    $relatedModel1->save();

    $relatedModel2 = new \Aqqo\OData\Tests\Testclasses\RelatedModel(['name' => 'Related2']);
    $relatedModel2->testModel()->associate($testModels[0]);
    $relatedModel2->save();

    // Filter test models where related models have names 'Related1' or 'Related2'
    // The expand parameter ensures the related models are included in the response
    $models = createQueryFromParams(
        filter: "relatedModels/any(s:s/name in ('Related1', 'Related2'))",
        expand: "relatedModels"
    )->get();

    // Verify we only get one model (the first one that has the related models)
    expect($models)->toHaveCount(1);
    expect($models[0]['id'])->toEqual($testModels[0]->id);
    
    // Verify that both related models are loaded and have the correct names
    expect($models[0]['relatedModels'])->toHaveCount(2);
    
    $relatedNames = collect($models[0]['relatedModels'])->pluck('name')->all();
    expect($relatedNames)->toContain('Related1');
    expect($relatedNames)->toContain('Related2');
});

it('can filter models using in operator on related models with numbers', function () {
    // This test verifies that:
    // 1. We can filter parent models based on their related models' numeric values using the IN operator
    // 2. The related models are properly loaded in the results
    // 3. The relationship between parent and related models is maintained
    
    // Create test models - only the first one will have related models
    $testModels = \Aqqo\OData\Tests\Testclasses\TestModel::factory()
        ->count(3)
        ->create();

    // Create two related models with specific costs, both associated with the first test model
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

    // Filter test models where related models have costs of 100 or 200
    // The expand parameter ensures the related models are included in the response
    $models = createQueryFromParams(
        filter: "relatedModels/any(s:s/cost in (100, 200))",
        expand: "relatedModels"
    )->get();

    // Verify we only get one model (the first one that has the related models)
    expect($models)->toHaveCount(1);
    expect($models[0]['id'])->toEqual($testModels[0]->id);
    
    // Verify that both related models are loaded and have the correct costs
    expect($models[0]['relatedModels'])->toHaveCount(2);
    
    $relatedCosts = collect($models[0]['relatedModels'])->pluck('cost')->all();
    expect($relatedCosts)->toContain(100);
    expect($relatedCosts)->toContain(200);
});