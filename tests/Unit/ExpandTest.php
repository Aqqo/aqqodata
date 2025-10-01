<?php

use function Aqqo\OData\Tests\Feature\createQueryFromParams;

beforeEach(function () {
    $this->models = \Aqqo\OData\Tests\Testclasses\TestModel::factory()->count(5)->create();

    $this->models->each(function (\Aqqo\OData\Tests\Testclasses\TestModel $model, $index) {
        $model
            ->relatedModel()->create([
                'test_model_id' => $model->id,
                'name' => $model->name
            ]);


        $model->relatedModel->nestedRelatedModels()->create([
            'related_model_id' => $model->relatedModel->id,
            'name' => $model->name
        ]);
    });
});

it('can have a single one to one expand', function () {
    $models = createQueryFromParams(expand: 'relatedModel')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first) && !empty($first['relatedModel']['name']))->toEqual(true);
});

it('can have a single one to many expand', function () {
    $models = createQueryFromParams(expand: 'relatedModels')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModels', $first) && !empty($first['relatedModels'][0]['name']))->toEqual(true);
});

it('can have multiple expands', function () {
    $models = createQueryFromParams(expand: 'relatedModel,relatedModels')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModels', $first) && !empty($first['relatedModels'][0]['name']))->toEqual(true)
        ->and(array_key_exists('relatedModel', $first) && !empty($first['relatedModel']['name']))->toEqual(true);
});

it('can have multiple expands with details', function () {
    $models = createQueryFromParams(expand: 'relatedModel($expand=nestedRelatedModels),relatedModels')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModels', $first) && !empty($first['relatedModels'][0]['name']))->toEqual(true)
        ->and(array_key_exists('relatedModel', $first) && !empty($first['relatedModel']['name']))->toEqual(true)
        ->and(array_key_exists('nestedRelatedModels', $first['relatedModel'])  && !empty($first['relatedModel']['nestedRelatedModels'][0]['name']))->toEqual(true);
});

it('can handle expand with $select option', function () {
    $models = createQueryFromParams(expand: 'relatedModel($select=name)')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
    expect($first['relatedModel'])->toHaveKey('name');
});

it('can handle expand with $filter option', function () {
    $name = $this->models->first()->name;
    $models = createQueryFromParams(expand: "relatedModel(\$filter=name eq '{$name}')")->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with $orderby option', function () {
    $models = createQueryFromParams(expand: 'relatedModel($orderby=name asc)')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with $expand option', function () {
    $models = createQueryFromParams(expand: 'relatedModel($expand=nestedRelatedModels)')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
    expect(array_key_exists('nestedRelatedModels', $first['relatedModel']))->toEqual(true);
});

it('can handle expand with implied expand without prefix', function () {
    $models = createQueryFromParams(expand: 'relatedModel(nestedRelatedModels)')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
    expect(array_key_exists('nestedRelatedModels', $first['relatedModel']))->toEqual(true);
});

it('can handle expand with invalid relation name', function () {
    $query = createQueryFromParams(expand: 'invalidRelation');
    $models = $query->get();
    $first = $models->first();
    expect(array_key_exists('invalidRelation', $first))->toEqual(false);
});

it('can handle expand with multiple options', function () {
    $name = $this->models->first()->name;
    $models = createQueryFromParams(expand: "relatedModel(\$select=name;\$filter=name eq '{$name}';\$orderby=name asc)")->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with empty expand query', function () {
    $models = createQueryFromParams(expand: '')->get();
    $first = $models->first();
    expect($first)->not()->toHaveKey('relatedModel');
});

it('can handle expand with null expand query', function () {
    $request = new \Illuminate\Http\Request();
    $request->setLaravelSession(app('session.store'));
    $request->merge(['$expand' => null]);
    
    $query = new \Aqqo\OData\Query(\Aqqo\OData\Tests\Testclasses\TestModel::query(), true, true, true, true, true, true, true, true, $request);
    $models = $query->get();
    $first = $models->first();
    expect($first)->not()->toHaveKey('relatedModel');
});

it('can handle expand with invalid relation name that throws exception', function () {
    $request = new \Illuminate\Http\Request();
    $request->setLaravelSession(app('session.store'));
    $request->merge(['$expand' => 'invalidRelation']);
    
    $query = new \Aqqo\OData\Query(\Aqqo\OData\Tests\Testclasses\TestModel::query(), true, true, true, true, true, true, true, true, $request);
    
    // This should not throw an exception, but should handle gracefully
    $models = $query->get();
    expect($models)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('can handle expand with nested relation access', function () {
    $request = new \Illuminate\Http\Request();
    $request->setLaravelSession(app('session.store'));
    $request->merge(['$expand' => 'relatedModel']);
    
    $query = new \Aqqo\OData\Query(\Aqqo\OData\Tests\Testclasses\TestModel::query(), true, true, true, true, true, true, true, true, $request);
    
    // This should handle relation access
    $models = $query->get();
    expect($models)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});