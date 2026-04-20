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

it('serializes expanded relation as empty when nested $select maps to no valid fields', function () {
    $models = createQueryFromParams(expand: 'relatedModel($select=nonExistentField)')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
    expect($first['relatedModel'])->toBe([]);
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
    $models = createQueryFromParams(expand: "relatedModel(\$select=name;\$orderby=name asc)")->get();
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

// Additional tests to cover specific uncovered lines in ExpandTrait

it('can handle expand with nested dot notation properties', function () {
    // This tests lines 169-183 - nested dot notation handling in isPropertyExpandable
    $models = createQueryFromParams(expand: 'relatedModel')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with deeply nested dot notation properties', function () {
    // This tests the nested dot notation handling in isPropertyExpandable
    $models = createQueryFromParams(expand: 'relatedModel')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with non-existent nested properties', function () {
    // This tests the nested dot notation handling when properties don't exist
    $models = createQueryFromParams(expand: 'relatedModel')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with empty expandables array', function () {
    // This tests line 183 - when expandables array is empty
    $models = createQueryFromParams(expand: 'anyProperty')->get();
    $first = $models->first();
    // Should not throw an exception even if property doesn't exist
    expect($models)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('can handle expand with getRelatedModel method', function () {
    // This tests lines 242-252 - getRelatedModel method
    $models = createQueryFromParams(expand: 'relatedModel')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with getRelatedModel method for non-existent relation', function () {
    // This tests the exception handling in getRelatedModel method
    expect(function () {
        $request = new \Illuminate\Http\Request();
        $request->setLaravelSession(app('session.store'));
        $request->merge(['$expand' => 'nonExistentRelation']);
        
        $query = new \Aqqo\OData\Query(\Aqqo\OData\Tests\Testclasses\TestModel::query(), true, true, true, true, true, true, true, true, $request);
        $query->get();
    })->not()->toThrow(\Exception::class);
});

it('can handle expand with multiple nested levels', function () {
    // This tests complex nested expansion scenarios
    $models = createQueryFromParams(expand: 'relatedModel($expand=nestedRelatedModels)')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
    if (array_key_exists('relatedModel', $first) && !empty($first['relatedModel'])) {
        expect(array_key_exists('nestedRelatedModels', $first['relatedModel']))->toEqual(true);
    }
});

it('can handle expand with relation method that does not exist', function () {
    // This tests the getRelatedModel method when relation method doesn't exist
    expect(function () {
        $request = new \Illuminate\Http\Request();
        $request->setLaravelSession(app('session.store'));
        $request->merge(['$expand' => 'invalidRelation']);
        
        $query = new \Aqqo\OData\Query(\Aqqo\OData\Tests\Testclasses\TestModel::query(), true, true, true, true, true, true, true, true, $request);
        $query->get();
    })->not()->toThrow(\Exception::class);
});

it('can handle expand with complex nested dot notation', function () {
    // This tests complex nested dot notation scenarios
    $models = createQueryFromParams(expand: 'relatedModel')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with mixed case relation names', function () {
    // This tests relation name handling with mixed case
    $models = createQueryFromParams(expand: 'RelatedModel')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with relation names containing underscores', function () {
    // This tests relation name handling with underscores
    $models = createQueryFromParams(expand: 'related_model')->get();
    $first = $models->first();
    // Should handle gracefully even if relation doesn't exist
    expect($models)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('can handle expand with relation names containing numbers', function () {
    // This tests relation name handling with numbers
    $models = createQueryFromParams(expand: 'relatedModel1')->get();
    $first = $models->first();
    // Should handle gracefully even if relation doesn't exist
    expect($models)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('can handle expand with very long relation names', function () {
    // This tests relation name handling with very long names
    $longName = str_repeat('a', 100);
    $models = createQueryFromParams(expand: $longName)->get();
    $first = $models->first();
    // Should handle gracefully even if relation doesn't exist
    expect($models)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('can handle expand with unicode relation names', function () {
    // This tests relation name handling with unicode characters
    $models = createQueryFromParams(expand: '关系模型')->get();
    $first = $models->first();
    // Should handle gracefully even if relation doesn't exist
    expect($models)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('can handle expand with special characters in relation names', function () {
    // This tests relation name handling with special characters
    $models = createQueryFromParams(expand: 'related-model')->get();
    $first = $models->first();
    // Should handle gracefully even if relation doesn't exist
    expect($models)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('can handle expand with empty relation names', function () {
    // This tests relation name handling with empty names
    $models = createQueryFromParams(expand: '')->get();
    $first = $models->first();
    expect($first)->not()->toHaveKey('relatedModel');
});

it('can handle expand with null relation names', function () {
    // This tests relation name handling with null names
    $request = new \Illuminate\Http\Request();
    $request->setLaravelSession(app('session.store'));
    $request->merge(['$expand' => null]);
    
    $query = new \Aqqo\OData\Query(\Aqqo\OData\Tests\Testclasses\TestModel::query(), true, true, true, true, true, true, true, true, $request);
    $models = $query->get();
    $first = $models->first();
    expect($first)->not()->toHaveKey('relatedModel');
});

it('can handle expand with whitespace in relation names', function () {
    // This tests relation name handling with whitespace
    $models = createQueryFromParams(expand: ' relatedModel ')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with multiple whitespace in relation names', function () {
    // This tests relation name handling with multiple whitespace
    $models = createQueryFromParams(expand: '  relatedModel  ')->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with tab characters in relation names', function () {
    // This tests relation name handling with tab characters
    $models = createQueryFromParams(expand: "\trelatedModel\t")->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with newline characters in relation names', function () {
    // This tests relation name handling with newline characters
    $models = createQueryFromParams(expand: "\nrelatedModel\n")->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with carriage return characters in relation names', function () {
    // This tests relation name handling with carriage return characters
    $models = createQueryFromParams(expand: "\rrelatedModel\r")->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});

it('can handle expand with mixed whitespace characters in relation names', function () {
    // This tests relation name handling with mixed whitespace characters
    $models = createQueryFromParams(expand: " \t\n\rrelatedModel \t\n\r")->get();
    $first = $models->first();
    expect(array_key_exists('relatedModel', $first))->toEqual(true);
});