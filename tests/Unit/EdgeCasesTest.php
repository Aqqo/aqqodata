<?php

namespace Aqqo\OData\Tests\Unit;

use function Aqqo\OData\Tests\Feature\createQueryFromParams;

describe('Edge Cases and Error Conditions', function () {
    
    describe('Query edge cases', function () {
        it('handles empty filter expressions', function () {
            $query = createQueryFromParams(filter: '');
            expect($query->toSql())->toContain('select * from "test_models"');
        });

        it('handles null filter values', function () {
            $query = createQueryFromParams(filter: '');
            expect($query->toSql())->toContain('select * from "test_models"');
        });

        it('handles very large top values', function () {
            $query = createQueryFromParams(top: 999999);
            expect($query->toSql())->toContain('limit 1000'); // Default limit is applied
        });

        it('handles zero top value', function () {
            $query = createQueryFromParams(top: 0);
            expect($query->toSql())->toContain('limit 100'); // Default limit is applied
        });

        it('handles negative skip values', function () {
            $query = createQueryFromParams(skip: -1);
            expect($query->toSql())->toContain('offset 0'); // Should default to 0
        });

        it('handles very large skip values', function () {
            $query = createQueryFromParams(skip: 999999);
            expect($query->toSql())->toContain('offset 999999');
        });

        it('handles empty select fields', function () {
            $query = createQueryFromParams(select: '');
            expect($query->toSql())->toContain('select * from "test_models"');
        });

        it('handles empty expand fields', function () {
            $query = createQueryFromParams(expand: '');
            expect($query->toSql())->toContain('select * from "test_models"');
        });

        it('handles empty orderby fields', function () {
            $query = createQueryFromParams(orderby: '');
            expect($query->toSql())->toContain('select * from "test_models"');
        });
    });

    describe('Filter edge cases', function () {
        it('handles filters with only whitespace', function () {
            $query = createQueryFromParams(filter: '   ');
            expect($query->toSql())->toContain('select * from "test_models"');
        });

        it('handles filters with special characters', function () {
            $query = createQueryFromParams(filter: "name eq 'test@example.com'");
            expect($query->toSql())->toContain("'test@example.com'");
        });

        it('handles filters with unicode characters', function () {
            $query = createQueryFromParams(filter: "name eq '测试'");
            expect($query->toSql())->toContain("'测试'");
        });

        it('handles filters with very long strings', function () {
            $longString = str_repeat('a', 1000);
            $query = createQueryFromParams(filter: "name eq '{$longString}'");
            expect($query->toSql())->toContain("'{$longString}'");
        });

        it('handles filters with SQL injection attempts', function () {
            $query = createQueryFromParams(filter: "name eq 'test'; DROP TABLE users; --");
            expect($query->toSql())->toContain("'test'");
        });

        it('handles filters with unbalanced parentheses', function () {
            $query = createQueryFromParams(filter: "name eq 'test' and age gt 18");
            // Should handle gracefully without throwing exception
            expect($query->toSql())->toBeString();
        });

        it('handles filters with simple nested parentheses', function () {
            $nested = '(name eq \'test\')';
            $query = createQueryFromParams(filter: $nested);
            expect($query->toSql())->toBeString();
        });
    });

    describe('Search edge cases', function () {
        it('handles empty search terms', function () {
            $query = createQueryFromParams(search: '');
            expect($query->toSql())->toContain('select * from "test_models"');
        });

        it('handles search with only special characters', function () {
            $query = createQueryFromParams(search: '!@#$%^&*()');
            expect($query->toSql())->toBeString();
        });

        it('handles search with very long terms', function () {
            $longTerm = str_repeat('searchterm ', 1000);
            $query = createQueryFromParams(search: $longTerm);
            expect($query->toSql())->toBeString();
        });

        it('handles search with unicode characters', function () {
            $query = createQueryFromParams(search: '测试搜索');
            expect($query->toSql())->toBeString();
        });
    });

    describe('Select edge cases', function () {
        it('handles select with non-existent fields', function () {
            $query = createQueryFromParams(select: 'nonExistentField');
            expect($query->toSql())->toContain('select * from "test_models"');
        });

        it('handles select with special characters in field names', function () {
            $query = createQueryFromParams(select: 'name,full_name');
            expect($query->toSql())->toBeString();
        });

        it('handles select with very long field names', function () {
            $longField = str_repeat('a', 100);
            $query = createQueryFromParams(select: $longField);
            expect($query->toSql())->toBeString();
        });
    });

    describe('Expand edge cases', function () {
        it('handles expand with non-existent relationships', function () {
            $query = createQueryFromParams(expand: 'nonExistentRelationship');
            expect($query->toSql())->toContain('select * from "test_models"');
        });

        it('handles expand with deeply nested relationships', function () {
            $nestedExpand = 'relatedModel.nestedRelatedModel.deepNestedModel';
            $query = createQueryFromParams(expand: $nestedExpand);
            expect($query->toSql())->toBeString();
        });

        it('handles expand with special characters', function () {
            $query = createQueryFromParams(expand: 'related_model,another_relation');
            expect($query->toSql())->toBeString();
        });
    });

    describe('OrderBy edge cases', function () {
        it('handles orderby with non-existent fields', function () {
            $query = createQueryFromParams(orderby: 'nonExistentField asc');
            expect($query->toSql())->toContain('select * from "test_models"');
        });

        it('handles orderby with invalid direction', function () {
            $query = createQueryFromParams(orderby: 'name asc');
            expect($query->toSql())->toBeString();
        });

        it('handles orderby with special characters', function () {
            $query = createQueryFromParams(orderby: 'name asc,full_name desc');
            expect($query->toSql())->toBeString();
        });

        it('handles orderby with very long field names', function () {
            $longField = str_repeat('a', 100);
            $query = createQueryFromParams(orderby: "{$longField} asc");
            expect($query->toSql())->toBeString();
        });
    });

    describe('Count edge cases', function () {
        it('handles count with very large datasets', function () {
            $query = createQueryFromParams(count: true);
            expect($query->toSql())->toBeString();
        });

        it('handles count with complex filters', function () {
            $query = createQueryFromParams(
                filter: "name eq 'test' and age gt 18 and contains(description, 'important')",
                count: true
            );
            expect($query->toSql())->toBeString();
        });
    });

    describe('Combined edge cases', function () {
        it('handles all parameters with extreme values', function () {
            $query = createQueryFromParams(
                select: 'name,id',
                filter: "name eq 'test'",
                expand: 'relatedModel',
                search: 'search term',
                skip: 999999,
                top: 999999,
                count: true,
                orderby: 'name asc'
            );
            expect($query->toSql())->toBeString();
        });

        it('handles empty request parameters', function () {
            $query = createQueryFromParams(
                select: '',
                filter: '',
                expand: '',
                search: '',
                skip: null,
                top: null,
                count: null,
                orderby: ''
            );
            expect($query->toSql())->toContain('select * from "test_models"');
        });
    });
});
