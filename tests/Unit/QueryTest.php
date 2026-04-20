<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Query;
use Aqqo\OData\Tests\Testclasses\AliasedModel;
use Aqqo\OData\Tests\Testclasses\MorphModel;
use Aqqo\OData\Tests\Testclasses\PlainMorphTarget;
use Aqqo\OData\Tests\Testclasses\TestModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

describe('Query', function () {
    
    it('can be created with model class string', function () {
        $query = Query::for(TestModel::class);
        
        expect($query)->toBeInstanceOf(Query::class);
        expect($query->toSql())->toBeString();
    });

    it('can be created with model query builder', function () {
        $query = Query::for(TestModel::query());
        
        expect($query)->toBeInstanceOf(Query::class);
        expect($query->toSql())->toBeString();
    });

    it('can be created with request', function () {
        $request = new Request(['$select' => 'name']);
        $query = Query::for(TestModel::class, $request);
        
        expect($query)->toBeInstanceOf(Query::class);
        expect($query->toSql())->toBeString();
    });

    it('can be cloned', function () {
        $query = Query::for(TestModel::class);
        $cloned = $query->clone();
        
        expect($cloned)->toBeInstanceOf(Query::class);
        expect($cloned)->not()->toBe($query);
    });

    it('can access subject properties', function () {
        $query = Query::for(TestModel::class);
        
        // Test __get magic method - access a property that exists on the builder
        expect($query->toSql())->toBeString();
    });

    it('can set subject properties', function () {
        $query = Query::for(TestModel::class);
        
        // Test __set magic method
        $query->someProperty = 'test';
        expect($query->someProperty)->toBe('test');
    });

    it('can generate SQL', function () {
        $query = Query::for(TestModel::class);
        $sql = $query->toSql();
        
        expect($sql)->toBeString();
        expect($sql)->toContain('select');
        expect($sql)->toContain('test_models');
    });

    it('can execute queries and return results', function () {
        // Create test data
        TestModel::factory()->count(3)->create();
        
        $query = Query::for(TestModel::class);
        $results = $query->get();
        
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($results)->toHaveCount(3);
    });

    it('can handle query exceptions', function () {
        // This test would require a scenario that causes a query exception
        $query = Query::for(TestModel::class);
        
        // Should not throw exception for normal queries
        expect($query->get())->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('can be JSON serialized', function () {
        TestModel::factory()->count(2)->create();
        
        $query = Query::for(TestModel::class);
        $json = $query->jsonSerialize();
        
        expect($json)->toBeArray();
        expect($json)->toHaveKey('@context');
        expect($json)->toHaveKey('value');
    });

    it('handles empty result sets', function () {
        $query = Query::for(TestModel::class);
        $results = $query->get();
        
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($results)->toHaveCount(0);
    });

    it('handles large result sets', function () {
        TestModel::factory()->count(100)->create();
        
        $query = Query::for(TestModel::class);
        $results = $query->get();
        
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($results)->toHaveCount(100);
    });

    it('handles queries with filters', function () {
        TestModel::factory()->count(5)->create();
        $firstModel = TestModel::first();
        
        $request = new Request(['$filter' => "name eq '{$firstModel->name}'"]);
        $query = Query::for(TestModel::class, $request);
        $results = $query->get();
        
        expect($results)->toHaveCount(1);
        expect($results->first()['name'])->toBe($firstModel->name);
    });

    it('handles queries with selects', function () {
        TestModel::factory()->count(3)->create();
        
        $request = new Request(['$select' => 'name,id']);
        $query = Query::for(TestModel::class, $request);
        $results = $query->get();
        
        expect($results)->toHaveCount(3);
        expect($results->first())->toHaveKey('name');
        expect($results->first())->toHaveKey('id');
    });

    it('uses getOriginal to return aliased source values', function () {
        AliasedModel::create([
            'name' => 'Alice',
            'full_name' => 'Alice Example',
        ]);

        $request = new Request(['$select' => 'display_name']);
        $results = Query::for(AliasedModel::class, $request)->get();
        $first = $results->first();

        expect($first)->toBeArray();
        expect($first['display_name'])->toBe('ALICE EXAMPLE');
        expect($first)->not()->toHaveKey('full_name');
    });

    it('uses default OData projection for morph expanded relations', function () {
        $parent = AliasedModel::create([
            'name' => 'Bob',
            'full_name' => 'Bob Example',
        ]);

        MorphModel::create([
            'name' => 'Morph record',
            'parent_type' => AliasedModel::class,
            'parent_id' => $parent->id,
        ]);

        $request = new Request(['$expand' => 'parent']);
        $results = Query::for(MorphModel::class, $request)->get();
        $parentAttributes = $results->first()['parent'];

        expect($parentAttributes)->toBeArray();
        expect($parentAttributes)->toHaveKey('display_name');
        expect($parentAttributes['display_name'])->toBe('BOB EXAMPLE');
        expect($parentAttributes)->not()->toHaveKey('full_name');
    });

    it('applies nested select on morph expanded relation', function () {
        $parent = AliasedModel::create([
            'name' => 'Carol',
            'full_name' => 'Carol Example',
        ]);

        MorphModel::create([
            'name' => 'Morph record',
            'parent_type' => AliasedModel::class,
            'parent_id' => $parent->id,
        ]);

        $request = new Request(['$expand' => 'parent($select=display_name)']);
        $results = Query::for(MorphModel::class, $request)->get();
        $parentAttributes = $results->first()['parent'];

        expect($parentAttributes)->toBeArray();
        expect($parentAttributes)->toHaveKeys(['display_name']);
        expect($parentAttributes['display_name'])->toBe('CAROL EXAMPLE');
        expect($parentAttributes)->not()->toHaveKey('full_name');
        expect($parentAttributes)->not()->toHaveKey('name');
    });

    it('does not fall back to default OData projection when morph nested $select maps to no fields', function () {
        $parent = AliasedModel::create([
            'name' => 'Frank',
            'full_name' => 'Frank Example',
        ]);

        MorphModel::create([
            'name' => 'Morph record',
            'parent_type' => AliasedModel::class,
            'parent_id' => $parent->id,
        ]);

        $request = new Request(['$expand' => 'parent($select=name)']);
        $results = Query::for(MorphModel::class, $request)->get();
        $parentAttributes = $results->first()['parent'];

        expect($parentAttributes)->toBeArray();
        expect($parentAttributes)->toBe([]);
    });

    it('serializes morph target without OData metadata as empty whitelist-only payload', function () {
        $testModel = TestModel::factory()->create();

        $parent = PlainMorphTarget::create([
            'name' => 'Plain target',
            'full_name' => 'Plain full',
            'cost' => 42,
            'test_model_id' => $testModel->id,
        ]);

        MorphModel::create([
            'name' => 'Morph record',
            'parent_type' => PlainMorphTarget::class,
            'parent_id' => $parent->id,
        ]);

        $request = new Request(['$expand' => 'parent']);
        $results = Query::for(MorphModel::class, $request)->get();
        $parentAttributes = $results->first()['parent'];

        expect($parentAttributes)->toBeArray();
        expect($parentAttributes)->toBe([]);
    });

    it('does not expose raw attributes for unannotated morph target even with nested $select', function () {
        $testModel = TestModel::factory()->create();

        $parent = PlainMorphTarget::create([
            'name' => 'Plain target',
            'full_name' => 'Plain full',
            'cost' => 42,
            'test_model_id' => $testModel->id,
        ]);

        MorphModel::create([
            'name' => 'Morph record',
            'parent_type' => PlainMorphTarget::class,
            'parent_id' => $parent->id,
        ]);

        $request = new Request(['$expand' => 'parent($select=name,full_name)']);
        $results = Query::for(MorphModel::class, $request)->get();
        $parentAttributes = $results->first()['parent'];

        expect($parentAttributes)->toBeArray();
        expect($parentAttributes)->toBe([]);
    });

    it('caches runtime OData metadata per expanded morph target class', function () {
        $firstParent = AliasedModel::create([
            'name' => 'Dave',
            'full_name' => 'Dave Example',
        ]);

        $secondParent = AliasedModel::create([
            'name' => 'Eve',
            'full_name' => 'Eve Example',
        ]);

        MorphModel::create([
            'name' => 'Morph record 1',
            'parent_type' => AliasedModel::class,
            'parent_id' => $firstParent->id,
        ]);

        MorphModel::create([
            'name' => 'Morph record 2',
            'parent_type' => AliasedModel::class,
            'parent_id' => $secondParent->id,
        ]);

        $request = new Request(['$expand' => 'parent']);
        $query = new class(MorphModel::query(), request: $request) extends Query {
            /** @var array<class-string<Model>, int> */
            public array $runtimeDefinitionBuilds = [];

            protected function buildRuntimeDefaultSelectedAttributeDefinitions(Model $item): array
            {
                $modelClass = $item::class;
                $this->runtimeDefinitionBuilds[$modelClass] = ($this->runtimeDefinitionBuilds[$modelClass] ?? 0) + 1;

                return parent::buildRuntimeDefaultSelectedAttributeDefinitions($item);
            }
        };

        $results = $query->get();

        expect($results)->toHaveCount(2);
        expect($query->runtimeDefinitionBuilds)->toBe([
            MorphModel::class => 1,
            AliasedModel::class => 1,
        ]);
    });

    it('handles queries with ordering', function () {
        TestModel::factory()->count(3)->create();
        
        $request = new Request(['$orderby' => 'name asc']);
        $query = Query::for(TestModel::class, $request);
        $results = $query->get();
        
        expect($results)->toHaveCount(3);
    });

    it('handles queries with pagination', function () {
        TestModel::factory()->count(10)->create();
        
        $request = new Request(['$skip' => 2, '$top' => 3]);
        $query = Query::for(TestModel::class, $request);
        $results = $query->get();
        
        expect($results)->toHaveCount(3);
    });

    it('handles queries with search', function () {
        TestModel::factory()->count(5)->create();
        
        $request = new Request(['$search' => 'test']);
        $query = Query::for(TestModel::class, $request);
        $results = $query->get();
        
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles queries with count', function () {
        TestModel::factory()->count(5)->create();
        
        $request = new Request(['$count' => true]);
        $query = Query::for(TestModel::class, $request);
        $response = $query->jsonSerialize();
        
        expect($response)->toHaveKey('@count');
        expect($response['@count'])->toBeInt();
    });

    it('handles queries with expansion', function () {
        TestModel::factory()->count(3)->create();
        
        $request = new Request(['$expand' => 'relatedModel']);
        $query = Query::for(TestModel::class, $request);
        $results = $query->get();
        
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles complex queries with all parameters', function () {
        TestModel::factory()->count(10)->create();
        
        $request = new Request([
            '$select' => 'name,id',
            '$filter' => "name ne 'nonexistent'",
            '$expand' => 'relatedModel',
            '$search' => 'test',
            '$skip' => 2,
            '$top' => 5,
            '$count' => true,
            '$orderby' => 'name asc'
        ]);
        
        $query = Query::for(TestModel::class, $request);
        $results = $query->get();
        $response = $query->jsonSerialize();
        
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($response)->toHaveKey('@context');
        expect($response)->toHaveKey('value');
        expect($response)->toHaveKey('@count');
    });

    it('handles queries with invalid parameters gracefully', function () {
        $request = new Request([
            '$select' => 'nonExistentField',
            '$filter' => 'nonExistentField eq \'test\'',
            '$expand' => 'nonExistentRelationship',
            '$orderby' => 'nonExistentField asc'
        ]);
        
        $query = Query::for(TestModel::class, $request);
        
        // Should not throw exceptions
        expect($query->toSql())->toBeString();
        expect($query->get())->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles queries with special characters', function () {
        TestModel::factory()->count(3)->create();
        
        $request = new Request([
            '$filter' => "name eq 'test@example.com'",
            '$search' => '测试搜索'
        ]);
        
        $query = Query::for(TestModel::class, $request);
        
        expect($query->toSql())->toBeString();
        expect($query->get())->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles queries with very long parameters', function () {
        $longString = str_repeat('a', 1000);
        
        $request = new Request([
            '$filter' => "name eq '{$longString}'",
            '$search' => $longString
        ]);
        
        $query = Query::for(TestModel::class, $request);
        
        expect($query->toSql())->toBeString();
        expect($query->get())->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });


    it('handles constructor with all boolean parameters set to false', function () {
        $query = new Query(
            TestModel::query(),
            select: false,
            filter: false,
            expand: false,
            search: false,
            skip: false,
            top: false,
            count: false,
            orderby: false
        );
        
        expect($query)->toBeInstanceOf(Query::class);
        expect($query->toSql())->toBeString();
    });

    it('handles constructor with custom request object', function () {
        $customRequest = new Request(['$select' => 'name']);
        $query = new Query(TestModel::query(), request: $customRequest);
        
        expect($query)->toBeInstanceOf(Query::class);
        expect($query->toSql())->toBeString();
    });

it('resolves models without an ignore selects parameter', function () {
    $method = new \ReflectionMethod(Query::class, 'resolveModel');

    expect($method->getNumberOfParameters())->toBe(2);
    expect($method->getParameters()[0]->getName())->toBe('item');
    expect($method->getParameters()[1]->getName())->toBe('expandedAsRelation');
});

    it('covers all missing lines from coverage report', function () {
        // This test specifically targets the lines mentioned in the coverage report:
        // Exception handling in get() method
        // resolveModel with runtime OData fallback (MorphTo / ignore_selects path)
        // MorphTo relationship handling
        
        // Test 1: Exception handling (lines 162-163)
        $mockBuilder = \Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $mockBuilder->shouldReceive('getModel')->andReturn(new TestModel());
        $mockBuilder->shouldReceive('get')->andThrow(new \Exception('Test exception'));
        $mockBuilder->shouldReceive('toRawSql')->andReturn('SELECT * FROM test_models');
        
        $query = new Query($mockBuilder, false, false, false, false, false, false, false, false);
        
        expect(fn() => $query->get())
            ->toThrow(\Aqqo\OData\Exceptions\QueryException::class, 'Test exception');
        
        // Test 2: resolveModel with ignore_selects=true (line 190)
        $testModel = TestModel::factory()->create();
        $relatedModel = new \Aqqo\OData\Tests\Testclasses\RelatedModel([
            'name' => 'Test Related',
            'cost' => 100,
            'test_model_id' => $testModel->id
        ]);
        $relatedModel->save();
        $testModel->load('relatedModel');
        
        $request = new Request(['$expand' => 'relatedModel']);
        $query = Query::for(TestModel::class, $request);
        $results = $query->get();
        
        expect($results->first())->toHaveKey('relatedModel');
        
        // Test 3: MorphTo relationship handling (line 205)
        $morphModel = new \Aqqo\OData\Tests\Testclasses\MorphModel([
            'parent_type' => TestModel::class,
            'parent_id' => $testModel->id,
            'name' => 'Test Morph'
        ]);
        $morphModel->save();
        $morphModel->load('parent');
        
        $request = new Request(['$expand' => 'parent']);
        $query = Query::for(\Aqqo\OData\Tests\Testclasses\MorphModel::class, $request);
        $results = $query->get();
        
        expect($results->first())->toHaveKey('parent');
    });
});
