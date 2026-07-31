<?php

namespace Aqqo\OData\Tests\Feature;

it('Runs orderby', function (?string $orderby, string $result) {
    $query = createQueryFromParams(orderby: $orderby);
    expect($query->toSql())->toEqual($result);
})->with([
    "Without orderby" => ["", 'select * from "test_models" limit 100 offset 0'],
    "Order by test1 asc" => ["test1 asc", 'select * from "test_models" order by "test1" asc limit 100 offset 0'],
    "Order by test1 desc" => ["test1 desc", 'select * from "test_models" order by "test1" desc limit 100 offset 0'],
    "Order by test1 asc, test2 asc" => ["test1 asc, test2 asc", 'select * from "test_models" order by "test1" asc, "test2" asc limit 100 offset 0'],
    "Order by test1 asc, test2 desc" => ["test1 asc, test2 desc", 'select * from "test_models" order by "test1" asc, "test2" desc limit 100 offset 0'],
    "Order by test1 desc, test2 asc" => ["test1 desc, test2 asc", 'select * from "test_models" order by "test1" desc, "test2" asc limit 100 offset 0'],
    "Order by test1 desc, test2 desc" => ["test1 desc, test2 desc", 'select * from "test_models" order by "test1" desc, "test2" desc limit 100 offset 0'],
]);



it('Maps orderby property aliases to their database columns', function () {
    $query = createQueryFromParams(orderby: 'odatacol desc');
    expect($query->toSql())->toEqual('select * from "test_models" order by "dbcol" desc limit 100 offset 0');
});

it('Throws on unknown orderby property in strict mode', function () {
    $request = new \Illuminate\Http\Request(['$orderby' => 'nonexistent asc']);
    \Aqqo\OData\Query::for(\Aqqo\OData\Tests\Testclasses\TestModel::class, $request, strict: true);
})->throws(\Aqqo\OData\Exceptions\QueryException::class);

it('Throws on unknown filter property in strict mode', function () {
    $request = new \Illuminate\Http\Request(['$filter' => "nonexistent eq 'x'"]);
    \Aqqo\OData\Query::for(\Aqqo\OData\Tests\Testclasses\TestModel::class, $request, strict: true);
})->throws(\Aqqo\OData\Exceptions\QueryException::class);

it('Keeps silently skipping unknown filter properties outside strict mode', function () {
    $query = createQueryFromParams(filter: "nonexistent eq 'x'");
    expect($query->toSql())->toEqual('select * from "test_models" limit 100 offset 0');
});
