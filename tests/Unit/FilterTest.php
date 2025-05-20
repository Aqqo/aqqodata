<?php

namespace Aqqo\OData\Tests\Unit;

use function Aqqo\OData\Tests\Feature\createQueryFromParams;

beforeEach(function () {
    $this->models = \Aqqo\OData\Tests\Testclasses\TestModel::factory()->count(5)->create();
});

describe('Basic Filter Operations', function () {
    it('filters models by exact name match', function () {
        $name = $this->models->first()->name;
        $models = createQueryFromParams(filter: "name eq '{$name}'")
            ->get();

        expect($models)->toHaveCount(1);
        expect($models->first()['name'])->toEqual($name);
    });

    it('filters models by name not equal', function () {
        $name = $this->models->first()->name;
        $models = createQueryFromParams(filter: "name ne '{$name}'")
            ->get();

        expect($models)->toHaveCount(4);
        expect($models->first()['name'])->not()->toEqual($name);
    });

    it('filters models by multiple conditions', function () {
        $name = $this->models->first()->name;
        $models = createQueryFromParams(filter: "name eq '{$name}' and test gt 0")
            ->get();

        expect($models)->toHaveCount(1);
        expect($models->first()['name'])->toEqual($name);
    });
});

describe('Advanced Filter Operations', function () {
    it('filters models using contains operator', function () {
        $name = $this->models->first()->name;
        $partialName = substr($name, 0, 3);
        
        // Count occurrences of partialName in all model names
        $occurrenceCount = 0;
        $this->models->each(function ($model) use ($partialName, &$occurrenceCount) {
            $occurrenceCount += substr_count($model->name, $partialName);
        });
        
        $models = createQueryFromParams(filter: "contains(name, '{$partialName}')")
            ->get();

        expect($models)->toHaveCount($occurrenceCount);
        expect($models->first()['name'])->toContain($partialName);
    });

    it('filters models using startswith operator', function () {
        $name = $this->models->first()->name;
        $start = substr($name, 0, 3);

         // Count occurrences of partialName in all model names
         $occurrenceCount = 0;
         $this->models->each(function ($model) use ($start, &$occurrenceCount) {
            if (str_starts_with($model->name, $start)) {
                $occurrenceCount++;
            }
         });

        $models = createQueryFromParams(filter: "startswith(name, '{$start}')")
            ->get();

        expect($models)->toHaveCount($occurrenceCount);
        expect($models->first()['name'])->toStartWith($start);
    });

    it('filters models using endswith operator', function () {
        $name = $this->models->first()->name;
        $end = substr($name, -3);

        $occurrenceCount = 0;
         $this->models->each(function ($model) use ($end, &$occurrenceCount) {
            if (str_ends_with($model->name, $end)) {
                $occurrenceCount++;
            }
         });

        $models = createQueryFromParams(filter: "endswith(name, '{$end}')")
            ->get();

        expect($models)->toHaveCount($occurrenceCount);
        expect($models->first()['name'])->toEndWith($end);
    });
});

describe('Filter with Related Models', function () {
    beforeEach(function () {
        // Create related models for each test model
        $this->models->each(function ($model) {
            $model->relatedModels()->create([
                'name' => $model->name,
                'cost' => 10
            ]);
        });
    });

    it('filters models using any operator on related models', function () {
        $name = $this->models->first()->name;
        $models = createQueryFromParams(filter: "relatedModels/any(s:s/name eq '{$name}')")
            ->get();

        expect($models)->toHaveCount(1);
        expect($models->first()['name'])->toEqual($name);
    });

    it('filters models using all operator on related models', function () {
        $name = $this->models->first()->name;
        $models = createQueryFromParams(filter: "relatedModels/all(f:f/name eq '{$name}')")
            ->get();

        expect($models)->toHaveCount(1);
        expect($models->first()['name'])->toEqual($name);
    });
});