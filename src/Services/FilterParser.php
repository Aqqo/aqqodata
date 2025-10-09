<?php

namespace Aqqo\OData\Services;

use Aqqo\OData\QueryNodeStructure\BasicQueryNode;
use Aqqo\OData\QueryNodeStructure\CompositeQueryNode;
use Aqqo\OData\QueryNodeStructure\LambdaQueryNode;
use Aqqo\OData\QueryNodeStructure\QueryNode;

class FilterParser
{
    /**
     * @var array<int, array{type:string,value:string}>
     */
    private array $tokens = [];

    private int $position = 0;

    public function parse(string $filter): QueryNode
    {
        $trimmed = trim($filter);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Filter string cannot be empty.');
        }

        $this->tokens = $this->tokenize($trimmed);
        $this->position = 0;

        $node = $this->parseOrExpression();
        $this->expectEnd();

        return $node;
    }

    /**
     * @return array<int, array{type:string,value:string}>
     */
    private function tokenize(string $input): array
    {
        $length = strlen($input);
        $tokens = [];
        $i = 0;

        while ($i < $length) {
            $char = $input[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if ($char === '(') {
                $tokens[] = ['type' => 'paren_open', 'value' => '('];
                $i++;
                continue;
            }

            if ($char === ')') {
                $tokens[] = ['type' => 'paren_close', 'value' => ')'];
                $i++;
                continue;
            }

            if ($char === ',') {
                $tokens[] = ['type' => 'comma', 'value' => ','];
                $i++;
                continue;
            }

            if ($char === ':') {
                $tokens[] = ['type' => 'colon', 'value' => ':'];
                $i++;
                continue;
            }

            if ($char === '/') {
                $tokens[] = ['type' => 'slash', 'value' => '/'];
                $i++;
                continue;
            }

            if ($char === '\'') {
                $i++;
                $value = '';
                while ($i < $length) {
                    $current = $input[$i];
                    if ($current === '\\' && $i + 1 < $length) {
                        $next = $input[$i + 1];
                        if ($next === '\'' || $next === '\\') {
                            $value .= $next;
                            $i += 2;
                            continue;
                        }
                    }

                    if ($current === '\'') {
                        $i++;
                        break;
                    }

                    $value .= $current;
                    $i++;
                }

                $tokens[] = ['type' => 'string', 'value' => "'" . $value . "'"];
                continue;
            }

            if ($char === '-' || ctype_digit($char)) {
                $start = $i;
                $i++;
                while ($i < $length && (ctype_digit($input[$i]) || $input[$i] === '.')) {
                    $i++;
                }
                $tokens[] = ['type' => 'number', 'value' => substr($input, $start, $i - $start)];
                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $start = $i;
                $i++;
                while ($i < $length && (ctype_alnum($input[$i]) || $input[$i] === '_' )) {
                    $i++;
                }
                $word = substr($input, $start, $i - $start);
                $lower = strtolower($word);
                $keywords = [
                    'and', 'or', 'not',
                    'eq', 'ne', 'gt', 'ge', 'lt', 'le', 'in',
                    'any', 'all',
                    'contains', 'startswith', 'endswith',
                    'tolower', 'toupper', 'lower', 'upper', 'trim',
                    'null', 'true', 'false',
                ];
                if (in_array($lower, $keywords, true)) {
                    $tokens[] = ['type' => 'keyword', 'value' => $lower];
                } else {
                    $tokens[] = ['type' => 'identifier', 'value' => $word];
                }
                continue;
            }

            throw new \InvalidArgumentException(sprintf('Unexpected character "%s" in filter.', $char));
        }

        return $tokens;
    }

    private function parseOrExpression(): QueryNode
    {
        $node = $this->parseAndExpression();

        while ($this->matchKeyword('or')) {
            $right = $this->parseAndExpression();
            $node = new CompositeQueryNode($node, 'or', $right);
        }

        return $node;
    }

    private function parseAndExpression(): QueryNode
    {
        $node = $this->parsePrimary();

        while ($this->matchKeyword('and')) {
            $right = $this->parsePrimary();
            $node = new CompositeQueryNode($node, 'and', $right);
        }

        return $node;
    }

    private function parsePrimary(): QueryNode
    {
        $token = $this->peek();
        if (!$token) {
            throw new \InvalidArgumentException('Unexpected end of filter.');
        }

        if ($token['type'] === 'paren_open') {
            $this->position++;
            $node = $this->parseOrExpression();
            $this->expectType('paren_close');
            return $node;
        }

        if ($this->isFunctionCall()) {
            return $this->parseFunctionCall();
        }

        return $this->parsePathBasedExpression();
    }

    private function parsePathBasedExpression(): QueryNode
    {
        $segments = [];
        $segments[] = $this->expectIdentifierValue();

        while ($this->matchType('slash')) {
            $nextToken = $this->expectIdentifierOrKeyword();
            $value = $nextToken['value'];
            $lower = strtolower($value);

            if (in_array($lower, ['any', 'all'], true)) {
                $relation = implode('/', $segments);
                $lambda = $lower;

                $this->expectType('paren_open');
                $parameter = null;
                if ($this->peekType('identifier') && $this->peekTypeAt(1, 'colon')) {
                    $parameter = $this->expectIdentifierValue();
                    $this->expectType('colon');
                }
                $condition = $this->parseOrExpression();
                $this->expectType('paren_close');

                return new LambdaQueryNode($relation, $lambda, $condition, $parameter);
            }

            $segments[] = $value;
        }

        $path = implode('/', $segments);
        return $this->parseComparison($path);
    }

    private function parseFunctionCall(): QueryNode
    {
        $function = $this->expectKeyword(['contains', 'startswith', 'endswith'])['value'];
        $this->expectType('paren_open');

        $path = $this->parsePath();
        $this->expectType('comma');
        $value = $this->parseValue();

        $this->expectType('paren_close');

        return new BasicQueryNode($path, $function, $value);
    }

    private function parseComparison(string $path): QueryNode
    {
        $operatorToken = $this->expectKeyword(['eq', 'ne', 'gt', 'ge', 'lt', 'le', 'in']);
        $operator = $operatorToken['value'];

        if ($operator === 'in') {
            $this->expectType('paren_open');
            $values = [];
            if (!$this->matchType('paren_close')) {
                $values[] = $this->parseValue();
                while ($this->matchType('comma')) {
                    $values[] = $this->parseValue();
                }
                $this->expectType('paren_close');
            }

            return new BasicQueryNode($path, $operator, $values);
        }

        $value = $this->parseValue();
        return new BasicQueryNode($path, $operator, $value);
    }

    private function parseValue(): string
    {
        $token = $this->peek();
        if (!$token) {
            throw new \InvalidArgumentException('Expected value but found end of filter.');
        }

        if (in_array($token['type'], ['string', 'number'], true)) {
            $this->position++;
            return $token['value'];
        }

        if ($token['type'] === 'keyword' && in_array($token['value'], ['null', 'true', 'false'], true)) {
            $this->position++;
            return $token['value'];
        }

        if ($token['type'] === 'identifier') {
            $this->position++;
            return $token['value'];
        }

        throw new \InvalidArgumentException(sprintf('Unexpected value token "%s".', $token['value']));
    }

    private function parsePath(): string
    {
        $token = $this->peek();
        if ($token && $token['type'] === 'keyword' && in_array($token['value'], ['tolower', 'toupper', 'lower', 'upper', 'trim'], true) && $this->peekTypeAt(1, 'paren_open')) {
            $function = $this->expectKeyword(['tolower', 'toupper', 'lower', 'upper', 'trim'])['value'];
            $this->expectType('paren_open');
            $inner = $this->parsePath();
            $this->expectType('paren_close');

            return $function . '(' . $inner . ')';
        }

        $segments = [];
        $segments[] = $this->expectIdentifierValue();

        while ($this->matchType('slash')) {
            $segments[] = $this->expectIdentifierValue();
        }

        return implode('/', $segments);
    }

    private function isFunctionCall(): bool
    {
        $token = $this->peek();
        $next = $this->peek(1);
        if (!$token || !$next) {
            return false;
        }

        return $token['type'] === 'keyword'
            && in_array($token['value'], ['contains', 'startswith', 'endswith'], true)
            && $next['type'] === 'paren_open';
    }

    private function expectEnd(): void
    {
        if ($this->position < count($this->tokens)) {
            $token = $this->tokens[$this->position];
            throw new \InvalidArgumentException(sprintf('Unexpected token "%s" at end of filter.', $token['value']));
        }
    }

    private function peek(int $offset = 0): ?array
    {
        $index = $this->position + $offset;
        return $this->tokens[$index] ?? null;
    }

    private function peekType(string $type): bool
    {
        $token = $this->peek();
        return $token !== null && $token['type'] === $type;
    }

    private function peekTypeAt(int $offset, string $type): bool
    {
        $token = $this->peek($offset);
        return $token !== null && $token['type'] === $type;
    }

    /**
     * @param string|array<int, string> $keywords
     */
    private function matchKeyword(string|array $keywords): bool
    {
        $token = $this->peek();
        if (!$token || $token['type'] !== 'keyword') {
            return false;
        }

        $values = (array) $keywords;
        if (!in_array($token['value'], $values, true)) {
            return false;
        }

        $this->position++;
        return true;
    }

    /**
     * @param string|array<int, string> $keywords
     * @return array{type:string,value:string}
     */
    private function expectKeyword(string|array $keywords): array
    {
        $token = $this->peek();
        if (!$token || $token['type'] !== 'keyword') {
            throw new \InvalidArgumentException('Expected keyword.');
        }

        $values = (array) $keywords;
        if (!in_array($token['value'], $values, true)) {
            throw new \InvalidArgumentException(sprintf('Unexpected keyword "%s".', $token['value']));
        }

        $this->position++;
        return $token;
    }

    private function matchType(string $type): bool
    {
        $token = $this->peek();
        if (!$token || $token['type'] !== $type) {
            return false;
        }

        $this->position++;
        return true;
    }

    /**
     * @return array{type:string,value:string}
     */
    private function expectType(string $type): array
    {
        $token = $this->peek();
        if (!$token || $token['type'] !== $type) {
            throw new \InvalidArgumentException(sprintf('Expected token of type "%s".', $type));
        }

        $this->position++;
        return $token;
    }

    private function expectIdentifierValue(): string
    {
        $token = $this->peek();
        if (!$token || $token['type'] !== 'identifier') {
            throw new \InvalidArgumentException('Expected identifier.');
        }

        $this->position++;
        return $token['value'];
    }

    /**
     * @return array{type:string,value:string}
     */
    private function expectIdentifierOrKeyword(): array
    {
        $token = $this->peek();
        if (!$token || !in_array($token['type'], ['identifier', 'keyword'], true)) {
            throw new \InvalidArgumentException('Expected identifier.');
        }

        $this->position++;
        return $token;
    }
}
