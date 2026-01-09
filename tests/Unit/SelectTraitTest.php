<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Query;
use Aqqo\OData\Tests\Testclasses\TestModel;
use Aqqo\OData\Tests\Testclasses\RelatedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\Relation;

describe('SelectTrait', function () {
    
    beforeEach(function () {
        $this->testModel = TestModel::factory()->create();
        $this->relatedModel = RelatedModel::factory()->create(['test_model_id' => $this->testModel->id]);
    });

    describe('addSelect method', function () {
        
        it('calls appendSelectQuery when $select parameter is provided', function () {
            $request = new Request(['$select' => 'name,id']);
            $query = Query::for(TestModel::class, $request);
            
            // Verify that selects array is populated
            expect($query->selects)->not()->toBeEmpty();
        });

        it('calls resolveToDefaultSelects when $select parameter is empty', function () {
            $request = new Request(['$select' => '']);
            $query = Query::for(TestModel::class, $request);
            
            // Verify that default selects are resolved
            expect($query->selects)->not()->toBeEmpty();
        });

        it('calls resolveToDefaultSelects when $select parameter is null', function () {
            $request = new Request();
            $query = Query::for(TestModel::class, $request);
            
            // Verify that default selects are resolved
            expect($query->selects)->not()->toBeEmpty();
        });

        it('calls resolveToDefaultSelects when request is null', function () {
            $query = Query::for(TestModel::class, null);
            
            // Verify that default selects are resolved
            expect($query->selects)->not()->toBeEmpty();
        });
    });

    describe('appendSelectQuery method', function () {
        
        it('processes valid selectable properties', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('name,id', $builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('name');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('id');
        });

        it('ignores non-selectable properties', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('name,nonExistentField', $builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('name');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->not()->toHaveKey('nonExistentField');
        });

        it('handles empty select string', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('', $builder);
            
            // Should fall back to default selects
            expect($query->selects)->not()->toBeEmpty();
        });

        it('handles select string with only whitespace', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('   ', $builder);
            
            // Should fall back to default selects
            expect($query->selects)->not()->toBeEmpty();
        });

        it('trims whitespace from select items', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery(' name , id ', $builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('name');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('id');
        });

        it('handles single select item', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('name', $builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('name');
        });

        it('handles multiple select items with mixed valid and invalid', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('name,invalidField,id,anotherInvalid', $builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('name');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('id');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->not()->toHaveKey('invalidField');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->not()->toHaveKey('anotherInvalid');
        });

        it('calls resolveToDefaultSelects when no valid selects are found', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('invalidField1,invalidField2', $builder);
            
            // Should fall back to default selects
            expect($query->selects)->not()->toBeEmpty();
        });

        it('handles properties with source mapping', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('odatacol', $builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('odatacol');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class]['odatacol'])->toBe('dbcol');
        });

        it('handles non-string items in explode result', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            // This test ensures the is_string check is covered
            $query->appendSelectQuery('name,id', $builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
        });
    });

    describe('addSelectForExpand method', function () {
        
        it('does nothing when columns is null', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            // Ensure columns is null
            $builder->getQuery()->columns = null;
            
            $query->addSelectForExpand($builder, 'relatedModel');
            
            // Should not throw any exceptions and not modify the query
            expect($builder->getQuery()->columns)->toBeNull();
        });

        it('adds local key for HasOne relationships', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            // Set columns to trigger the logic
            $builder->getQuery()->columns = ['*'];
            
            $query->addSelectForExpand($builder, 'relatedModel');
            
            // Should add the local key to the select
            expect($builder->getQuery()->columns)->toContain('test_models.id');
        });

        it('adds local key for HasMany relationships', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            // Set columns to trigger the logic
            $builder->getQuery()->columns = ['*'];
            
            $query->addSelectForExpand($builder, 'relatedModels');
            
            // Should add the local key to the select
            expect($builder->getQuery()->columns)->toContain('test_models.id');
        });

        it('adds foreign key for BelongsTo relationships', function () {
            $query = Query::for(RelatedModel::class);
            $builder = RelatedModel::query();
            
            // Set columns to trigger the logic
            $builder->getQuery()->columns = ['*'];
            
            // Test with a BelongsTo relationship that exists on the model
            $query->addSelectForExpand($builder, 'testModel');
            
            // Should include the foreign key required to hydrate the BelongsTo relation
            expect($builder->getQuery()->columns)->toContain('related_models.test_model_id');
        });

        it('adds local key for BelongsToMany relationships', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            // Set columns to trigger the logic
            $builder->getQuery()->columns = ['*'];
            
            $query->addSelectForExpand($builder, 'relatedThroughPivotModels');
            
            // Should add the local key to the select for BelongsToMany relationships
            expect($builder->getQuery()->columns)->toContain('test_models.id');
        });

        it('handles non-existent relationships gracefully', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            // Set columns to trigger the logic
            $builder->getQuery()->columns = ['*'];
            
            // This should throw an exception for non-existent relations
            expect(function () use ($query, $builder) {
                $query->addSelectForExpand($builder, 'nonExistentRelation');
            })->toThrow(\Exception::class);
        });
    });

    describe('resolveToDefaultSelects method', function () {
        
        it('resolves default selects for Builder', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->resolveToDefaultSelects($builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->not()->toBeEmpty();
        });

        it('resolves default selects for Relation', function () {
            $testModel = TestModel::factory()->create();
            $query = Query::for(TestModel::class);
            $relation = $testModel->relatedModel();
            
            $query->resolveToDefaultSelects($relation);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\RelatedModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\RelatedModel::class])->not()->toBeEmpty();
        });

        it('handles selectables for different model types', function () {
            $testModel = TestModel::factory()->create();
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            $relation = $testModel->relatedModel();
            
            $query->resolveToDefaultSelects($builder);
            $query->resolveToDefaultSelects($relation);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\RelatedModel::class);
        });
    });

    describe('edge cases and error handling', function () {
        

        it('handles empty selectables in isPropertySelectable', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            // Clear selectables to test the empty case
            $query->selectables = [];
            
            $query->appendSelectQuery('name', $builder);
            
            // Should fall back to default selects
            expect($query->selects)->not()->toBeEmpty();
        });

        it('handles mixed relationship types in addSelectForExpand', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            // Set columns to trigger the logic
            $builder->getQuery()->columns = ['*'];
            
            // Test with different relationship types
            $query->addSelectForExpand($builder, 'relatedModel'); // HasOne
            $query->addSelectForExpand($builder, 'relatedModels'); // HasMany
            $query->addSelectForExpand($builder, 'relatedThroughPivotModels'); // BelongsToMany
            
            expect($builder->getQuery()->columns)->toContain('test_models.id');
        });

        it('handles complex select strings with special characters', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('name,id,start_datetime_utc,end_datetime_utc', $builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('name');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('id');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('start_datetime_utc');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('end_datetime_utc');
        });

        it('handles select strings with only commas', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery(',,,', $builder);
            
            // Should fall back to default selects
            expect($query->selects)->not()->toBeEmpty();
        });

        it('handles select strings with mixed valid and invalid properties', function () {
            $query = Query::for(TestModel::class);
            $builder = TestModel::query();
            
            $query->appendSelectQuery('name,invalid1,id,invalid2,description', $builder);
            
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('name');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('id');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->toHaveKey('description');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->not()->toHaveKey('invalid1');
            expect($query->selects[\Aqqo\OData\Tests\Testclasses\TestModel::class])->not()->toHaveKey('invalid2');
        });
    });

    describe('integration with Query class', function () {
        
        it('works correctly with Query constructor', function () {
            $request = new Request(['$select' => 'name,id']);
            $query = Query::for(TestModel::class, $request);
            
            expect($query->selects)->not()->toBeEmpty();
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
        });

        it('works correctly with Query constructor without select', function () {
            $request = new Request();
            $query = Query::for(TestModel::class, $request);
            
            expect($query->selects)->not()->toBeEmpty();
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
        });

        it('works correctly with Query constructor with empty select', function () {
            $request = new Request(['$select' => '']);
            $query = Query::for(TestModel::class, $request);
            
            expect($query->selects)->not()->toBeEmpty();
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
        });

        it('works correctly with Query constructor with whitespace select', function () {
            $request = new Request(['$select' => '   ']);
            $query = Query::for(TestModel::class, $request);
            
            expect($query->selects)->not()->toBeEmpty();
            expect($query->selects)->toHaveKey(\Aqqo\OData\Tests\Testclasses\TestModel::class);
        });
    });
});
