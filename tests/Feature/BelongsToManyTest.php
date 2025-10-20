<?php

namespace Aqqo\OData\Tests\Feature;

use Aqqo\OData\Query;
use Aqqo\OData\Tests\Testclasses\RelatedThroughPivotModel;
use Aqqo\OData\Tests\Testclasses\TestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

it('loads belongsToMany relations when expanding with a limited select', function () {
    $parent = TestModel::factory()->create(['name' => 'Parent']);
    $related = RelatedThroughPivotModel::query()->create(['name' => 'Child']);
    $parent->relatedThroughPivotModels()->attach($related->id);

    $query = Query::for(TestModel::class, new Request([
        '$select' => 'name',
        '$expand' => 'relatedThroughPivotModels',
    ]));

    $results = $query->get();

    expect($results)->toHaveCount(1);

    $entry = $results->first();
    expect($entry['name'])->toBe('Parent');
    expect($entry['relatedThroughPivotModels'])->toBeInstanceOf(Collection::class);
    expect($entry['relatedThroughPivotModels'])->toHaveCount(1);
    expect($entry['relatedThroughPivotModels']->first())->toMatchArray([
        'name' => 'Child',
    ]);
});
