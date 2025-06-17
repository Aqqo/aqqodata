<?php

namespace Aqqo\OData\Tests\Feature;


it('handles no search', function (string|null $search, string $result) {
    $query = createQueryFromParams(search: $search);
    expect($query->toSql())->toEqual($result);
})->with([
    "No search" => ['', 'select * from "test_models" limit 100 offset 0'],
    "Single word search" => ["hans", 'select * from "test_models" where (("name" LIKE \'%hans%\' or "description" LIKE \'%hans%\')) limit 100 offset 0'],
    "Double word not exact search" => ["hans greta", 'select * from "test_models" where (("name" LIKE \'%hans%\' or "description" LIKE \'%hans%\') or ("name" LIKE \'%greta%\' or "description" LIKE \'%greta%\')) limit 100 offset 0'],
    "Double word exact search" => ["\"hans greta\"", 'select * from "test_models" where (("name" LIKE \'%hans greta%\' or "description" LIKE \'%hans greta%\')) limit 100 offset 0'],
    "Double word exact search with or" => ["\"hans greta\" peter", 'select * from "test_models" where (("name" LIKE \'%hans greta%\' or "description" LIKE \'%hans greta%\') or ("name" LIKE \'%peter%\' or "description" LIKE \'%peter%\')) limit 100 offset 0'],

    // Additional Test Cases
    "Exclusion (NOT) operator" => ["hans NOT greta", 'select * from "test_models" where (("name" LIKE \'%hans%\' or "description" LIKE \'%hans%\')) and ("name" NOT LIKE \'%greta%\' and "description" NOT LIKE \'%greta%\') limit 100 offset 0'],
    "Multiple exclusions (NOT)" => ["hans NOT greta NOT john", 'select * from "test_models" where (("name" LIKE \'%hans%\' or "description" LIKE \'%hans%\')) and ("name" NOT LIKE \'%greta%\' and "description" NOT LIKE \'%greta%\') and ("name" NOT LIKE \'%john%\' and "description" NOT LIKE \'%john%\') limit 100 offset 0'],
    "Wildcard search" => ["han*", 'select * from "test_models" where (("name" LIKE \'han%\' or "description" LIKE \'han%\')) limit 100 offset 0'],
    "Exact phrase with wildcard" => ["\"hans greta\" han*", 'select * from "test_models" where (("name" LIKE \'%hans greta%\' or "description" LIKE \'%hans greta%\') or ("name" LIKE \'han%\' or "description" LIKE \'han%\')) limit 100 offset 0'],
    "Multiple exact phrases" => ["\"hans greta\" \"john doe\"", 'select * from "test_models" where (("name" LIKE \'%hans greta%\' or "description" LIKE \'%hans greta%\') or ("name" LIKE \'%john doe%\' or "description" LIKE \'%john doe%\')) limit 100 offset 0'],
    "Wildcard with multiple terms" => ["han* greta*", 'select * from "test_models" where (("name" LIKE \'han%\' or "description" LIKE \'han%\') or ("name" LIKE \'greta%\' or "description" LIKE \'greta%\')) limit 100 offset 0'],
    "Combination of exact phrase and exclusion" => ["\"hans greta\" NOT john", 'select * from "test_models" where (("name" LIKE \'%hans greta%\' or "description" LIKE \'%hans greta%\')) and ("name" NOT LIKE \'%john%\' and "description" NOT LIKE \'%john%\') limit 100 offset 0'],
    "Multiple wildcards" => ["han* piet*", 'select * from "test_models" where (("name" LIKE \'han%\' or "description" LIKE \'han%\') or ("name" LIKE \'piet%\' or "description" LIKE \'piet%\')) limit 100 offset 0'],
    "Exact phrase with multiple terms" => ["\"hans greta\" peter", 'select * from "test_models" where (("name" LIKE \'%hans greta%\' or "description" LIKE \'%hans greta%\') or ("name" LIKE \'%peter%\' or "description" LIKE \'%peter%\')) limit 100 offset 0'],
    "Search with special characters and wildcards" => ["hans & greta*", 'select * from "test_models" where (("name" LIKE \'%hans%\' or "description" LIKE \'%hans%\') or ("name" LIKE \'%&%\' or "description" LIKE \'%&%\') or ("name" LIKE \'greta%\' or "description" LIKE \'greta%\')) limit 100 offset 0'],
]);
