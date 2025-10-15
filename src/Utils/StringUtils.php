<?php

namespace Aqqo\OData\Utils;

/**
 * Class StringUtils
 *
 * Provides utility functions for string manipulation, specifically tailored for OData expressions.
 */
class StringUtils
{
    /**
     * Split an OData expression by commas, respecting nested parentheses.
     *
     * @param string $expression
     * @param string $separator
     * @throws \InvalidArgumentException
     * @return array<string>
     */
    public static function splitODataExpression(string $expression, string $separator = ','): array
    {
        $results   = [];
        $current   = '';
        $depth     = 0;

        // Quote-handling state
        $inQuote   = false;   // Whether we are currently inside a quoted literal
        $quoteChar = null;    // Which quote char opened the literal (' or ")

        $length = strlen($expression);
        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if ($char === "'" || $char === '"') {
                // Determine if this quote char is escaped. Two mechanisms considered:
                //   1. A backslash before the quote (e.g. \")
                //   2. The OData style doubled quote inside a literal (e.g. '' inside 'don''t')

                $isEscapedByBackslash = $i > 0 && $expression[$i - 1] === '\\';

                if ($inQuote) {
                    if ($char === $quoteChar && ! $isEscapedByBackslash) {
                        // Check for doubled quote escaping within OData literals.
                        if ($i + 1 < $length && $expression[$i + 1] === $quoteChar) {
                            // It's an escaped quote – keep both quotes to preserve the literal.
                            $current .= $char . $quoteChar;
                            $i++; // Skip the second quote in the pair
                            continue;
                        }

                        // Otherwise this is the closing quote of the literal
                        $inQuote   = false;
                        $quoteChar = null;
                    }
                } else {
                    if (! $isEscapedByBackslash) {
                        // Opening a new quoted literal
                        $inQuote   = true;
                        $quoteChar = $char;
                    }
                }

                // In all cases, include the quote character in the token
                $current .= $char;
                continue;
            }

            // While inside a quoted literal treat every character as data – parentheses and
            // separators included – and move on.
            if ($inQuote) {
                $current .= $char;
                continue;
            }

            // Structural parentheses tracking (only when *not* inside quotes)
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($depth < 0) {
                throw new \InvalidArgumentException('Unbalanced parentheses in OData expression');
            }

            // Split at the top-level separator (outside parentheses & quotes)
            if ($char === $separator && $depth === 0) {
                $results[] = $current;
                $current   = '';
            } else {
                $current .= $char;
            }
        }

        if ($inQuote) {
            // A quote was opened but never closed.
            throw new \InvalidArgumentException('Unbalanced quotes in OData expression');
        }

        if ($depth !== 0) {
            throw new \InvalidArgumentException('Unbalanced parentheses in OData expression');
        }

        if (trim($current) !== '') {
            $results[] = $current;
        }

        return $results;
    }

    /**
     * Splits a details string into individual components and sorts them in a predefined order.
     *
     * The order is:
     * 1. $select
     * 2. $expand
     * 3. $filter
     *
     * @param string $details The details string to split and sort.
     * @param string $separator
     * @return array<string> The sorted detail components.
     */
    public static function getSortedDetails(string $details, string $separator = ';'): array
    {
        // Initialize variables for splitting
        $parts = [];
        $current = '';
        $parentheses = 0;
        $length = strlen($details);

        // Iterate over each character to split by the specified separator not within parentheses
        for ($i = 0; $i < $length; $i++) {
            $char = $details[$i];

            if ($char === '(') {
                $parentheses++;
            } elseif ($char === ')') {
                if ($parentheses > 0) {
                    $parentheses--;
                }
            }

            if ($char === $separator && $parentheses === 0) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // Add the last part if not empty
        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        // Define the desired order of details using an indexed array for flexibility
        $order = [
            '$select',
            '$expand',
            '$filter',
        ];

        // Assign priorities based on the order array
        $priorityMap = [];
        foreach ($order as $index => $key) {
            $priorityMap[strtolower($key)] = $index + 1; // Start priorities at 1
        }
        $defaultPriority = count($order) + 1;

        // Group parts by their priority
        $grouped = [];

        foreach ($parts as $part) {
            $found = false;
            foreach ($priorityMap as $key => $priority) {
                // Use a case-insensitive check
                if (stripos($part, $key . '=') === 0) { // Ensures it starts with key=
                    $grouped[$priority][] = $part;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // Assign default priority to unknown keys
                $grouped[$defaultPriority][] = $part;
            }
        }

        // Sort the grouped parts by priority
        ksort($grouped);

        // Flatten the grouped parts into a single array
        $sorted = [];
        foreach ($grouped as $priority => $items) {
            foreach ($items as $item) {
                $sorted[] = $item;
            }
        }

        return $sorted;
    }
}
