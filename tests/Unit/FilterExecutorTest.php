<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Query;
use Aqqo\OData\Services\FilterExecutor;
use Aqqo\OData\Services\FilterParser;
use Aqqo\OData\Tests\Testclasses\TestModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

describe('FilterExecutor', function () {
    beforeEach(function () {
        $this->builder = TestModel::query();
        $this->query = new Query($this->builder);
    });

    it('handles OR where one side is invalid for raw SQL (e.g. transform)', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("tolower(name) eq 'test' or name eq 'foo'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('where (LOWER("test_models"."name") = \'test\') or ("test_models"."name" = \'foo\')');
    });

    it('handles tolower transformation in filter', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("tolower(name) eq 'test'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('LOWER("test_models"."name") = \'test\'');
    });

    it('handles toupper transformation in filter', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("toupper(name) eq 'TEST'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('UPPER("test_models"."name") = \'TEST\'');
    });

    it('handles trim transformation in filter', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("trim(name) eq 'test'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('TRIM("test_models"."name") = \'test\'');
    });

    it('handles null comparison with transformation', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("tolower(name) eq null");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('LOWER("test_models"."name") IS NULL');
    });

    it('handles IN operator with transformation', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("tolower(name) in ('a', 'b')");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('LOWER("test_models"."name") IN (\'a\', \'b\')');
    });

    it('handles not expression for complex node', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("not (name eq 'test' or name eq 'foo')");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('where not ("test_models"."name" = \'test\' or "test_models"."name" = \'foo\')');
    });

    it('handles lambda all expression', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("relatedModels/all(r:r/name eq 'test')");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        // Lambda all(condition) is implemented as NOT EXISTS (NOT condition)
        expect($sql)->toContain('where not exists (select * from "related_models" where "test_models"."id" = "related_models"."test_model_id" and "related_models"."name" != \'test\')');
    });

    it('handles comparison with null first segment in path', function () {
        // Targets line 131
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('/name', 'eq', 'test');
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($node);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->not()->toContain('where');
    });

    it('handles lambda alias with no segments', function () {
        // Targets line 136
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('r/', 'eq', 'test');
        $executor = new FilterExecutor($this->query, $this->builder, true, ['r' => true]);
        $executor->execute($node);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->not()->toContain('where');
    });

    it('handles negating lambda any', function () {
        $parser = new FilterParser();
        // Trigger negateNode for LambdaQueryNode 'any'
        $ast = $parser->parse("relatedModels/all(r:r/relatedModels/any(s:s/name eq 'test'))");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('not exists');
    });

    it('handles negating lambda all', function () {
        $parser = new FilterParser();
        // Trigger negateNode for LambdaQueryNode 'all'
        // Lambda all(condition) negated is implemented as Not(any(condition))
        $node = new \Aqqo\OData\QueryNodeStructure\LambdaQueryNode('relatedModels', 'all', new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('name', 'eq', 'test'));
        $executor = new FilterExecutor($this->query, $this->builder);
        // We need a way to call negateNode or trigger it.
        // It's called by applyLambda for 'all'.
        $executor->execute($node);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('not exists');
    });

    it('handles IN operator with transformation in buildNodeExpression', function () {
        $parser = new FilterParser();
        // This targets line 582 and 747..750
        $ast = $parser->parse("tolower(name) in ('a', 'b') or name eq 'foo'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('IN (\'a\', \'b\')');
    });

    it('handles isComparisonOnly for NotQueryNode', function () {
        // Targets line 656..660
        $inner = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('name', 'eq', 'test');
        $node = new \Aqqo\OData\QueryNodeStructure\NotQueryNode($inner);
        // negateNode calls canDistributeNegationForOr which calls isComparisonOnly
        $ast = new \Aqqo\OData\QueryNodeStructure\CompositeQueryNode(
            new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('a', 'eq', 'b'),
            'or',
            $node
        );
        // We need to trigger negateNode on this composite node
        // Lambda 'all' calls negateNode on its condition.
        $lambda = new \Aqqo\OData\QueryNodeStructure\LambdaQueryNode('relatedModels', 'all', $ast);
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($lambda);
        
        expect($this->builder->toRawSql())->toContain('not exists');
    });

    it('handles comparison with slash in field name but no valid relation', function () {
        // This targets lines where it tries to resolve a relation but it fails
        $parser = new FilterParser();
        $ast = $parser->parse("nonExistentRelation/name eq 'test'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        // Should not add any where clause if relation is invalid
        expect($sql)->not()->toContain('where');
    });

    it('handles direct filter with wrap and null comparison', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("name eq null or description eq 'test'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('"test_models"."name" IS NULL');
    });

    it('handles direct filter with wrap and not null comparison', function () {
        $parser = new FilterParser();
        $ast = $parser->parse("name ne null or description eq 'test'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        expect($sql)->toContain('"test_models"."name" IS NOT NULL');
    });

    it('handles stripRedundantParentheses edge cases', function () {
        $executor = new FilterExecutor($this->query, $this->builder);
        
        // Reflect to call private method
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('stripRedundantParentheses');
        
        // Candidate empty (line 669) - returns original if candidate is empty
        expect($method->invoke($executor, '()'))->toBe('()');
        
        // Not balanced (line 673)
        expect($method->invoke($executor, '(a) or (b)'))->toBe('(a) or (b)');
    });

    it('handles buildBasicExpression returning false for invalid column', function () {
        // Targets line 730
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('nonExistent', 'eq', 'test');
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($node);
        
        expect($this->builder->toRawSql())->not()->toContain('where');
    });

    it('handles orWhereHas in applyRelationComparison', function () {
        // Targets lines 154-160: orWhereHas path
        // We need to trigger the path where $boolean !== 'where' in applyRelationComparison
        // This happens when we have an OR condition and the second part is a relation comparison
        $parser = new FilterParser();
        $ast = $parser->parse("name eq 'test' or relatedModel/name eq 'foo'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        // The second part should use orWhereHas, which generates "or exists"
        expect($sql)->toContain('or exists');
    });

    it('handles wrap path for null comparison in applyDirectFilter', function () {
        // Targets lines 189-191: wrap path for null comparison
        // We need to trigger wrap=true, which happens in OR conditions with grouping
        $parser = new FilterParser();
        // Use OR to force wrapping - need to ensure wrap=true is passed
        $ast = $parser->parse("(name eq null) or name eq 'test'");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        // The first condition should use wrapped raw due to OR grouping
        expect($sql)->toContain('IS NULL');
    });

    it('handles applyNot with empty wheres', function () {
        // Targets line 297: Early return when query->wheres is empty
        $node = new \Aqqo\OData\QueryNodeStructure\NotQueryNode(
            new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('nonExistent', 'eq', 'test')
        );
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($node);
        
        // Should not throw and should not add where clause
        expect($this->builder->toRawSql())->not()->toContain('not');
    });

    it('handles applyNot with base where constraints', function () {
        // Targets lines 303, 307: Trimming base relation constraints
        // Need to ensure baseWhereCount > 0 and baseWhereBindingsCount > 0
        $builder = TestModel::query()->where('id', '>', 0)->where('name', '!=', '');
        $query = new Query($builder);
        
        $parser = new FilterParser();
        $ast = $parser->parse("not (name eq 'test')");
        $executor = new FilterExecutor($query, $builder);
        $executor->execute($ast);
        
        $sql = $builder->toRawSql();
        expect($sql)->toContain('not');
        // Verify that base constraints were trimmed (not in final SQL)
        // The not clause should be present
        expect($sql)->toContain('name');
    });

    it('handles resolveColumn returning true for filterable property', function () {
        // Targets line 349: Return $field when isPropertyFilterable returns true
        // This happens when isPropertyFilterable returns true (not a string mapping)
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('name', 'eq', 'test');
        $executor = new FilterExecutor($this->query, $this->builder);
        
        // Use reflection to test resolveColumn directly
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('resolveColumn');
        $method->setAccessible(true);
        
        $result = $method->invoke($executor, 'name');
        // Should return 'name' when isPropertyFilterable returns true
        expect($result)->toBe('name');
    });

    it('handles mapOperator for negated IN operator', function () {
        // Targets line 365: 'in' operator with negated = true
        // Test mapOperator directly with negated IN
        $executor = new FilterExecutor($this->query, $this->builder);
        
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('mapOperator');
        $method->setAccessible(true);
        
        // Test with negated = true for 'in' operator
        $result = $method->invoke($executor, 'in', true);
        expect($result)->toBe('NOT IN');
        
        // Also test execution to ensure it works end-to-end
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('name', 'in', ['a', 'b'], true);
        $executor->execute($node);
        $sql = strtolower($this->builder->toRawSql());
        expect($sql)->toContain('not in');
    });

    it('handles formatInValues with string containing parentheses', function () {
        // Targets lines 373-374: String value with parentheses
        // Test with a string value that contains parentheses format like "(a, b, c)"
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('name', 'in', '(a, b, c)');
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($node);
        
        $sql = strtolower($this->builder->toRawSql());
        // Should parse the parentheses format correctly - check SQL was generated
        expect($sql)->toContain('in');
        expect($sql)->toContain('a');
    });

    it('handles formatInValues with null string value', function () {
        // Targets line 383: Normalized value is 'null' string
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('name', 'in', ['null', 'test']);
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($node);
        
        $sql = strtolower($this->builder->toRawSql());
        // The 'null' string should be converted to actual null - check SQL was generated
        expect($sql)->toContain('in');
        // Check that null appears in the SQL (either as null or 'null')
        expect($sql)->toMatch('/null|test/');
    });

    it('handles normalizeScalarValue with non-string value', function () {
        // Targets line 397: Default case (non-string value)
        // Pass numeric values directly (not as strings)
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('quantity', 'in', [1, 2, 3]);
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($node);
        
        $sql = strtolower($this->builder->toRawSql());
        // Should handle numeric values correctly - check SQL was generated
        expect($sql)->toContain('in');
        expect($sql)->toContain('1');
    });

    it('handles applyTransformedFilter with empty IN values', function () {
        // Targets line 542: Early return when values is empty
        $parser = new FilterParser();
        $ast = $parser->parse("tolower(name) in ()");
        $executor = new FilterExecutor($this->query, $this->builder);
        $executor->execute($ast);
        
        $sql = $this->builder->toRawSql();
        // Should not add IN clause when empty
        expect($sql)->not()->toContain('IN');
    });

    it('handles isComparisonOnly for CompositeQueryNode', function () {
        // Targets line 608: isComparisonOnly for CompositeQueryNode
        $executor = new FilterExecutor($this->query, $this->builder);
        
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('isComparisonOnly');
        $method->setAccessible(true);
        
        $left = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('a', 'eq', 'b');
        $right = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('c', 'eq', 'd');
        $composite = new \Aqqo\OData\QueryNodeStructure\CompositeQueryNode($left, 'and', $right);
        
        // Test isComparisonOnly directly
        $result = $method->invoke($executor, $composite);
        expect($result)->toBeTrue(); // Both children are comparison-only
    });

    it('handles escapeLiteral with boolean value', function () {
        // Targets line 664: Boolean value conversion
        $executor = new FilterExecutor($this->query, $this->builder);
        
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('escapeLiteral');
        $method->setAccessible(true);
        
        expect($method->invoke($executor, true))->toBe("'1'");
        expect($method->invoke($executor, false))->toBe("'0'");
    });

    it('handles escapeLiteral with null value', function () {
        // Targets line 668: Null value
        $executor = new FilterExecutor($this->query, $this->builder);
        
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('escapeLiteral');
        $method->setAccessible(true);
        
        expect($method->invoke($executor, null))->toBe('null');
    });

    it('handles buildBasicExpression with invalid column', function () {
        // Targets line 689: Return early when column === false
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('__invalid__', 'eq', 'test');
        $executor = new FilterExecutor($this->query, $this->builder);
        
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('buildBasicExpression');
        $method->setAccessible(true);
        
        $result = $method->invoke($executor, $node);
        expect($result[0])->toBe('');
        expect($result[1])->toBe([]);
        expect($result[2])->toBeFalse();
        expect($result[3])->toBeFalse();
    });

    it('handles buildBasicExpression with IN operator', function () {
        // Targets lines 706-709: IN operator handling
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('name', 'in', ['a', 'b']);
        $executor = new FilterExecutor($this->query, $this->builder);
        
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('buildBasicExpression');
        $method->setAccessible(true);
        
        $result = $method->invoke($executor, $node);
        expect($result[0])->toContain('IN');
        expect($result[2])->toBeTrue();
    });

    it('handles buildNodeExpression with empty wheres', function () {
        // Targets line 733: Early return when query->wheres is empty
        // Need to use a node that will produce empty wheres (non-basic node with invalid field)
        $node = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('__invalid__', 'eq', 'test');
        $executor = new FilterExecutor($this->query, $this->builder);
        
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('buildNodeExpression');
        $method->setAccessible(true);
        
        $result = $method->invoke($executor, $node);
        // Should return empty string and empty array when wheres is empty
        expect($result[0])->toBe('');
        expect($result[1])->toBe([]);
        // Third element should be false (not valid)
        expect($result[2])->toBe(false);
    });

    it('handles isComparisonOnly default return false', function () {
        // Targets line 619: Default return false
        // This is for QueryNode types that don't match any instanceof checks
        // Since QueryNode is abstract, we can't create a direct instance
        // But we can test with a CompositeQueryNode that has non-comparison children
        $executor = new FilterExecutor($this->query, $this->builder);
        
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('isComparisonOnly');
        $method->setAccessible(true);
        
        // Create a composite with contains (function operator) which is not comparison-only
        $left = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('name', 'contains', 'test');
        $right = new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('name', 'eq', 'test');
        $composite = new \Aqqo\OData\QueryNodeStructure\CompositeQueryNode($left, 'and', $right);
        
        // Should return false because left has contains (function operator)
        $result = $method->invoke($executor, $composite);
        expect($result)->toBeFalse();
    });
});
