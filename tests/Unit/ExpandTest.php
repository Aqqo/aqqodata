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