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
    "Two filters with any filter" => ["name eq 'Aqqo' and relatedModel/any(s:s/name eq 'Aqqo')", 'select * from "test_models" where (("test_models"."name" = \'Aqqo\') and (exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\'))) limit 100 offset 0'],
    "Two filters with any filter but inversed" => ["relatedModels/any(s:s/name eq 'Aqqo') and name eq 'Aqqo'", 'select * from "test_models" where ((exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\')) and ("test_models"."name" = \'Aqqo\')) limit 100 offset 0'],
    "Simple all filter" => ["relatedModels/all(f:f/cost gt 10)", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') limit 100 offset 0'],
    "Two filters with all filter" => ["name eq 'Aqqo' and relatedModels/all(f:f/cost gt 10)", 'select * from "test_models" where (("test_models"."name" = \'Aqqo\') and (not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\'))) limit 100 offset 0'],
    "Two filters with all filter but inversed" => ["relatedModels/all(f:f/cost gt 10) and name eq 'Aqqo'", 'select * from "test_models" where ((not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\')) and ("test_models"."name" = \'Aqqo\')) limit 100 offset 0'],
    "Two filters with all filter but not expandable" => ["nonExistingModel/all(f:f/cost gt 10) and name eq 'Aqqo'", 'select * from "test_models" where (("test_models"."name" = \'Aqqo\')) limit 100 offset 0'],
    "0 literal" => ["id eq 0", 'select * from "test_models" where "test_models"."id" = \'0\' limit 100 offset 0'],
]);

// See https://docs.oasis-open.org/odata/odata/v4.01/odata-v4.01-part2-url-conventions.html#sec_PrimitiveLiterals

it('Handle date literals', function (?string $filter, string $result) {
    $query = createQueryFromParams(filter: $filter);
    expect($query->toSql())->toEqual($result);
})->with([
    "Date ge" => ["start_datetime_utc ge 2000-01-02", 'select * from "test_models" where "test_models"."start_datetime_utc" >= \'2000-01-02\' limit 100 offset 0'],
    "Date lt" => ["start_datetime_utc lt 2000-01-02", 'select * from "test_models" where "test_models"."start_datetime_utc" < \'2000-01-02\' limit 100 offset 0'],
]);

it('Handle boolean literals', function (?string $filter, string $result) {
    $query = createQueryFromParams(filter: $filter);
    expect($query->toSql())->toEqual($result);
})->with([
    "eq true" => ["is_visible eq true", 'select * from "test_models" where "test_models"."is_visible" = \'1\' limit 100 offset 0'],
    "eq false" => ["is_visible eq false", 'select * from "test_models" where "test_models"."is_visible" = \'0\' limit 100 offset 0'],
    "eq TRUE - uppercase" => ["is_visible eq TRUE", 'select * from "test_models" where "test_models"."is_visible" = \'1\' limit 100 offset 0'],
    "eq False - mixed case" => ["is_visible eq False", 'select * from "test_models" where "test_models"."is_visible" = \'0\' limit 100 offset 0'],
    "ne true" => ["is_visible ne true", 'select * from "test_models" where "test_models"."is_visible" != \'1\' limit 100 offset 0'],
    "ne false" => ["is_visible ne false", 'select * from "test_models" where "test_models"."is_visible" != \'0\' limit 100 offset 0'],
    "not ... eq true" => ["not is_visible eq true", 'select * from "test_models" where "test_models"."is_visible" != \'1\' limit 100 offset 0'],
    "not ... eq false" => ["not is_visible eq false", 'select * from "test_models" where "test_models"."is_visible" != \'0\' limit 100 offset 0'],
    "not ... ne true" => ["not is_visible ne true", 'select * from "test_models" where "test_models"."is_visible" = \'1\' limit 100 offset 0'],
    "eq 1 keeps working" => ["is_visible eq 1", 'select * from "test_models" where "test_models"."is_visible" = \'1\' limit 100 offset 0'],
    "eq 0 keeps working" => ["is_visible eq 0", 'select * from "test_models" where "test_models"."is_visible" = \'0\' limit 100 offset 0'],
    "IN operator with booleans" => ["is_visible in (true, false)", 'select * from "test_models" where "test_models"."is_visible" in (\'1\', \'0\') limit 100 offset 0'],
    "not IN operator with booleans" => ["not is_visible in (true, false)", 'select * from "test_models" where "test_models"."is_visible" not in (\'1\', \'0\') limit 100 offset 0'],
    "or not IN operator with booleans" => ["name eq 'Aqqo' or not is_visible in (true)", 'select * from "test_models" where (("test_models"."name" = \'Aqqo\') or ("test_models"."is_visible" not in (\'1\'))) limit 100 offset 0'],
    "Combined with another filter" => ["is_visible eq true and name eq 'Test'", 'select * from "test_models" where "test_models"."is_visible" = \'1\' and "test_models"."name" = \'Test\' limit 100 offset 0'],
    "Grouped with another filter" => ["(is_visible eq true or is_visible eq false) and name eq 'Test'", 'select * from "test_models" where (("test_models"."is_visible" = \'1\' or "test_models"."is_visible" = \'0\') and ("test_models"."name" = \'Test\')) limit 100 offset 0'],
    "Boolean next to a lambda" => ["relatedModels/any(s:s/name eq 'Aqqo') and is_visible eq true", 'select * from "test_models" where ((exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\')) and ("test_models"."is_visible" = \'1\')) limit 100 offset 0'],
    "Boolean inside an any lambda" => ["relatedModels/any(s:s/is_active eq true)", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."is_active" = \'1\') limit 100 offset 0'],
    "Boolean inside an all lambda" => ["relatedModels/all(s:s/is_active eq true)", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."is_active" != \'1\') limit 100 offset 0'],
    // The literals are only literals when unquoted - a quoted 'true' stays a string.
    "Quoted true stays a string" => ["name eq 'true'", 'select * from "test_models" where "test_models"."name" = \'true\' limit 100 offset 0'],
    "Quoted false stays a string" => ["name eq 'false'", 'select * from "test_models" where "test_models"."name" = \'false\' limit 100 offset 0'],
    "Quoted true inside contains" => ["contains(name, 'true')", 'select * from "test_models" where "test_models"."name" LIKE \'%true%\' limit 100 offset 0'],
    // An identifier that merely starts with `true` is still an identifier.
    "Identifier starting with true" => ["true_flag eq 'yes'", 'select * from "test_models" where "test_models"."true_flag" = \'yes\' limit 100 offset 0'],
]);

