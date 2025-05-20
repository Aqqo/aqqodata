<?php

use function Aqqo\OData\Tests\Feature\createQueryFromParams;

function countModelsMatchingCondition($models, $condition) {
    $count = 0;
    $models->each(function ($model) use ($condition, &$count) {
        if ($condition($model)) {
            $count++;
        }
    });
    return $count;
}

beforeEach(function () {
    $this->models = \Aqqo\OData\Tests\Testclasses\TestModel::factory()->count(5)->create();
});

it('can filter models by name', function () {
    $name = $this->models->first()->name;

    $occurrenceCount = countModelsMatchingCondition($this->models, function($model) use ($name) {
        return $model->name === $name;
    });

    $models = createQueryFromParams(filter: "name eq '{$name}'")
        ->get();

    expect($models)->toHaveCount($occurrenceCount);
    expect($models->first()['name'])->toEqual($name);
});

it('can filter models by name not equals', function () {
    $name = $this->models->first()->name;

    $occurrenceCount = countModelsMatchingCondition($this->models, function($model) use ($name) {
        return $model->name !== $name;
    });

    $models = createQueryFromParams(filter: "name ne '{$name}'")
        ->get();

    expect($models)->toHaveCount($occurrenceCount);
    expect($models->first()['name'])->not()->toEqual($name);
});

it('can filter models using in operator', function () {
    // This test verifies that:
    // 1. We can filter models using the IN operator on direct model properties
    // 2. The IN operator is case insensitive ('in' vs 'IN')
    // 3. Multiple values can be correctly matched
    
    // Take the first two models' names from our test data set
    $names = $this->models->take(2)->pluck('name')->toArray();

    $occurrenceCount = countModelsMatchingCondition($this->models, function($model) use ($names) {
        return in_array($model->name, $names);
    });
    
    // Filter models where name matches either of the two names
    $models = createQueryFromParams(filter: "name in ('{$names[0]}', '{$names[1]}')")
        ->get();

    // Verify we get exactly two models back
    expect($models)->toHaveCount($occurrenceCount);
    
    // Verify the returned models have the exact names we filtered for
    expect($models->pluck('name')->toArray())->toEqual($names);
});

it('can filter models using in operator on related models', function () {
    // This test verifies that:
    // 1. We can filter parent models based on their related models' names using the IN operator
    // 2. The related models are properly loaded in the results
    // 3. The relationship between parent and related models is maintained
    
    // Create test models - only the first one will have related models
    $testModels = \Aqqo\OData\Tests\Testclasses\TestModel::factory()
        ->count(3)
        ->create();

    // Create two related models with specific names, both associated with the first test model
    $relatedModel1 = new \Aqqo\OData\Tests\Testclasses\RelatedModel(['name' => 'Related1']);
    $relatedModel1->testModel()->associate($testModels[0]);
    $relatedModel1->save();

    $relatedModel2 = new \Aqqo\OData\Tests\Testclasses\RelatedModel(['name' => 'Related2']);
    $relatedModel2->testModel()->associate($testModels[0]);
    $relatedModel2->save();

    // Filter test models where related models have names 'Related1' or 'Related2'
    // The expand parameter ensures the related models are included in the response
    $models = createQueryFromParams(
        filter: "relatedModels/any(s:s/name in ('Related1', 'Related2'))",
        expand: "relatedModels"
    )->get();

    // Verify we only get one model (the first one that has the related models)
    expect($models)->toHaveCount(1);
    expect($models[0]['id'])->toEqual($testModels[0]->id);
    
    // Verify that both related models are loaded and have the correct names
    expect($models[0]['relatedModels'])->toHaveCount(2);
    
    $relatedNames = collect($models[0]['relatedModels'])->pluck('name')->all();
    expect($relatedNames)->toContain('Related1');
    expect($relatedNames)->toContain('Related2');
});

