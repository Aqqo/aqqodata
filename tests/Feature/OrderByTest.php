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

it('Runs orderby in expand', function () {
    $query = createQueryFromParams(expand: 'RelatedModel($orderby=name asc)');
    $sql = $query->toSql();
    
    // Debug: Let's see what the actual SQL looks like
    dump($sql);
    
    // The SQL should contain an ORDER BY clause for the related model
    expect($sql)->toContain('order by "name" asc');
});


