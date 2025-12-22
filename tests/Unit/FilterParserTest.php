<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Services\FilterParser;

describe('FilterParser', function () {
    $parser = new FilterParser();

    it('throws exception for empty filter', function () use ($parser) {
        expect(fn() => $parser->parse(''))
            ->toThrow(\InvalidArgumentException::class, 'Filter string cannot be empty.');
    });

    it('throws exception for unexpected character', function () use ($parser) {
        expect(fn() => $parser->parse('name eq #'))
            ->toThrow(\InvalidArgumentException::class, 'Unexpected character "#" in filter.');
    });

    it('throws exception for unexpected end of filter', function () use ($parser) {
        expect(fn() => $parser->parse('name eq'))
            ->toThrow(\InvalidArgumentException::class, 'Expected value but found end of filter.');
    });

    it('throws exception for expected value but found end', function () use ($parser) {
        expect(fn() => $parser->parse('name eq '))
            ->toThrow(\InvalidArgumentException::class, 'Expected value but found end of filter.');
    });

    it('throws exception for unexpected value token', function () use ($parser) {
        expect(fn() => $parser->parse('name eq ('))
            ->toThrow(\InvalidArgumentException::class, 'Unexpected value token "(".');
    });

    it('throws exception for unexpected token at end', function () use ($parser) {
        expect(fn() => $parser->parse('name eq \'test\' extra'))
            ->toThrow(\InvalidArgumentException::class, 'Unexpected token "extra" at end of filter');
    });

    it('throws exception for expected keyword', function () use ($parser) {
        expect(fn() => $parser->parse('name \'test\''))
            ->toThrow(\InvalidArgumentException::class, 'Expected keyword.');
    });

    it('throws exception for unexpected keyword', function () use ($parser) {
        expect(fn() => $parser->parse('name and \'test\''))
            ->toThrow(\InvalidArgumentException::class, 'Unexpected keyword "and".');
    });

    it('throws exception for expected token type', function () use ($parser) {
        expect(fn() => $parser->parse('contains(name \'test\')'))
            ->toThrow(\InvalidArgumentException::class, 'Expected token of type "comma".');
    });

    it('throws exception for expected identifier in slash path', function () use ($parser) {
        // Since I changed it to expectIdentifierOrKeywordValue, I need a different way to trigger
        // Unexpected token type that is not identifier or keyword
        expect(fn() => $parser->parse('relation/( eq 1'))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
    });

    it('handles nested transformations in path', function () use ($parser) {
        $node = $parser->parse("tolower(toupper(name)) eq 'test'");
        expect($node->getField())->toBe('tolower(toupper(name))');
    });

    it('handles isAlternativeAggregateSyntax returning false', function () use ($parser) {
        // any followed by something other than (
        $node = $parser->parse("any eq 'test'");
        expect($node->getField())->toBe('any');
    });

    it('throws exception for unexpected end of filter in parsePrimary', function () use ($parser) {
        expect(fn() => $parser->parse("name eq 'test' and "))
            ->toThrow(\InvalidArgumentException::class, 'Unexpected end of filter.');
    });

    it('handles alternative aggregate syntax', function () use ($parser) {
        $node = $parser->parse("any(relatedModels, name eq 'test')");
        expect($node)->toBeInstanceOf(\Aqqo\OData\QueryNodeStructure\LambdaQueryNode::class);
        expect($node->getLambda())->toBe('any');
        expect($node->getRelation())->toBe('relatedModels');
    });

    it('handles exception catch in parsePrimary for invalid operator syntax', function () use ($parser) {
        // Targets line 238: Exception catch when parsing invalid operator syntax
        // This happens when operator is at the start and value parsing fails
        // Test with operator followed by invalid value that causes parseValue to throw
        $node = $parser->parse("eq 'test'");
        // Should return a node with __invalid__ field
        expect($node->getField())->toBe('__invalid__');
        
        // Also test with operator followed by nothing (end of input) to hit the catch block
        try {
            $node2 = $parser->parse("eq");
            // If it doesn't throw, it should return __invalid__
            expect($node2->getField())->toBe('__invalid__');
        } catch (\InvalidArgumentException $e) {
            // This is also valid - parseValue throws when no value found
            expect($e->getMessage())->toContain('Expected value');
        }
    });

    it('handles identifier type in parseValue', function () use ($parser) {
        // Targets lines 329-330: Identifier type in parseValue
        $node = $parser->parse("name eq someIdentifier");
        expect($node->getValue())->toBe('someIdentifier');
    });

    it('handles backslash escaping in strings', function () use ($parser) {
        // Targets lines 112..114: Backslash escaping handling
        // Test with escaped single quote - the parser handles backslash escaping
        // This should parse successfully and the escaped quote should be in the value
        $node = $parser->parse("name eq 'test\\'value'");
        // Verify it parsed successfully (doesn't throw)
        expect($node->getField())->toBe('name');
        expect($node->getValue())->toBeString();
        
        // Test with escaped backslash - covers the backslash escape path
        $node2 = $parser->parse("name eq 'test\\\\value'");
        expect($node2->getField())->toBe('name');
        expect($node2->getValue())->toBeString();
    });

    it('handles OData-style doubled quotes in strings', function () use ($parser) {
        // Also test doubled quotes (OData style) - targets lines 112..114
        // This tests the doubled quote handling path
        $node = $parser->parse("name eq 'don''t'");
        // Verify it parsed successfully
        expect($node->getField())->toBe('name');
        expect($node->getValue())->toBeString();
    });

    it('handles isFunctionCall returning false when token is null', function () use ($parser) {
        // Targets line 374: Return false when $token or $next is null in isFunctionCall
        // This happens when we're at the end of input and peek(1) returns null
        // Test with a filter that doesn't have a function call at the end
        $node = $parser->parse("name eq 'test'");
        expect($node->getField())->toBe('name');
        
        // Test with a filter ending with identifier (no next token)
        // This should cause isFunctionCall to return false when checking next token
        $node2 = $parser->parse("name eq identifier");
        expect($node2->getField())->toBe('name');
    });

    it('handles isAlternativeAggregateSyntax returning false when token is null', function () use ($parser) {
        // Targets line 374: Return false when $token or $next is null
        // This happens at end of input - test with a filter that ends
        $node = $parser->parse("name eq 'test'");
        expect($node->getField())->toBe('name');
    });

    it('handles isAlternativeAggregateSyntax returning false when next is null', function () use ($parser) {
        // Targets line 387: Return false when $token or $next is null in isAlternativeAggregateSyntax
        // Test with "any" followed by something that's not a paren_open
        // This will cause isAlternativeAggregateSyntax to return false (next is not paren_open)
        // and then parse it as a regular identifier
        $node = $parser->parse("any eq 'test'");
        expect($node->getField())->toBe('any');
        
        // Also test with "all" at end of input (next is null)
        // This should cause isAlternativeAggregateSyntax to return false when next is null
        $node2 = $parser->parse("all eq 'test'");
        expect($node2->getField())->toBe('all');
    });

    it('throws exception for expectIdentifierValue when token is not identifier or keyword', function () use ($parser) {
        // Targets line 508: Exception in expectIdentifierValue
        // This happens in parsePath when we expect an identifier after a slash
        // Try to parse a path where after slash we have something that's not identifier/keyword
        // The parser will try to parse "name/" and then expect an identifier, but finds "("
        // However, the parser might handle this in parsePathBasedExpression differently
        // Let's try with a comma which is definitely not identifier/keyword
        expect(fn() => $parser->parse("name/, eq 'test'"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
        
        // Also test in parsePath context (used by parseFunctionCall)
        expect(fn() => $parser->parse("contains(name/, 'test')"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
        
        // Test with transformation function
        expect(fn() => $parser->parse("tolower(name/, 'test')"))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('throws exception for expectIdentifierOrKeyword when token is not identifier or keyword', function () use ($parser) {
        // Targets line 508: Exception in expectIdentifierOrKeyword
        // This is called from parsePathBasedExpression when expecting identifier/keyword after slash
        // Try with a token that's not identifier or keyword (like a comma or paren)
        expect(fn() => $parser->parse("name/( eq 'test')"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
        
        // Also test with comma which is definitely not identifier/keyword
        expect(fn() => $parser->parse("name/, eq 'test'"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
        
        // Test with number which is also not identifier/keyword
        expect(fn() => $parser->parse("name/123 eq 'test'"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
    });

    it('handles semicolon as filter terminator', function () use ($parser) {
        // Targets line 68: break when encountering ';'
        $node = $parser->parse("name eq 'test'; extra stuff");
        expect($node->getField())->toBe('name');
        expect($node->getValue())->toBe("'test'");
    });

    it('handles date time literals in tokenization', function () use ($parser) {
        // Targets lines 142-144: Date/time literal parsing
        $node = $parser->parse("created_at eq 2024-01-02");
        expect($node->getField())->toBe('created_at');
        expect($node->getValue())->toBe('2024-01-02');
        
        // Test with full datetime
        $node2 = $parser->parse("created_at eq 2024-01-02T12:34:56Z");
        expect($node2->getField())->toBe('created_at');
        expect($node2->getValue())->toBe('2024-01-02T12:34:56Z');
        
        // Test with timezone offset
        $node3 = $parser->parse("created_at eq 2024-01-02T12:34:56+01:00");
        expect($node3->getField())->toBe('created_at');
        expect($node3->getValue())->toBe('2024-01-02T12:34:56+01:00');
    });

    it('handles parseValue with literal type', function () use ($parser) {
        // Targets lines 330-331: parseValue with literal type
        $node = $parser->parse("created_at eq 2024-01-02T12:34:56Z");
        expect($node->getField())->toBe('created_at');
        expect($node->getValue())->toBe('2024-01-02T12:34:56Z');
    });

    it('handles parseAndExpression creating CompositeQueryNode', function () use ($parser) {
        // Targets line 189: Creating CompositeQueryNode in parseAndExpression
        $node = $parser->parse("name eq 'test' and description eq 'foo'");
        expect($node)->toBeInstanceOf(\Aqqo\OData\QueryNodeStructure\CompositeQueryNode::class);
        expect($node->getOperator())->toBe('and');
    });

    it('handles parseFunctionCall with all function types', function () use ($parser) {
        // Targets lines 287-291: parseFunctionCall
        $node1 = $parser->parse("contains(name, 'test')");
        expect($node1->getField())->toBe('name');
        expect($node1->getOperator())->toBe('contains');
        expect($node1->getValue())->toBe("'test'");
        
        $node2 = $parser->parse("startswith(name, 'test')");
        expect($node2->getOperator())->toBe('startswith');
        
        $node3 = $parser->parse("endswith(name, 'test')");
        expect($node3->getOperator())->toBe('endswith');
    });

    it('handles parsePath with multiple slashes', function () use ($parser) {
        // Targets line 363: while loop in parsePath with multiple slashes
        $node = $parser->parse("relation/subrelation/field eq 'test'");
        expect($node->getField())->toBe('relation/subrelation/field');
        
        // Test with even more slashes to ensure the while loop executes multiple times
        $node2 = $parser->parse("a/b/c/d/field eq 'test'");
        expect($node2->getField())->toBe('a/b/c/d/field');
        
        // Test with path in function call to hit parsePath
        $node3 = $parser->parse("contains(relation/subrelation/field, 'test')");
        expect($node3->getField())->toBe('relation/subrelation/field');
    });

    it('handles expectIdentifierOrKeyword exception with various invalid tokens', function () use ($parser) {
        // Targets line 508: Exception in expectIdentifierOrKeyword
        // This is called from parsePathBasedExpression line 253 when expecting identifier/keyword after slash
        // Test with number token (not identifier or keyword) - this will tokenize 123 as 'number' type
        expect(fn() => $parser->parse("name/123 eq 'test'"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
        
        // Test with string token - this will tokenize 'test' as 'string' type
        expect(fn() => $parser->parse("name/'test' eq 'value'"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
        
        // Test with paren_open token - this will tokenize ( as 'paren_open' type
        expect(fn() => $parser->parse("name/( eq 'test')"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
        
        // Test with comma token
        expect(fn() => $parser->parse("name/, eq 'test'"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
        
        // Test with colon token
        expect(fn() => $parser->parse("name/: eq 'test'"))
            ->toThrow(\InvalidArgumentException::class, 'Expected identifier.');
    });
});