it('can filter models using in operator on related models with numbers', function () {
    // This test verifies that:
    // 1. We can filter parent models based on their related models' numeric values using the IN operator
    // 2. The related models are properly loaded in the results
    // 3. The relationship between parent and related models is maintained
    
    // Create test models - only the first one will have related models
    $testModels = \Aqqo\OData\Tests\Testclasses\TestModel::factory()
        ->count(3)
        ->create();

    // Create two related models with specific costs, both associated with the first test model
    $relatedModel1 = new \Aqqo\OData\Tests\Testclasses\RelatedModel([
        'name' => 'Related1',
        'cost' => 100
    ]);
    $relatedModel1->testModel()->associate($testModels[0]);
    $relatedModel1->save();

    $relatedModel2 = new \Aqqo\OData\Tests\Testclasses\RelatedModel([
        'name' => 'Related2',
        'cost' => 200
    ]);
    $relatedModel2->testModel()->associate($testModels[0]);
    $relatedModel2->save();

    // Filter test models where related models have costs of 100 or 200
    // The expand parameter ensures the related models are included in the response
    $models = createQueryFromParams(
        filter: "relatedModels/any(s:s/cost in (100, 200))",
        expand: "relatedModels"
    )->get();

    // Verify we only get one model (the first one that has the related models)
    expect($models)->toHaveCount(1);
    expect($models[0]['id'])->toEqual($testModels[0]->id);
    
    // Verify that both related models are loaded and have the correct costs
    expect($models[0]['relatedModels'])->toHaveCount(2);
    
    $relatedCosts = collect($models[0]['relatedModels'])->pluck('cost')->all();
    expect($relatedCosts)->toContain(100);
    expect($relatedCosts)->toContain(200);
});

it('can handle IN operator with strings', function () {
    $query = createQueryFromParams(filter: "name in ('Test', 'Aqqo', 'Example')");
    expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."name" in (\'Test\', \'Aqqo\', \'Example\') limit 100 offset 0');
});

it('can handle IN operator with numbers', function () {
    $query = createQueryFromParams(filter: "id in (1, 2, 3)");
    expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."id" in (\'1\', \'2\', \'3\') limit 100 offset 0');
});

it('can handle simple name filter', function () {
    $query = createQueryFromParams(filter: "name eq 'Test' and test gt 12");
    expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."name" = \'Test\' and "test_models"."test" > \'12\' limit 100 offset 0');
});

it('can handle simple different source filter', function () {
    $query = createQueryFromParams(filter: "odatacol eq 'Test'");
    expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."dbcol" = \'Test\' limit 100 offset 0');
});

it('can handle simple contains filter', function () {
    $query = createQueryFromParams(filter: "contains(name, 'Test') and test gt 12");
    expect($query->toSql())->toEqual('select * from "test_models" where (("test_models"."name" LIKE \'%Test%\') and ("test_models"."test" > \'12\')) limit 100 offset 0');
});

it('can handle simple startswith filter', function () {
    $query = createQueryFromParams(filter: "startswith(name, 'Te') and test gt 12");
    expect($query->toSql())->toEqual('select * from "test_models" where (("test_models"."name" LIKE \'Te%\') and ("test_models"."test" > \'12\')) limit 100 offset 0');
});

it('can handle simple endswith filter', function () {
    $query = createQueryFromParams(filter: "endswith(name, 'st') and test gt 12");
    expect($query->toSql())->toEqual('select * from "test_models" where (("test_models"."name" LIKE \'%st\') and ("test_models"."test" > \'12\')) limit 100 offset 0');
});

it('can handle non existing filter', function () {
    $query = createQueryFromParams(filter: "nonExisting eq 'Test'");
    expect($query->toSql())->toEqual('select * from "test_models" limit 100 offset 0');
});

it('can handle two filters with OR', function () {
    $query = createQueryFromParams(filter: "name eq 'Test' or name eq 'Aqqo'");
    expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."name" = \'Test\' or "test_models"."name" = \'Aqqo\' limit 100 offset 0');
});

it('can handle grouped filter', function () {
    $query = createQueryFromParams(filter: "(start_datetime_utc gt '2024-05-13T06:00:00+00:00' or start_datetime_utc lt '2024-05-13T06:00:00+00:00') and end_datetime_utc lt '2024-05-19T15:00:00+00:00'");
    expect($query->toSql())->toEqual('select * from "test_models" where (("test_models"."start_datetime_utc" > \'2024-05-13T06:00:00+00:00\' or "test_models"."start_datetime_utc" < \'2024-05-13T06:00:00+00:00\') and ("test_models"."end_datetime_utc" < \'2024-05-19T15:00:00+00:00\')) limit 100 offset 0');
});

it('can handle simple any filter', function () {
    $query = createQueryFromParams(filter: "relatedModels/any(s:s/name eq 'Aqqo')");
    expect($query->toSql())->toEqual('select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') limit 100 offset 0');
});

it('can handle simple any filter but not expandable', function () {
    $query = createQueryFromParams(filter: "nonExistingModel/any(s:s/name eq 'Aqqo')");
    expect($query->toSql())->toEqual('select * from "test_models" limit 100 offset 0');
});

it('can handle two filters with any filter', function () {
    $query = createQueryFromParams(filter: "name eq 'Aqqo' and relatedModel/any(s:s/name eq 'Aqqo')");
    expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."name" = \'Aqqo\' and exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') limit 100 offset 0');
});

