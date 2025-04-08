<?php

namespace Aqqo\OData\Utils;

/**
 * @class OperatorUtils
 * The class below contains all static functions for operators. Especially mapping the OData operators to Eloquent/Mysql operators.
 */
class OperatorUtils
{
    /**
     * @var array<string, array<int, string>>
     */
    private static array $operatorMap = [
        'eq' => ['=', '!='],
        'ne' => ['!=', '='],
        'ge' => ['>=', '<'],
        'gt' => ['>', '<='],
        'le' => ['<=', '>'],
        'lt' => ['<', '>='],
        'in' => ['IN', 'NOT IN'],
        'and' => ['AND', 'OR'],
        'or' => ['OR', 'AND'],
        'not' => ['NOT', ''],  // No straightforward inverse for NOT
        'add' => ['+', '-'],
        'sub' => ['-', '+'],
        'mul' => ['*', '/'],
        'div' => ['/', '*'],
        'mod' => ['%', ''],  // No inverse for modulo
        'contains' => ['LIKE', 'NOT LIKE', '%{$value}%'],
        'startswith' => ['LIKE', 'NOT LIKE', '{$value}%'],
        'endswith' => ['LIKE', 'NOT LIKE', '%{$value}'],
        'substring' => ['SUBSTRING', ''],
        'length' => ['LENGTH', ''],
        'indexof' => ['LOCATE', ''],
        'tolower' => ['LOWER', ''],
        'toupper' => ['UPPER', ''],
        'trim' => ['TRIM', ''],
        'concat' => ['CONCAT', ''],
        'year' => ['YEAR', ''],
        'month' => ['MONTH', ''],
        'day' => ['DAY', ''],
        'hour' => ['HOUR', ''],
        'minute' => ['MINUTE', ''],
        'second' => ['SECOND', ''],
        'now' => ['NOW()', ''],
        'round' => ['ROUND', ''],
        'floor' => ['FLOOR', ''],
        'ceiling' => ['CEILING', '']
    ];

    /**
     * Check if an operator exists in the map.
     *
     * @param string $odataOperator The operator to check
     * @return bool True if the operator exists, false otherwise
     */
    public static function isValidOperator(string $odataOperator): bool
    {
        return isset(self::$operatorMap[$odataOperator]);
    }

    /**
     * Map OData operators to Laravel operators.
     *
     * @param string $odataOperator The OData operator to map
     * @param bool $inverse Return inverse operator if true
     * @return string The mapped operator
     * @throws \InvalidArgumentException If the operator is not found in the map
     */
    public static function mapOperator(string $odataOperator, bool $inverse = false): string
    {
        if (!self::isValidOperator($odataOperator)) {
            throw new \InvalidArgumentException("Invalid operator: {$odataOperator}");
        }

        $map = self::$operatorMap[$odataOperator];
        return $inverse ? $map[1] : $map[0];
    }

    /**
     * Get the value based on the operator type.
     *
     * @param string $odataOperator The OData operator
     * @param string|array<string> $value The value to process
     * @return string The processed value
     * @throws \InvalidArgumentException If the operator is not found in the map
     */
    public static function getValueBasedOnOperator(string $odataOperator, string|array $value): string
    {
        if (!self::isValidOperator($odataOperator)) {
            throw new \InvalidArgumentException("Invalid operator: {$odataOperator}");
        }

        if ($odataOperator === 'in' && is_array($value)) {
            return '(' . implode(',', array_map(fn($v) => "'" . addslashes($v) . "'", $value)) . ')';
        }

        $stringValue = addslashes(is_array($value) ? implode(',', $value) : (string)$value);
        if (isset(self::$operatorMap[$odataOperator][2])) {
            return str_replace('{$value}', $stringValue, self::$operatorMap[$odataOperator][2]);
        }
        
        return $stringValue;
    }
}