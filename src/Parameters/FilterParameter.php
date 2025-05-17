<?php
namespace Aqqo\OData\Parameters;

use Aqqo\OData\Utils\OperatorUtils;

class FilterParameter
{
    private string $column;
    private string $operator;
    private string|array $value;
    private string $lambda;
    /** @var string[] */
    private array $relation;
    private bool $inverseOperator;

    private function __construct(
        string $column,
        string $operator,
        string|array $value,
        string $lambda,
        array $relation,
        bool $inverseOperator
    ) {
        $this->column = $column;
        $this->operator = $operator;
        $this->value = $value;
        $this->lambda = $lambda;
        $this->relation = $relation;
        $this->inverseOperator = $inverseOperator;
    }

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
            // nothing meaningful
            $column = $operator = $lambda = '';
            $value = '';
            $relation = [];
            goto FINISH;
        }

        if (isset($tokens[1]) && strtolower($tokens[1]) === 'in') {
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
            $lambda = '';
            $relation = [];
            goto FINISH;
        } else if (in_array($tokens[0], ['contains', 'startswith', 'endswith'], true)) {
            $column = $tokens[2];
            $operator = OperatorUtils::mapOperator($tokens[0], $inverseOperator);
            $value = OperatorUtils::getValueBasedOnOperator($tokens[0], $tokens[4]);
            $lambda = '';
            $relation = [];
            goto FINISH;
        } else if (isset($tokens[1]) && ($tokens[1] == 'any' || $tokens[1] == 'all')) {
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
            if (strtolower($tokens[$operatorIndex]) === 'in') {
                $values = [];
                $remainingTokens = array_slice($tokens, $operatorIndex);
                foreach ($remainingTokens as $token) {
                    if ($token !== ',' && $token !== '(' && $token !== ')') {
                        $values[] = trim($token, "'");
                    }
                }
                if (!empty($values)) {
                    $value = OperatorUtils::getValueBasedOnOperator('in', $values);
                } else {
                    throw new \Exception('Invalid IN operator syntax');
                }
            } else {
                $value = isset($tokens[$operatorIndex + 1]) ? OperatorUtils::getValueBasedOnOperator($tokens[$operatorIndex], $tokens[$operatorIndex + 1]) : '';
            }
            $lambda = $tokens[1];
            $relation = $tokens[0];
            goto FINISH;
        } else {
            $column = $tokens[0];
            $operator = OperatorUtils::mapOperator($tokens[1], $inverseOperator);
            $value = OperatorUtils::getValueBasedOnOperator($tokens[1], $tokens[2]);
            $lambda = '';
            $relation = [];
            goto FINISH;
        }

        FINISH:

        // if someone wrote Customer/Region eq 'EMEA' or deeper chains,
        // split them off into the relation array:
        $extra = [];
        if (str_contains($column, '/')) {
            $parts  = explode('/', $column);
            $column = array_pop($parts);
            $extra  = $parts;
        }
        // if any existing single‐string relation (from any/all) e.g. "Orders",
        // shove it on the end of the new chain:
        if (!empty($relation) && is_string($relation)) {
            $extra[] = $relation;
        }
        // now re‐merge
        $relation = $extra;

        return new self(
            $column,
            $operator,
            $value,
            $lambda,
            $relation,
            $inverseOperator
        );
    }

    public function getColumn(): string   { return $this->column; }
    public function getOperator(): string { return $this->operator; }
    public function getValue(): string|array { return $this->value; }
    public function getLambda(): string   { return $this->lambda; }
    /** @return string[] */
    public function getRelation(): array { return $this->relation; }
    public function isInverse(): bool     { return $this->inverseOperator; }
}
