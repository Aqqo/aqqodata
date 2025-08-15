<?php

require_once 'vendor/autoload.php';

use Aqqo\OData\Filters\FilterExample;

// Run the examples
$example = new FilterExample();
$example->runAllExamples();

echo "\n=== Testing Complex Nested Filters ===\n";

// Test the complex nested filter you mentioned: ((name eq 'test') OR (blabla))
use Aqqo\OData\Filters\FilterParser;

$parser = new FilterParser();

$complexFilter = "((name eq 'test') OR (age gt 18 and status eq 'active'))";
$filter = $parser->parse($complexFilter);

echo "Original: {$complexFilter}\n";
echo "Parsed: " . $filter->toString() . "\n";

// Test with FilterBuilder
use Aqqo\OData\Filters\FilterBuilder;

$builtFilter = FilterBuilder::create()
    ->or(function($builder) {
        $builder->eq('name', 'test')
                ->and(function($builder) {
                    $builder->gt('age', 18)
                            ->eq('status', 'active');
                });
    })
    ->build();

echo "Built: " . $builtFilter->toString() . "\n";

echo "\n=== Testing Relationship Filters ===\n";

$relationshipFilter = "any(orders, total gt 100) and all(reviews, rating ge 4)";
$parsedRelationship = $parser->parse($relationshipFilter);

echo "Original: {$relationshipFilter}\n";
echo "Parsed: " . $parsedRelationship->toString() . "\n";

echo "\n=== Testing Function Filters ===\n";

$functionFilter = "contains(name, 'john') and startswith(email, 'j') and endswith(domain, '.com')";
$parsedFunction = $parser->parse($functionFilter);

echo "Original: {$functionFilter}\n";
echo "Parsed: " . $parsedFunction->toString() . "\n";

echo "\n=== Testing Mixed Complex Filters ===\n";

$mixedFilter = "((name eq 'john' or name eq 'jane') and age gt 18) or (status eq 'active' and any(orders, total gt 100))";
$parsedMixed = $parser->parse($mixedFilter);

echo "Original: {$mixedFilter}\n";
echo "Parsed: " . $parsedMixed->toString() . "\n";

echo "\n=== Filter System Test Complete ===\n";
