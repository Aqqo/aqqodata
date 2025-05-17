<?php

namespace Aqqo\OData\Tests\Feature;

it('Run filter', function (?string $filter, string $result) {
    $query = createQueryFromParams(filter: $filter);
    expect($query->toSql())->toEqual($result);
})->with([
    "Without filters" => ["", 'select * from "test_models" limit 100 offset 0'],
    "IN operator with strings" => ["name in ('Test', 'Aqqo', 'Example')", 'select * from "test_models" where "test_models"."name" in (\'Test\', \'Aqqo\', \'Example\') limit 100 offset 0'],
    "IN operator with numbers" => ["id in (1, 2, 3)", 'select * from "test_models" where "test_models"."id" in (\'1\', \'2\', \'3\') limit 100 offset 0'],
    "Simple name filter" => ["name eq 'Test' and test gt 12", 'select * from "test_models" where "test_models"."name" = \'Test\' and "test_models"."test" > \'12\' limit 100 offset 0'],
    "Simple different source filter" => ["odatacol eq 'Test'", 'select * from "test_models" where "test_models"."dbcol" = \'Test\' limit 100 offset 0'],
    "Simple contains filter" => ["contains(name, 'Test') and test gt 12", 'select * from "test_models" where (("test_models"."name" LIKE \'%Test%\') and ("test_models"."test" > \'12\')) limit 100 offset 0'],
    "Simple startswith filter" => ["startswith(name, 'Te') and test gt 12", 'select * from "test_models" where (("test_models"."name" LIKE \'Te%\') and ("test_models"."test" > \'12\')) limit 100 offset 0'],
    "Simple endswith filter" => ["endswith(name, 'st') and test gt 12", 'select * from "test_models" where (("test_models"."name" LIKE \'%st\') and ("test_models"."test" > \'12\')) limit 100 offset 0'],
    "Non existing filter" => ["nonExisting eq 'Test'", 'select * from "test_models" limit 100 offset 0'],
    "Two filters" => ["name eq 'Test' or name eq 'Aqqo'", 'select * from "test_models" where "test_models"."name" = \'Test\' or "test_models"."name" = \'Aqqo\' limit 100 offset 0'],
    "Grouped filter" => ["(start_datetime_utc gt '2024-05-13T06:00:00+00:00' or start_datetime_utc lt '2024-05-13T06:00:00+00:00') and end_datetime_utc lt '2024-05-19T15:00:00+00:00'", 'select * from "test_models" where (("test_models"."start_datetime_utc" > \'2024-05-13T06:00:00+00:00\' or "test_models"."start_datetime_utc" < \'2024-05-13T06:00:00+00:00\') and ("test_models"."end_datetime_utc" < \'2024-05-19T15:00:00+00:00\')) limit 100 offset 0'],
    "Simple any filter" => ["relatedModels/any(s:s/name eq 'Aqqo')", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') limit 100 offset 0'],
    "Simple any filter but not expandable" => ["nonExistingModel/any(s:s/name eq 'Aqqo')", 'select * from "test_models" limit 100 offset 0'],
    "Two filters with any filter" => ["name eq 'Aqqo' and relatedModel/any(s:s/name eq 'Aqqo')", 'select * from "test_models" where "test_models"."name" = \'Aqqo\' and exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') limit 100 offset 0'],
    "Two filters with any filter but inversed" => ["relatedModels/any(s:s/name eq 'Aqqo') and name eq 'Aqqo'", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') and "test_models"."name" = \'Aqqo\' limit 100 offset 0'],
    "Simple all filter" => ["relatedModels/all(f:f/cost gt 10)", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') limit 100 offset 0'],
    "Two filters with all filter" => ["name eq 'Aqqo' and relatedModels/all(f:f/cost gt 10)", 'select * from "test_models" where "test_models"."name" = \'Aqqo\' and not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') limit 100 offset 0'],
    "Two filters with all filter but inversed" => ["relatedModels/all(f:f/cost gt 10) and name eq 'Aqqo'", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') and "test_models"."name" = \'Aqqo\' limit 100 offset 0'],
    "Two filters with all filter but not expandable" => ["nonExistingModel/all(f:f/cost gt 10) and name eq 'Aqqo'", 'select * from "test_models" where "test_models"."name" = \'Aqqo\' limit 100 offset 0'],
]);
