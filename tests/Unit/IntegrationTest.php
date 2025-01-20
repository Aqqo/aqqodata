<?php

use function Aqqo\OData\Tests\Feature\createQueryFromParams;

beforeEach(function () {
    $this->models = \Aqqo\OData\Tests\Testclasses\TestModel::factory()->count(5)->create();

    $this->models->each(function (\Aqqo\OData\Tests\Testclasses\TestModel $model, $index) {
        $model
            ->relatedModel()->create([
                'test_model_id' => $model->id,
                'name' => $model->name,
                'cost' => $index
            ]);


        $model->relatedModel->nestedRelatedModels()->create([
            'related_model_id' => $model->relatedModel->id,
            'name' => $model->name,
        ]);
    });
});

it('can have many parameters', function() {
    $models = createQueryFromParams(skip: 0, top: 2, filter: 'RelatedModel/any(s:s/cost eq 1)', count: true, expand: 'relatedModel,relatedModels($expand=nestedRelatedModels)')->get();
    var_dump($models->toArray());
    $first = $models->first();
    expect($models->count())->toEqual(1)
        ->and(array_key_exists('relatedModels', $first) && !empty($first['relatedModels'][0]['name']))->toEqual(true)
        ->and(array_key_exists('relatedModel', $first) && !empty($first['relatedModel']['name']))->toEqual(true)
        ->and(array_key_exists('nestedRelatedModels', $first['relatedModels'][0]) && !empty($first['relatedModels'][0]['nestedRelatedModels'][0]['name']))->toEqual(true);
});
