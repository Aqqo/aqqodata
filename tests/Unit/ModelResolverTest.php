<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Resolvers\ModelResolver;
use Aqqo\OData\Tests\Testclasses\MorphModel;
use Aqqo\OData\Tests\Testclasses\TestModel;

it('resolves models with configured selects', function () {
    $model = TestModel::factory()->create(['name' => 'Selected Name']);
    $model->setRelation('relatedModels', collect());

    $resolver = new ModelResolver(['testmodel' => ['name' => 'name']]);

    $result = $resolver->resolveModel($model);

    expect($result)->toBe(['name' => 'Selected Name', 'relatedModels' => collect()]);
});

it('resolves morph relations while respecting select rules', function () {
    $parent = TestModel::factory()->create(['name' => 'Parent Name']);
    $morph = new MorphModel([
        'parent_type' => TestModel::class,
        'parent_id' => $parent->id,
        'name' => 'Morph Child',
    ]);
    $morph->save();
    $morph->load('parent');

    $resolver = new ModelResolver([
        'testmodel' => ['name' => 'name'],
        'morphmodel' => ['name' => 'name'],
    ]);

    $result = $resolver->resolveModel($morph);

    expect($result)->toHaveKey('parent')
        ->and($result['parent'])
        ->toHaveKey('name', 'Parent Name');
});

it('refreshes selects before resolving collections', function () {
    $model = TestModel::factory()->create(['name' => 'First Name', 'email' => 'first@example.com']);
    $collection = TestModel::where('id', $model->id)->get();

    $resolver = new ModelResolver(['testmodel' => ['name' => 'name']]);
    $resolver->refreshSelects(['testmodel' => ['email' => 'email']]);

    $results = $resolver->resolveCollection($collection);

    expect($results->first())->toBe(['email' => 'first@example.com']);
});