it('Handle negated lambdas', function (?string $filter, string $result) {
    $query = createQueryFromParams(filter: $filter);
    expect($query->toSql())->toEqual($result);
})->with([
    // `not relation/any(cond)` ≡ no related row matches ≡ whereDoesntHave(cond)
    "not any" => ["not relatedModels/any(s:s/name eq 'Aqqo')", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\') limit 100 offset 0'],
    // `not relation/all(cond)` ≡ some related row violates cond ≡ whereHas(¬cond)
    "not all" => ["not relatedModels/all(f:f/cost gt 10)", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."cost" <= \'10\') limit 100 offset 0'],
    "not any with a boolean literal" => ["not relatedModels/any(s:s/is_active eq true)", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."is_active" = \'1\') limit 100 offset 0'],
    "not all with a boolean literal" => ["not relatedModels/all(s:s/is_active eq true)", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."is_active" != \'1\') limit 100 offset 0'],
    "not any with in" => ["not relatedModels/any(s:s/name in ('Related1', 'Related2'))", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" in (\'Related1\', \'Related2\')) limit 100 offset 0'],
    "all with in" => ["relatedModels/all(s:s/is_active in (true))", 'select * from "test_models" where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."is_active" not in (\'1\')) limit 100 offset 0'],
    "not all with in" => ["not relatedModels/all(s:s/is_active in (true))", 'select * from "test_models" where exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."is_active" not in (\'1\')) limit 100 offset 0'],
    "not any combined with another filter" => ["name eq 'Aqqo' and not relatedModels/any(s:s/name eq 'Aqqo')", 'select * from "test_models" where (("test_models"."name" = \'Aqqo\') and (not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" = \'Aqqo\'))) limit 100 offset 0'],
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
    "Grouped filter" => ["(start_datetime_utc gt 2024-05-13T06:00:00+00:00 or start_datetime_utc lt 2024-05-13T06:00:00+00:00) and end_datetime_utc lt 2024-05-19T15:00:00+00:00", 'select * from "test_models" where (("test_models"."start_datetime_utc" > \'2024-05-13T06:00:00+00:00\' or "test_models"."start_datetime_utc" < \'2024-05-13T06:00:00+00:00\') and ("test_models"."end_datetime_utc" < \'2024-05-19T15:00:00+00:00\')) limit 100 offset 0'],
]);