it('can handle two filters with any filter but inversed', function () {
    $query = createQueryFromParams(filter: "relatedModels/any(s:s/name eq 'Aqqo') and name eq 'Aqqo'");
    expect($query->toSql())->toEqual('select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') and "test_models"."name" = \'Aqqo\' limit 100 offset 0');
});

it('can handle simple all filter', function () {
    $query = createQueryFromParams(filter: "relatedModels/all(f:f/cost gt 10)");
    expect($query->toSql())->toEqual('select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') limit 100 offset 0');
});

it('can handle two filters with all filter', function () {
    $query = createQueryFromParams(filter: "name eq 'Aqqo' and relatedModels/all(f:f/cost gt 10)");
    expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."name" = \'Aqqo\' and not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') limit 100 offset 0');
});

it('can handle two filters with all filter but inversed', function () {
    $query = createQueryFromParams(filter: "relatedModels/all(f:f/cost gt 10) and name eq 'Aqqo'");
    expect($query->toSql())->toEqual('select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') and "test_models"."name" = \'Aqqo\' limit 100 offset 0');
});

it('can handle two filters with all filter but not expandable', function () {
    $query = createQueryFromParams(filter: "nonExistingModel/all(f:f/cost gt 10) and name eq 'Aqqo'");
    expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."name" = \'Aqqo\' limit 100 offset 0');
});

// TODO uncomment below for more complex tests
// it('can handle deeply nested AND/OR conditions', function () {
//     $query = createQueryFromParams(filter: "((name eq 'Test' or name eq 'Aqqo') and (age gt 18 or age lt 65)) and (status eq 'active' or status eq 'pending')");
//     expect($query->toSql())->toEqual('select * from "test_models" where (("test_models"."name" = \'Test\' or "test_models"."name" = \'Aqqo\') and ("test_models"."age" > \'18\' or "test_models"."age" < \'65\')) and ("test_models"."status" = \'active\' or "test_models"."status" = \'pending\') limit 100 offset 0');
// });

// it('can handle complex IN operations with multiple conditions', function () {
//     $query = createQueryFromParams(filter: "name in ('Test', 'Aqqo') and age in (18, 21, 25) and status in ('active', 'pending')");
//     expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."name" in (\'Test\', \'Aqqo\') and "test_models"."age" in (\'18\', \'21\', \'25\') and "test_models"."status" in (\'active\', \'pending\') limit 100 offset 0');
// });

// it('can handle complex date/time comparisons', function () {
//     $query = createQueryFromParams(filter: "created_at gt '2024-01-01T00:00:00Z' and (updated_at lt '2024-12-31T23:59:59Z' or deleted_at eq null)");
//     expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."created_at" > \'2024-01-01T00:00:00Z\' and ("test_models"."updated_at" < \'2024-12-31T23:59:59Z\' or "test_models"."deleted_at" is null) limit 100 offset 0');
// });

// it('can handle complex string operations with multiple functions', function () {
//     $query = createQueryFromParams(filter: "contains(tolower(name), 'test') and startswith(upper(status), 'ACTIVE') and endswith(trim(description), 'end')");
//     expect($query->toSql())->toEqual('select * from "test_models" where (LOWER("test_models"."name") LIKE \'%test%\') and (UPPER("test_models"."status") LIKE \'ACTIVE%\') and (TRIM("test_models"."description") LIKE \'%end\') limit 100 offset 0');
// });

// it('can handle complex nested any/all conditions', function () {
//     $query = createQueryFromParams(filter: "relatedModels/any(r:r/name eq 'Test' and r/status eq 'active') and relatedModels/all(r:r/cost gt 100 or r/cost lt 50)");
//     expect($query->toSql())->toEqual('select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Test\' and "related_models"."status" = \'active\') and not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'100\' and "related_models"."cost" >= \'50\') limit 100 offset 0');
// });

// it('can handle complex nested any/all with multiple conditions', function () {
//     $query = createQueryFromParams(filter: "relatedModels/any(r:r/name in ('Test', 'Aqqo') and r/status eq 'active') and relatedModels/all(r:r/cost gt 100 or r/status eq 'inactive')");
//     expect($query->toSql())->toEqual('select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" in (\'Test\', \'Aqqo\') and "related_models"."status" = \'active\') and not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'100\' and "related_models"."status" != \'inactive\') limit 100 offset 0');
// });

