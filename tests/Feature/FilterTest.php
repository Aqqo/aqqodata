<?php

namespace Aqqo\OData\Tests\Feature;

it('Run filter', function (?string $filter, string $result) {
    $query = createQueryFromParams(filter: $filter);
    expect($query->toSql())->toEqual($result);
})->with([
    "Without filters" => ["", 'select * from "test_models" limit 100 offset 0'],
    "IN operator with strings" => ["name in ('Test', 'Aqqo', 'Example')", 'select * from "test_models" where "test_models"."name" in (\'Test\', \'Aqqo\', \'Example\') limit 100 offset 0'],
    "IN operator with numbers" => ["id in (1, 2, 3)", 'select * from "test_models" where "test_models"."id" in (1, 2, 3) limit 100 offset 0'],
    "Simple name filter" => ["name eq 'Test' and test gt 12", 'select * from "test_models" where "test_models"."name" = \'Test\' and "test_models"."test" > \'12\' limit 100 offset 0'],
    "Simple different source filter" => ["odatacol eq 'Test'", 'select * from "test_models" where "test_models"."dbcol" = \'Test\' limit 100 offset 0'],
    "Simple contains filter" => ["contains(name, 'Test') and test gt 12", 'select * from "test_models" where (("test_models"."name" LIKE \'%Test%\') and ("test_models"."test" > \'12\')) limit 100 offset 0'],
    "Simple startswith filter" => ["startswith(name, 'Te') and test gt 12", 'select * from "test_models" where (("test_models"."name" LIKE \'Te%\') and ("test_models"."test" > \'12\')) limit 100 offset 0'],
    "Simple endswith filter" => ["endswith(name, 'st') and test gt 12", 'select * from "test_models" where (("test_models"."name" LIKE \'%st\') and ("test_models"."test" > \'12\')) limit 100 offset 0'],
    "Non existing filter" => ["nonExisting eq 'Test'", 'select * from "test_models" limit 100 offset 0'],
    "Two filters" => ["name eq 'Test' or name eq 'Aqqo'", 'select * from "test_models" where ("test_models"."name" = \'Test\' or "test_models"."name" = \'Aqqo\') limit 100 offset 0'],
    "Grouped filter" => ["(start_datetime_utc gt '2024-05-13T06:00:00+00:00' or start_datetime_utc lt '2024-05-13T06:00:00+00:00') and end_datetime_utc lt '2024-05-19T15:00:00+00:00'", 'select * from "test_models" where (("test_models"."start_datetime_utc" > \'2024-05-13T06:00:00+00:00\' or "test_models"."start_datetime_utc" < \'2024-05-13T06:00:00+00:00\') and "test_models"."end_datetime_utc" < \'2024-05-19T15:00:00+00:00\') limit 100 offset 0'],
    "Simple any filter" => ["relatedModels/any(s:s/name eq 'Aqqo')", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') limit 100 offset 0'],
    "Simple any filter but not expandable" => ["nonExistingModel/any(s:s/name eq 'Aqqo')", 'select * from "test_models" limit 100 offset 0'],
    "Two filters with any filter" => ["name eq 'Aqqo' and relatedModel/any(s:s/name eq 'Aqqo')", 'select * from "test_models" where "test_models"."name" = \'Aqqo\' and exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') limit 100 offset 0'],
    "Two filters with any filter but inversed" => ["relatedModels/any(s:s/name eq 'Aqqo') and name eq 'Aqqo'", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') and "test_models"."name" = \'Aqqo\' limit 100 offset 0'],
    "Simple all filter" => ["relatedModels/all(f:f/cost gt 10)", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') limit 100 offset 0'],
    "Two filters with all filter" => ["name eq 'Aqqo' and relatedModels/all(f:f/cost gt 10)", 'select * from "test_models" where "test_models"."name" = \'Aqqo\' and not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') limit 100 offset 0'],
    "Two filters with all filter but inversed" => ["relatedModels/all(f:f/cost gt 10) and name eq 'Aqqo'", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') and "test_models"."name" = \'Aqqo\' limit 100 offset 0'],
    "Two filters with all filter but not expandable" => ["nonExistingModel/all(f:f/cost gt 10) and name eq 'Aqqo'", 'select * from "test_models" where "test_models"."name" = \'Aqqo\' limit 100 offset 0'],
    "Multiple grouped conditions" => ["(name eq 'Test' or name eq 'Aqqo') and (test gt 5 and test lt 20)", 'select * from "test_models" where (("test_models"."name" = \'Test\' or "test_models"."name" = \'Aqqo\') and ("test_models"."test" > \'5\' and "test_models"."test" < \'20\')) limit 100 offset 0'],
    "Boolean true comparison" => ["is_active eq true", 'select * from "test_models" where "test_models"."is_active" = \'1\' limit 100 offset 0'],
    "Boolean false comparison" => ["is_active eq false", 'select * from "test_models" where "test_models"."is_active" = \'0\' limit 100 offset 0'],
    "Not equal operator" => ["name ne 'Test'", 'select * from "test_models" where "test_models"."name" != \'Test\' limit 100 offset 0'],
    "Greater than or equal" => ["test ge 10", 'select * from "test_models" where "test_models"."test" >= \'10\' limit 100 offset 0'],
    "Less than or equal" => ["test le 10", 'select * from "test_models" where "test_models"."test" <= \'10\' limit 100 offset 0'],
    "Multiple string functions" => ["contains(name, 'Test') and startswith(name, 'T') and endswith(name, 't')", 'select * from "test_models" where ("test_models"."name" LIKE \'%Test%\') and ("test_models"."name" LIKE \'T%\') and ("test_models"."name" LIKE \'%t\') limit 100 offset 0'],
    "Any with multiple conditions" => ["relatedModels/any(s:s/name eq 'Aqqo' and s/cost gt 100)", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\' and "related_models"."cost" > \'100\') limit 100 offset 0'],
    "All with multiple conditions" => ["relatedModels/all(f:f/cost gt 10 and f/name ne 'Excluded')", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and ("related_models"."cost" <= \'10\' or "related_models"."name" = \'Excluded\')) limit 100 offset 0'],
    "Mixed any and all" => ["relatedModels/any(s:s/name eq 'Aqqo') and relatedModels/all(f:f/cost gt 10)", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') and not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') limit 100 offset 0'],
    "Complex nested groups" => ["((name eq 'A' or name eq 'B') and test gt 5) or (name eq 'C' and test lt 10)", 'select * from "test_models" where (("test_models"."name" = \'A\' or "test_models"."name" = \'B\') and "test_models"."test" > \'5\') or ("test_models"."name" = \'C\' and "test_models"."test" < \'10\') limit 100 offset 0'],
    "Null comparison" => ["name eq null", 'select * from "test_models" where "test_models"."name" is null limit 100 offset 0'],
    "Not null comparison" => ["name ne null", 'select * from "test_models" where "test_models"."name" is not null limit 100 offset 0'],
]);

// See https://docs.oasis-open.org/odata/odata/v4.01/odata-v4.01-part2-url-conventions.html#sec_PrimitiveLiterals

it('Handle date literals', function (?string $filter, string $result) {
    $query = createQueryFromParams(filter: $filter);
    expect($query->toSql())->toEqual($result);
})->with([
    "Date ge" => ["start_datetime_utc ge 2000-01-02", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2000-01-02\' limit 100 offset 0'],
    "Date lt" => ["start_datetime_utc lt 2000-01-02", 'select * from "test_models" where "test_models"."start_datetime_utc" < \'2000-01-02\' limit 100 offset 0'],
]);

it('Handle dateTimeOffset literals', function (?string $filter, string $result) {
    $query = createQueryFromParams(filter: $filter);
    expect($query->toSql())->toEqual($result);
})->with([
    "Timestamp ge" => ["start_datetime_utc ge 2000-01-02T03:04Z", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2000-01-02T03:04Z\' limit 100 offset 0'],
    "Timestamp lt" => ["start_datetime_utc lt 2000-01-02T03:04Z", 'select * from "test_models" where "test_models"."start_datetime_utc" < \'2000-01-02T03:04Z\' limit 100 offset 0'],
    "Timestamp ge - timezone +00:00" => ["start_datetime_utc ge 2000-01-02T03:04+00:00", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2000-01-02T03:04+00:00\' limit 100 offset 0'],
    "Timestamp ge - timezone +01:00" => ["start_datetime_utc ge 2000-01-02T03:04+01:00", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2000-01-02T03:04+01:00\' limit 100 offset 0'],
    "Timestamp ge - incl. seconds" => ["start_datetime_utc ge 2000-01-02T03:04:05Z", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2000-01-02T03:04:05Z\' limit 100 offset 0'],
    "Timestamp ge - incl. microseconds" => ["start_datetime_utc ge 2000-01-02T03:04:05.125Z", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2000-01-02T03:04:05.125Z\' limit 100 offset 0'],
    // Same as in 'Run filter', but now as dateTimeOffset literal (instead of string)
    "Grouped filter" => ["(start_datetime_utc gt 2024-05-13T06:00:00+00:00 or start_datetime_utc lt 2024-05-13T06:00:00+00:00) and end_datetime_utc lt 2024-05-19T15:00:00+00:00", 'select * from "test_models" where (("test_models"."start_datetime_utc" > \'2024-05-13T06:00:00+00:00\' or "test_models"."start_datetime_utc" < \'2024-05-13T06:00:00+00:00\') and "test_models"."end_datetime_utc" < \'2024-05-19T15:00:00+00:00\') limit 100 offset 0'],
    "Date range with and" => ["start_datetime_utc ge 2024-01-01 and start_datetime_utc le 2024-12-31", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2024-01-01\' and "test_models"."start_datetime_utc" <= \'2024-12-31\' limit 100 offset 0'],
    "Timestamp with negative timezone" => ["start_datetime_utc ge 2000-01-02T03:04-05:00", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2000-01-02T03:04-05:00\' limit 100 offset 0'],
]);

it('Handle numeric literals', function (?string $filter, string $result) {
    $query = createQueryFromParams(filter: $filter);
    expect($query->toSql())->toEqual($result);
})->with([
    "Integer comparison" => ["test eq 42", 'select * from "test_models" where "test_models"."test" = \'42\' limit 100 offset 0'],
    "Negative integer" => ["test gt -10", 'select * from "test_models" where "test_models"."test" > \'-10\' limit 100 offset 0'],
    "Decimal comparison" => ["price eq 19.99", 'select * from "test_models" where "test_models"."price" = \'19.99\' limit 100 offset 0'],
    "Large number" => ["test le 1000000", 'select * from "test_models" where "test_models"."test" <= \'1000000\' limit 100 offset 0'],
    "Zero comparison" => ["quantity eq 0", 'select * from "test_models" where "test_models"."quantity" = \'0\' limit 100 offset 0'],
]);

it('Handle combination filters', function (string $filter, string $result) {
    $query = createQueryFromParams(filter: $filter);
    expect($query->toSql())->toEqual($result);
})->with([
    "String function with boolean" => ["contains(name, 'Test') and is_active eq true", 'select * from "test_models" where (("test_models"."name" LIKE \'%Test%\') and ("test_models"."is_active" = \'1\')) limit 100 offset 0'],
    "Date with string filter" => ["start_datetime_utc ge 2024-01-01 and name eq 'Test'", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2024-01-01\' and "test_models"."name" = \'Test\' limit 100 offset 0'],
    "Any with contains" => ["relatedModels/any(s:contains(s/name, 'Aqqo'))", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and ("related_models"."name" LIKE \'%Aqqo%\')) limit 100 offset 0'],
    "All with startswith" => ["relatedModels/all(f:startswith(f/name, 'Test'))", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and ("related_models"."name" NOT LIKE \'Test%\')) limit 100 offset 0'],
    "IN with contains" => ["name in ('Test', 'Aqqo') and contains(tags, 'important')", 'select * from "test_models" where (("test_models"."name" IN (\'Test\', \'Aqqo\')) and ("test_models"."tags" LIKE \'%important%\')) limit 100 offset 0'],
    "Multiple any with or" => ["relatedModels/any(s:s/name eq 'Aqqo') or relatedModels/any(r:r/cost gt 100)", 'select * from "test_models" where (exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\')) or (exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" > \'100\')) limit 100 offset 0'],
]);
