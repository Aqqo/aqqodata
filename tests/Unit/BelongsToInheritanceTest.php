<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Tests\Testclasses\ChildModelWithBelongsTo;
use Aqqo\OData\Tests\Testclasses\ParentModelWithBelongsTo;
use Aqqo\OData\Query;
use Illuminate\Http\Request;

describe('BelongsTo Relationship Inheritance', function () {
    
    it('verifies BelongsTo relationships are inherited from parent', function () {
        // Create a query for child model
        $query = Query::for(ChildModelWithBelongsTo::class);
        
        // Use reflection to test isPropertyExpandable directly
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('isPropertyExpandable');
        $method->setAccessible(true);
        
        // Test that parent BelongsTo relationship is recognized as expandable
        $belongsToResult = $method->invoke($query, 'parentModel');
        $hasManyResult = $method->invoke($query, 'childModels');
        
        // Both should be recognized - this is the key test
        expect($belongsToResult)->not()->toBeFalse()
            ->and($hasManyResult)->not()->toBeFalse();
    });

    it('compares parent and child model BelongsTo expandable relationships', function () {
        // Create queries for both parent and child
        $parentQuery = Query::for(ParentModelWithBelongsTo::class);
        $childQuery = Query::for(ChildModelWithBelongsTo::class);
        
        // Use reflection to access isPropertyExpandable
        $reflection = new \ReflectionClass($parentQuery);
        $method = $reflection->getMethod('isPropertyExpandable');
        $method->setAccessible(true);
        
        // Test that child recognizes the same BelongsTo relationship as parent
        $parentResult = $method->invoke($parentQuery, 'parentModel');
        $childResult = $method->invoke($childQuery, 'parentModel');
        
        // Both should recognize the BelongsTo relationship
        expect($parentResult)->not()->toBeFalse()
            ->and($childResult)->not()->toBeFalse()
            ->and($childResult)->toBe($parentResult); // Should return the same relationship name
    });

    it('can expand BelongsTo relationship from child model', function () {
        // Test that we can actually use the inherited BelongsTo in expand
        $query = Query::for(ChildModelWithBelongsTo::class, new Request([
            '$expand' => 'parentModel'
        ]));
        
        // Should not throw an exception - the BelongsTo relationship should be recognized
        expect($query->toSql())->toBeString();
    });

    it('can use BelongsTo relationship in filter from child model', function () {
        // Test that we can use inherited BelongsTo in filter expressions
        // Using proper OData syntax for relationship filtering with any
        $query = Query::for(ChildModelWithBelongsTo::class, new Request([
            '$filter' => "parentModel/any(p:p/id eq 1)"
        ]));
        
        // Should not throw an exception - BelongsTo should be recognized as expandable
        expect($query->toSql())->toBeString();
    });
});