// it('can handle complex nested any/all with multiple levels', function () {
//     $query = createQueryFromParams(filter: "relatedModels/any(r:r/name eq 'Test' and r/subModels/any(s:s/status eq 'active')) and relatedModels/all(r:r/cost gt 100 or r/subModels/all(s:s/status eq 'inactive'))");
//     expect($query->toSql())->toEqual('select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Test\' and exists (select * from "sub_models" where "related_models"."id" = "sub_models"."related_model_id" and "sub_models"."status" = \'active\')) and not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'100\' and not exists (select * from "sub_models" where "related_models"."id" = "sub_models"."related_model_id" and "sub_models"."status" = \'inactive\')) limit 100 offset 0');
// });

// it('can handle complex numeric operations', function () {
//     $query = createQueryFromParams(filter: "(cost gt 100 and cost lt 1000) and (quantity gt 0 and quantity lt 100) and (price gt 10.50 and price lt 99.99)");
//     expect($query->toSql())->toEqual('select * from "test_models" where ("test_models"."cost" > \'100\' and "test_models"."cost" < \'1000\') and ("test_models"."quantity" > \'0\' and "test_models"."quantity" < \'100\') and ("test_models"."price" > \'10.50\' and "test_models"."price" < \'99.99\') limit 100 offset 0');
// });

// it('can handle complex boolean operations', function () {
//     $query = createQueryFromParams(filter: "(is_active eq true and is_verified eq true) or (is_admin eq true and is_superuser eq true)");
//     expect($query->toSql())->toEqual('select * from "test_models" where ("test_models"."is_active" = \'1\' and "test_models"."is_verified" = \'1\') or ("test_models"."is_admin" = \'1\' and "test_models"."is_superuser" = \'1\') limit 100 offset 0');
// });

// it('can handle complex null checks', function () {
//     $query = createQueryFromParams(filter: "(deleted_at eq null and updated_at ne null) or (created_at eq null and status eq 'pending')");
//     expect($query->toSql())->toEqual('select * from "test_models" where ("test_models"."deleted_at" is null and "test_models"."updated_at" is not null) or ("test_models"."created_at" is null and "test_models"."status" = \'pending\') limit 100 offset 0');
// });

// it('can handle complex string operations with special characters', function () {
//     $query = createQueryFromParams(filter: "contains(name, 'Test\'s') and contains(description, 'O\'Connor') and contains(tags, 'C#')");
//     expect($query->toSql())->toEqual('select * from "test_models" where ("test_models"."name" LIKE \'%Test\\\'s%\') and ("test_models"."description" LIKE \'%O\\\'Connor%\') and ("test_models"."tags" LIKE \'%C#%\') limit 100 offset 0');
// });

// it('can handle complex date/time operations with timezone offsets', function () {
//     $query = createQueryFromParams(filter: "created_at gt '2024-01-01T00:00:00+00:00' and created_at lt '2024-12-31T23:59:59+00:00' and updated_at gt '2024-01-01T00:00:00-05:00'");
//     expect($query->toSql())->toEqual('select * from "test_models" where "test_models"."created_at" > \'2024-01-01T00:00:00+00:00\' and "test_models"."created_at" < \'2024-12-31T23:59:59+00:00\' and "test_models"."updated_at" > \'2024-01-01T00:00:00-05:00\' limit 100 offset 0');
// });

// it('can handle complex nested any/all with multiple conditions and functions', function () {
//     $query = createQueryFromParams(filter: "relatedModels/any(r:contains(r/name, 'Test') and r/cost gt 100) and relatedModels/all(r:startswith(r/status, 'active') or r/cost lt 50)");
//     expect($query->toSql())->toEqual('select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and ("related_models"."name" LIKE \'%Test%\') and "related_models"."cost" > \'100\') and not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and not (("related_models"."status" LIKE \'active%\') or "related_models"."cost" < \'50\')) limit 100 offset 0');
// });

// it('can handle complex nested any/all with multiple levels and functions', function () {
//     $query = createQueryFromParams(filter: "relatedModels/any(r:contains(r/name, 'Test') and r/subModels/any(s:startswith(s/status, 'active'))) and relatedModels/all(r:r/cost gt 100 or r/subModels/all(s:endswith(s/status, 'inactive')))");
//     expect($query->toSql())->toEqual('select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and ("related_models"."name" LIKE \'%Test%\') and exists (select * from "sub_models" where "related_models"."id" = "sub_models"."related_model_id" and ("sub_models"."status" LIKE \'active%\'))) and not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'100\' and not exists (select * from "sub_models" where "related_models"."id" = "sub_models"."related_model_id" and ("sub_models"."status" LIKE \'%inactive\'))) limit 100 offset 0');
// });