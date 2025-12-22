<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Utils\StringUtils;

describe('StringUtils', function () {
    
    describe('splitODataExpression', function () {
        it('splits simple comma-separated expressions', function () {
            $result = StringUtils::splitODataExpression('a,b,c');
            expect($result)->toBe(['a', 'b', 'c']);
        });

        it('handles expressions with parentheses', function () {
            $result = StringUtils::splitODataExpression('a(b,c),d');
            expect($result)->toBe(['a(b,c)', 'd']);
        });

        it('handles nested parentheses', function () {
            $result = StringUtils::splitODataExpression('a(b(c,d)),e');
            expect($result)->toBe(['a(b(c,d))', 'e']);
        });

        it('handles quoted strings with commas', function () {
            $result = StringUtils::splitODataExpression("'a,b',c");
            expect($result)->toBe(["'a,b'", 'c']);
        });

        it('handles double-quoted strings with commas', function () {
            $result = StringUtils::splitODataExpression('"a,b",c');
            expect($result)->toBe(['"a,b"', 'c']);
        });

        it('handles escaped quotes in strings', function () {
            $result = StringUtils::splitODataExpression("'don\\'t',c");
            expect($result)->toBe(["'don\\'t'", 'c']);
        });

        it('handles OData-style escaped quotes', function () {
            $result = StringUtils::splitODataExpression("'don''t',c");
            expect($result)->toBe(["'don''t'", 'c']);
        });

        it('handles mixed quote types', function () {
            $result = StringUtils::splitODataExpression("'single',\"double\",normal");
            expect($result)->toBe(["'single'", '"double"', 'normal']);
        });

        it('handles empty expressions', function () {
            $result = StringUtils::splitODataExpression('');
            expect($result)->toBe([]);
        });

        it('handles single expression without commas', function () {
            $result = StringUtils::splitODataExpression('single');
            expect($result)->toBe(['single']);
        });

        it('handles whitespace around expressions', function () {
            $result = StringUtils::splitODataExpression(' a , b , c ');
            expect($result)->toBe([' a ', ' b ', ' c ']);
        });

        it('throws exception for unbalanced parentheses', function () {
            expect(fn() => StringUtils::splitODataExpression('a(b,c'))
                ->toThrow(\InvalidArgumentException::class, 'Unbalanced parentheses in OData expression');
        });

        it('throws exception for too many closing parentheses', function () {
            expect(fn() => StringUtils::splitODataExpression('a(b,c))'))
                ->toThrow(\InvalidArgumentException::class, 'Unbalanced parentheses in OData expression');
        });

        it('throws exception for unbalanced quotes', function () {
            expect(fn() => StringUtils::splitODataExpression("'unclosed"))
                ->toThrow(\InvalidArgumentException::class, 'Unbalanced quotes in OData expression');
        });

        it('handles complex real-world examples', function () {
            $result = StringUtils::splitODataExpression("name eq 'test',age gt 18,contains(description,'important')");
            expect($result)->toBe([
                "name eq 'test'",
                'age gt 18',
                "contains(description,'important')"
            ]);
        });
    });

    describe('getSortedDetails', function () {
        it('sorts details in correct order', function () {
            $result = StringUtils::getSortedDetails('$filter=name eq test;$select=id,name;$expand=related');
            expect($result)->toBe([
                '$select=id,name',
                '$expand=related',
                '$filter=name eq test'
            ]);
        });

        it('handles single detail', function () {
            $result = StringUtils::getSortedDetails('$select=id,name');
            expect($result)->toBe(['$select=id,name']);
        });

        it('handles details with parentheses', function () {
            $result = StringUtils::getSortedDetails('$filter=name eq test;$expand=related($select=id)');
            expect($result)->toBe([
                '$expand=related($select=id)',
                '$filter=name eq test'
            ]);
        });

        it('handles unknown details', function () {
            $result = StringUtils::getSortedDetails('$unknown=test;$select=id');
            expect($result)->toBe([
                '$select=id',
                '$unknown=test'
            ]);
        });

        it('handles case insensitive details', function () {
            $result = StringUtils::getSortedDetails('$SELECT=id;$FILTER=name eq test');
            expect($result)->toBe([
                '$SELECT=id',
                '$FILTER=name eq test'
            ]);
        });

        it('handles empty details', function () {
            $result = StringUtils::getSortedDetails('');
            expect($result)->toBe([]);
        });

        it('handles whitespace in details', function () {
            $result = StringUtils::getSortedDetails(' $select=id ; $filter=name eq test ');
            expect($result)->toBe([
                '$select=id',
                '$filter=name eq test'
            ]);
        });

        it('handles complex nested details', function () {
            $result = StringUtils::getSortedDetails('$filter=name eq test;$expand=related($select=id,name;$filter=active eq true)');
            expect($result)->toBe([
                '$expand=related($select=id,name;$filter=active eq true)',
                '$filter=name eq test'
            ]);
        });

        it('handles multiple details of same type', function () {
            $result = StringUtils::getSortedDetails('$select=id;$select=name;$filter=active eq true');
            expect($result)->toBe([
                '$select=id',
                '$select=name',
                '$filter=active eq true'
            ]);
        });
    });

    describe('edge cases', function () {
        it('handles very long expressions', function () {
            $longExpression = str_repeat('a,', 1000) . 'b';
            $result = StringUtils::splitODataExpression($longExpression);
            expect(count($result))->toBe(1001);
        });

        it('handles deeply nested parentheses', function () {
            $nested = 'a(' . str_repeat('b(', 10) . 'c' . str_repeat(')', 10) . ')';
            $result = StringUtils::splitODataExpression($nested . ',d');
            expect($result)->toBe([$nested, 'd']);
        });

        it('handles special characters in expressions', function () {
            $result = StringUtils::splitODataExpression('a@#$%,b!@#');
            expect($result)->toBe(['a@#$%', 'b!@#']);
        });

        it('handles backslash-escaped quotes in strings', function () {
            $result = StringUtils::splitODataExpression("'don\\'t',c");
            expect($result)->toBe(["'don\\'t'", 'c']);
        });

        it('handles backslash-escaped double quotes in strings', function () {
            $result = StringUtils::splitODataExpression('"don\\"t",c');
            expect($result)->toBe(['"don\\"t"', 'c']);
        });

        it('handles mixed escaped and unescaped quotes', function () {
            expect(fn() => StringUtils::splitODataExpression("'escaped\'quote','normal'quote'"))
            ->toThrow(\InvalidArgumentException::class, 'Unbalanced quotes in OData expression');
        });

        it('handles custom separator in splitODataExpression', function () {
            $result = StringUtils::splitODataExpression('a;b;c', ';');
            expect($result)->toBe(['a', 'b', 'c']);
        });

        it('handles custom separator with parentheses', function () {
            $result = StringUtils::splitODataExpression('a(b;c);d', ';');
            expect($result)->toBe(['a(b;c)', 'd']);
        });

        it('handles custom separator in getSortedDetails', function () {
            $result = StringUtils::getSortedDetails('$filter=name eq test,$select=id,name,$expand=related', ',');
        
            expect(array_diff(
                array_values($result),
                [
                    '$select=id',
                    'name',
                    '$expand=related',
                    '$filter=name eq test',
                ]
            ))->toBe([]);
        });

        it('handles getSortedDetails with empty current part', function () {
            $result = StringUtils::getSortedDetails('$select=id;;$filter=name eq test');
            
            expect(array_diff(
                array_values($result),
                [
                    '$select=id',
                    '',
                    '$filter=name eq test'
                ]
            ))->toBe([]);
        
        });

        it('handles getSortedDetails with unbalanced closing parentheses', function () {
            $result = StringUtils::getSortedDetails('$select=id;$filter=name eq test)');
            expect($result)->toBe([
                '$select=id',
                '$filter=name eq test)'
            ]);
        });

        it('handles getSortedDetails with whitespace-only parts', function () {
            $result = StringUtils::getSortedDetails('$select=id;   ;$filter=name eq test');
            
            expect(array_diff(
                array_values($result),
                [
                    '$select=id',
                    '',
                    '$filter=name eq test'
                ]
            ))->toBe([]);
            
           
        });
    });
});
