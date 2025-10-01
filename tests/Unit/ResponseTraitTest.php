<?php

namespace Aqqo\OData\Tests\Unit;

use function Aqqo\OData\Tests\Feature\createQueryFromParams;

beforeEach(function () {
    // Create test data
    $this->models = \Aqqo\OData\Tests\Testclasses\TestModel::factory()->count(10)->create();
});

describe('ResponseTrait', function () {
    
    it('returns basic response structure', function () {
        $query = createQueryFromParams();
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('@context');
        expect($response)->toHaveKey('value');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('includes count when requested', function () {
        $query = createQueryFromParams(count: true);
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('@count');
        expect($response['@count'])->toBeInt();
        expect($response['@count'])->toBeGreaterThanOrEqual(0);
    });

    it('does not include count when not requested', function () {
        $query = createQueryFromParams(count: false);
        $response = $query->getResponse();
        
        expect($response)->not()->toHaveKey('@count');
    });

    it('includes nextLink when there are more results', function () {
        $query = createQueryFromParams(top: 5);
        $response = $query->getResponse();
        
        if (count($this->models) > 5) {
            expect($response)->toHaveKey('@nextLink');
            expect($response['@nextLink'])->toBeString();
        }
    });

    it('does not include nextLink when all results are returned', function () {
        $query = createQueryFromParams(top: 100);
        $response = $query->getResponse();
        
        expect($response)->not()->toHaveKey('@nextLink');
    });

    it('handles empty result set', function () {
        // Delete all models to get empty result
        \Aqqo\OData\Tests\Testclasses\TestModel::query()->delete();
        
        $query = createQueryFromParams();
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('value');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($response['value'])->toBeEmpty();
    });

    it('handles filtered results', function () {
        $firstModel = $this->models->first();
        $query = createQueryFromParams(filter: "name eq '{$firstModel->name}'");
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('value');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($response['value'])->toHaveCount(1);
    });

    it('handles pagination with skip', function () {
        $query = createQueryFromParams(skip: 5, top: 3);
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('value');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($response['value'])->toHaveCount(3);
    });

    it('handles ordering in response', function () {
        $query = createQueryFromParams(orderby: 'name asc');
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('value');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles expanded relationships in response', function () {
        // Create related models
        $this->models->each(function ($model) {
            $model->relatedModel()->create([
                'name' => 'Related ' . $model->name,
                'cost' => 100
            ]);
        });
        
        $query = createQueryFromParams(expand: 'relatedModel');
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('value');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles select fields in response', function () {
        $query = createQueryFromParams(select: 'name,id');
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('value');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles search in response', function () {
        $query = createQueryFromParams(search: 'test');
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('value');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles complex queries in response', function () {
        $query = createQueryFromParams(
            select: 'name,id',
            filter: "name ne 'nonexistent'",
            expand: 'relatedModel',
            search: 'test',
            skip: 2,
            top: 5,
            count: true,
            orderby: 'name asc'
        );
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('@context');
        expect($response)->toHaveKey('value');
        expect($response)->toHaveKey('@count');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles response with resource collection', function () {
        // Create test data with a model that has a resource property
        \Aqqo\OData\Tests\Testclasses\TestModelWithResource::factory()->count(3)->create();
        
        $query = createQueryFromParams(model: \Aqqo\OData\Tests\Testclasses\TestModelWithResource::class);
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('value');
        expect($response['value'])->not()->toBeNull();
    });

    it('handles very large result sets', function () {
        // Create many models
        \Aqqo\OData\Tests\Testclasses\TestModel::factory()->count(1000)->create();
        
        $query = createQueryFromParams(top: 100);
        $response = $query->getResponse();
        
        expect($response)->toHaveKey('value');
        expect($response['value'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($response['value'])->toHaveCount(100);
    });

    it('handles response context correctly', function () {
        $query = createQueryFromParams();
        $response = $query->getResponse();
        
        expect($response['@context'])->toBeString();
        expect($response['@context'])->toContain('api/$metadata#');
    });

    it('handles nextLink generation correctly', function () {
        $query = createQueryFromParams(top: 3);
        $response = $query->getResponse();
        
        if (isset($response['@nextLink'])) {
            expect($response['@nextLink'])->toContain('$skip=');
            expect($response['@nextLink'])->toBeString();
        }
    });
});
