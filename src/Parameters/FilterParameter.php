<?php
namespace Aqqo\OData\Parameters;

use Aqqo\OData\Utils\OperatorUtils;

/**
 * Represents a filter parameter in an OData query.
 * This class handles parsing and storing filter parameters for comparison operations.
 */
class FilterParameter
{
    /**
     * @param string $column The column name to filter on
     * @param string $operator The comparison operator
     * @param string|array $value The value to compare against
     * @param string $lambda The lambda operator ('any' or 'all') for relationship filters
     * @param array<string> $relation The relationship path segments
     * @param bool $inverseOperator Whether the operator should be inverted
     */
    private function __construct(
        private readonly string $column,
        private readonly string $operator,
        private readonly string|array $value,
        private readonly string $lambda,
        private readonly array $relation,
        private readonly bool $inverseOperator
    ) {}

    /**
     * Create a FilterParameter from an OData filter string.
     * 
     * @param string $input The OData filter string to parse
     * @param bool $inverseOperator Whether to invert the operator
     * @return self
     */
    public static function fromString(string $input, bool $inverseOperator = false): self
    {
        $pattern = '/\b(contains|startswith|endswith|and|or|not|eq|ne|gt|ge|lt|le|in)\b|([(),])|\'([^\']*)\'|(\d+(\.\d+)?)|([A-Za-z_][A-Za-z0-9_]*)/i';
        $lambda = '';
        $relation = '';
        preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);

        $tokens = array_map(function ($match) {
            if (!empty($match[1])) {
                return strtolower($match[1]);
            } elseif (!empty($match[3])) {
                return $match[3];
            } elseif (isset($match[4]) && is_numeric($match[4])) {
                return $match[4];
            } elseif (!empty($match[6])) {
                return $match[6];
            }
            return null;
        }, $matches);

        $tokens = array_filter($tokens, fn($token) => $token !== null);

        if (count($tokens) < 3) {
            return new self('', '', '', '', [], $inverseOperator);
        }

        // Handle function-based filters (contains, startswith, endswith)
        if (in_array($tokens[0], ['contains', 'startswith', 'endswith'], true)) {
            return self::createFunctionFilter($tokens, $inverseOperator);
        }

        if (isset($tokens[1]) && strtolower($tokens[1]) === 'in') {
            return self::createInOperator($tokens, $inverseOperator);
        }

        if (isset($tokens[1]) && ($tokens[1] == 'any' || $tokens[1] == 'all')) {
            return self::createRelationshipFilter($tokens, $inverseOperator);
        }

        return self::createSimpleFilter($tokens, $inverseOperator);
    }

    /**
     * Create a filter parameter for a function-based filter (contains, startswith, endswith).
     * 
     * @param array $tokens The parsed tokens
     * @param bool $inverseOperator Whether to invert the operator
     * @return self
     */
    private static function createFunctionFilter(array $tokens, bool $inverseOperator): self
    {
        $function = $tokens[0];
        $column = $tokens[2];
        $operator = OperatorUtils::mapOperator($function, $inverseOperator);
        $value = OperatorUtils::getValueBasedOnOperator($function, $tokens[4]);

        return new self($column, $operator, $value, '', [], $inverseOperator);
    }

    /**
     * Create a filter parameter for an IN operator.
     * 
     * @param array $tokens The parsed tokens
     * @param bool $inverseOperator Whether to invert the operator
     * @return self
     */
    private static function createInOperator(array $tokens, bool $inverseOperator): self
    {
        $column = $tokens[0];
        $operator = OperatorUtils::mapOperator($tokens[1], $inverseOperator);
        $value = [];
        $inValues = array_slice($tokens, 2);
        
        foreach ($inValues as $token) {
            if ($token === '(' || $token === ')' || $token === ',') {
                continue;
            }
            if (is_numeric($token)) {
                $value[] = $token;
            } else {
                $value[] = trim($token, "'");
            }
        }

        return new self($column, $operator, $value, '', [], $inverseOperator);
    }

    /**
     * Create a filter parameter for a relationship filter (any/all).
     * 
     * @param array $tokens The parsed tokens
     * @param bool $inverseOperator Whether to invert the operator
     * @return self
     * @throws \Exception If the syntax is invalid
     */
    private static function createRelationshipFilter(array $tokens, bool $inverseOperator): self
    {
        if (isset($tokens[5]) && !isset($tokens[6])) {
            $tokens[6] = $tokens[5];
            $tokens[5] = $tokens[7];
            $tokens[7] = $tokens[9];
        }
        if (!isset($tokens[6])) {
            throw new \Exception('Invalid syntax');
        }

        $column = $tokens[5];
        $operatorIndex = 6;
        if (isset($tokens[7]) && OperatorUtils::isValidOperator(strtolower($tokens[7]))) {
            $operatorIndex = 7;
            $column = "{$tokens[5]}.{$tokens[6]}";
        }

        $operator = OperatorUtils::mapOperator($tokens[$operatorIndex], ($tokens[1] == 'all'));
        $value = self::parseRelationshipValue($tokens, $operatorIndex);
        $lambda = $tokens[1];
        $relation = $tokens[0];

        return new self($column, $operator, $value, $lambda, [$relation], $inverseOperator);
    }

    /**
     * Parse the value for a relationship filter.
     * 
     * @param array $tokens The parsed tokens
     * @param int $operatorIndex The index of the operator
     * @return string|array The parsed value
     * @throws \Exception If the syntax is invalid
     */
    private static function parseRelationshipValue(array $tokens, int $operatorIndex): string|array
    {
        if (strtolower($tokens[$operatorIndex]) === 'in') {
            $values = [];
            $remainingTokens = array_slice($tokens, $operatorIndex);
            foreach ($remainingTokens as $token) {
                if ($token !== ',' && $token !== '(' && $token !== ')') {
                    $values[] = trim($token, "'");
                }
            }
            if (!empty($values)) {
                return OperatorUtils::getValueBasedOnOperator('in', $values);
            }
            throw new \Exception('Invalid IN operator syntax');
        }

        return isset($tokens[$operatorIndex + 1]) 
            ? OperatorUtils::getValueBasedOnOperator($tokens[$operatorIndex], $tokens[$operatorIndex + 1]) 
            : '';
    }

    /**
     * Create a simple filter parameter.
     * 
     * @param array $tokens The parsed tokens
     * @param bool $inverseOperator Whether to invert the operator
     * @return self
     */
    private static function createSimpleFilter(array $tokens, bool $inverseOperator): self
    {
        $column = $tokens[0];
        $operator = OperatorUtils::mapOperator($tokens[1], $inverseOperator);
        $value = OperatorUtils::getValueBasedOnOperator($tokens[1], $tokens[2]);

        // Handle relationship paths in column names
        $extra = [];
        if (str_contains($column, '/')) {
            $parts = explode('/', $column);
            $column = array_pop($parts);
            $extra = $parts;
        }

        return new self($column, $operator, $value, '', $extra, $inverseOperator);
    }

    public function getColumn(): string { return $this->column; }
    public function getOperator(): string { return $this->operator; }
    public function getValue(): string|array { return $this->value; }
    public function getLambda(): string { return $this->lambda; }
    /** @return array<string> */
    public function getRelation(): array { return $this->relation; }
    public function isInverse(): bool { return $this->inverseOperator; }
}
