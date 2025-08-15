<?php

namespace Aqqo\OData\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Example usage of the new filter system
 */
class FilterExample
{
    /**
     * Example 1: Simple filters
     */
    public function simpleFilters(): void
    {
        // Using FilterBuilder
        $filter = FilterBuilder::create()
            ->eq('name', 'John')
            ->gt('age', 18)
            ->in('status', ['active', 'pending'])
            ->build();

        echo "Simple filter: " . $filter->toString() . "\n";
        // Output: (name = 'John' and age > '18' and status IN ('active', 'pending'))
    }

    /**
     * Example 2: Complex nested filters
     */
    public function nestedFilters(): void
    {
        $filter = FilterBuilder::create()
            ->and(function($builder) {
                $builder->eq('status', 'active')
                        ->or(function($builder) {
                            $builder->contains('name', 'john')
                                    ->contains('name', 'jane');
                        });
            })
            ->gt('age', 18)
            ->build();

        echo "Nested filter: " . $filter->toString() . "\n";
        // Output: ((status = 'active' and (contains(name, 'john') or contains(name, 'jane'))) and age > '18')
    }

    /**
     * Example 3: Relationship filters
     */
    public function relationshipFilters(): void
    {
        $filter = FilterBuilder::create()
            ->eq('status', 'active')
            ->any('orders', function($builder) {
                $builder->gt('total', 100)
                        ->eq('status', 'completed');
            })
            ->all('reviews', function($builder) {
                $builder->ge('rating', 4);
            })
            ->build();

        echo "Relationship filter: " . $filter->toString() . "\n";
        // Output: (status = 'active' and any(orders, (total > '100' and status = 'completed')) and all(reviews, rating >= '4'))
    }

    /**
     * Example 4: Function filters
     */
    public function functionFilters(): void
    {
        $filter = FilterBuilder::create()
            ->contains('description', 'important')
            ->startsWith('name', 'j')
            ->endsWith('email', '@example.com')
            ->build();

        echo "Function filter: " . $filter->toString() . "\n";
        // Output: (contains(description, 'important') and startswith(name, 'j') and endswith(email, '@example.com'))
    }

    /**
     * Example 5: Parsing OData filter strings
     */
    public function parseODataString(): void
    {
        $parser = new FilterParser();
        
        $filterString = "(name eq 'test') OR (age gt 18 and status eq 'active')";
        $filter = $parser->parse($filterString);
        
        echo "Parsed filter: " . $filter->toString() . "\n";
        // Output: ((name = 'test') or (age > '18' and status = 'active'))
    }

    /**
     * Example 6: Complex nested OData parsing
     */
    public function complexODataParsing(): void
    {
        $parser = new FilterParser();
        
        $filterString = "((name eq 'john') OR (name eq 'jane')) AND (age gt 18) AND any(orders, total gt 100)";
        $filter = $parser->parse($filterString);
        
        echo "Complex parsed filter: " . $filter->toString() . "\n";
        // Output: (((name = 'john') or (name = 'jane')) and age > '18' and any(orders, total > '100'))
    }

    /**
     * Example 7: Direct filter object creation
     */
    public function directFilterCreation(): void
    {
        // Create filters directly
        $nameFilter = new SimpleFilter('name', '=', 'John');
        $ageFilter = new SimpleFilter('age', '>', 18);
        
        // Create logical filter
        $logicalFilter = new LogicalFilter('and');
        $logicalFilter->addFilter($nameFilter);
        $logicalFilter->addFilter($ageFilter);
        
        echo "Direct filter: " . $logicalFilter->toString() . "\n";
        // Output: (name = 'John' and age > '18')
    }

    /**
     * Example 8: Mixed approach
     */
    public function mixedApproach(): void
    {
        // Start with a parsed filter
        $parser = new FilterParser();
        $baseFilter = $parser->parse("status eq 'active'");
        
        // Add more conditions programmatically
        $finalFilter = FilterBuilder::create()
            ->add($baseFilter)
            ->gt('age', 18)
            ->contains('description', 'important')
            ->build();
        
        echo "Mixed filter: " . $finalFilter->toString() . "\n";
        // Output: (status = 'active' and age > '18' and contains(description, 'important'))
    }

    /**
     * Example 9: Real-world scenario
     */
    public function realWorldScenario(): void
    {
        // Simulate a user search with multiple criteria
        $filter = FilterBuilder::create()
            ->and(function($builder) {
                $builder->or(function($builder) {
                    $builder->contains('name', 'john')
                            ->contains('email', 'john');
                })
                ->or(function($builder) {
                    $builder->eq('status', 'active')
                            ->eq('status', 'pending');
                });
            })
            ->gt('age', 18)
            ->any('orders', function($builder) {
                $builder->gt('total', 50)
                        ->eq('status', 'completed');
            })
            ->build();
        
        echo "Real-world filter: " . $filter->toString() . "\n";
        // Output: (((contains(name, 'john') or contains(email, 'john')) and (status = 'active' or status = 'pending')) and age > '18' and any(orders, (total > '50' and status = 'completed')))
    }

    /**
     * Run all examples
     */
    public function runAllExamples(): void
    {
        echo "=== Filter System Examples ===\n\n";
        
        $this->simpleFilters();
        echo "\n";
        
        $this->nestedFilters();
        echo "\n";
        
        $this->relationshipFilters();
        echo "\n";
        
        $this->functionFilters();
        echo "\n";
        
        $this->parseODataString();
        echo "\n";
        
        $this->complexODataParsing();
        echo "\n";
        
        $this->directFilterCreation();
        echo "\n";
        
        $this->mixedApproach();
        echo "\n";
        
        $this->realWorldScenario();
        echo "\n";
    }
}
