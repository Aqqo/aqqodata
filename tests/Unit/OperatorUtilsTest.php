<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Utils\OperatorUtils;

describe('OperatorUtils', function () {
    
    describe('isValidOperator', function () {
        it('returns true for valid operators', function () {
            expect(OperatorUtils::isValidOperator('eq'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('ne'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('gt'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('lt'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('ge'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('le'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('in'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('and'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('or'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('contains'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('startswith'))->toBeTrue();
            expect(OperatorUtils::isValidOperator('endswith'))->toBeTrue();
        });

        it('returns false for invalid operators', function () {
            expect(OperatorUtils::isValidOperator('invalid'))->toBeFalse();
            expect(OperatorUtils::isValidOperator(''))->toBeFalse();
            expect(OperatorUtils::isValidOperator('EQ'))->toBeFalse(); // Case sensitive
            expect(OperatorUtils::isValidOperator('unknown'))->toBeFalse();
        });
    });

    describe('mapOperator', function () {
        it('maps operators correctly', function () {
            expect(OperatorUtils::mapOperator('eq'))->toBe('=');
            expect(OperatorUtils::mapOperator('ne'))->toBe('!=');
            expect(OperatorUtils::mapOperator('gt'))->toBe('>');
            expect(OperatorUtils::mapOperator('lt'))->toBe('<');
            expect(OperatorUtils::mapOperator('ge'))->toBe('>=');
            expect(OperatorUtils::mapOperator('le'))->toBe('<=');
            expect(OperatorUtils::mapOperator('in'))->toBe('IN');
            expect(OperatorUtils::mapOperator('and'))->toBe('AND');
            expect(OperatorUtils::mapOperator('or'))->toBe('OR');
        });

        it('maps inverse operators correctly', function () {
            expect(OperatorUtils::mapOperator('eq', true))->toBe('!=');
            expect(OperatorUtils::mapOperator('ne', true))->toBe('=');
            expect(OperatorUtils::mapOperator('gt', true))->toBe('<=');
            expect(OperatorUtils::mapOperator('lt', true))->toBe('>=');
            expect(OperatorUtils::mapOperator('ge', true))->toBe('<');
            expect(OperatorUtils::mapOperator('le', true))->toBe('>');
            expect(OperatorUtils::mapOperator('in', true))->toBe('NOT IN');
            expect(OperatorUtils::mapOperator('and', true))->toBe('OR');
            expect(OperatorUtils::mapOperator('or', true))->toBe('AND');
        });

        it('throws exception for invalid operators', function () {
            expect(fn() => OperatorUtils::mapOperator('invalid'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid operator: invalid');
        });
    });

    describe('getValueBasedOnOperator', function () {
        it('handles string values correctly', function () {
            expect(OperatorUtils::getValueBasedOnOperator('eq', 'test'))->toBe('test');
            expect(OperatorUtils::getValueBasedOnOperator('ne', 'value'))->toBe('value');
        });

        it('handles array values for IN operator', function () {
            $values = ['value1', 'value2', 'value3'];
            expect(OperatorUtils::getValueBasedOnOperator('in', $values))->toBe($values);
        });

        it('handles null values', function () {
            expect(OperatorUtils::getValueBasedOnOperator('eq', 'null'))->toBeNull();
            expect(OperatorUtils::getValueBasedOnOperator('ne', null))->toBe('');
        });

        it('handles special operators with value formatting', function () {
            expect(OperatorUtils::getValueBasedOnOperator('contains', 'test'))
                ->toBe('%test%');
            expect(OperatorUtils::getValueBasedOnOperator('startswith', 'test'))
                ->toBe('test%');
            expect(OperatorUtils::getValueBasedOnOperator('endswith', 'test'))
                ->toBe('%test');
        });

        it('escapes special characters in values', function () {
            expect(OperatorUtils::getValueBasedOnOperator('eq', "test'value"))
                ->toBe("test\'value");
            expect(OperatorUtils::getValueBasedOnOperator('eq', 'test"value'))
                ->toBe('test\"value');
        });

        it('handles array values for non-IN operators', function () {
            $values = ['value1', 'value2'];
            expect(OperatorUtils::getValueBasedOnOperator('eq', $values))
                ->toBe('value1,value2');
        });

        it('throws exception for invalid operators', function () {
            expect(fn() => OperatorUtils::getValueBasedOnOperator('invalid', 'test'))
                ->toThrow(\InvalidArgumentException::class, 'Invalid operator: invalid');
        });
    });

    describe('edge cases', function () {
        it('handles empty string values', function () {
            expect(OperatorUtils::getValueBasedOnOperator('eq', ''))->toBe('');
        });

        it('handles numeric string values', function () {
            expect(OperatorUtils::getValueBasedOnOperator('gt', '123'))->toBe('123');
        });

        it('handles boolean-like string values', function () {
            expect(OperatorUtils::getValueBasedOnOperator('eq', 'true'))->toBe('true');
            expect(OperatorUtils::getValueBasedOnOperator('eq', 'false'))->toBe('false');
        });
    });
});
