<?php

namespace Aqqo\OData\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModelClass of Model
 * @template TRelatedModel of Model
 */
trait SearchTrait
{
    /**
     * @return void
     */
    public function addSearch(): void
    {
        $search = $this->request?->input('$search');

        if (!empty($search)) {
            $this->appendSearchQuery($search, $this->subject);
        }
    }

    /**
     * Append select clauses to the builder or relation.
     *
     * @param string $search
     * @param Builder<TModelClass> $builder
     * @return void
     */
    public function appendSearchQuery(string $search, Builder $builder): void
    {
        // Extract exact phrases and individual terms
        preg_match_all('/"([^"]+)"|(\S+)/', $search, $matches);

        $exactPhrases = $matches[1]; // Array of exact phrases without quotes
        $terms = $matches[2];        // Array of individual terms

        $tokens = [];
        for ($i = 0; $i < count($matches[0]); $i++) {
            if (!empty($exactPhrases[$i])) {
                $tokens[] = $exactPhrases[$i];
            } elseif (!empty($terms[$i])) {
                $tokens[] = $terms[$i];
            }
        }

        $inclusionTokens = [];
        $excludeTokens = [];

        $expectExclude = false;

        foreach ($tokens as $token) {
            if (strcasecmp($token, 'NOT') === 0) {
                $expectExclude = true;
                continue;
            }

            if ($expectExclude) {
                $excludeTokens[] = $token;
                $expectExclude = false;
                continue;
            }

            $inclusionTokens[] = $token;
        }

        // Build inclusion conditions in an AND wrapper so they combine with previous filters
        // ---------------------------------------------------------------------------------
        // We wrap ALL inclusion tokens in one outer `where(function(){ … })` so that the
        // predicate group is **AND-ed** with any previously-applied filter clauses coming
        // from OData's $filter handling.  Inside that wrapper each token is OR-ed, meaning
        // a record matches if **any** of the tokens is found in one of the searchable
        // columns – but the whole group still respects the outer AND.

        if (!empty($inclusionTokens)) {
            $builder->where(function ($outerQ) use ($inclusionTokens) {
                foreach ($inclusionTokens as $index => $token) {
                    // For the FIRST token we start a new condition with `where`, every
                    // subsequent token is appended with `orWhere` so the tokens are OR-ed
                    // together inside the outer wrapper.
                    $method = $index === 0 ? 'where' : 'orWhere';

                    if (strpos($token, '*') !== false) {
                        // Support simple wildcard searches (e.g. "han*") by converting the
                        // asterisk to SQL's `%` wildcard. Because the token might already
                        // contain a `%` we just perform a straight replacement.
                        $token = str_replace('*', '%', $token);
                        // Build `(col LIKE 'han%') OR (other_col LIKE 'han%') …` for every
                        // searchable column, then attach that group via the chosen `$method`.
                        $outerQ->{$method}(function ($subQ) use ($token) {
                            foreach ($this->getSearchables() as $field) {
                                $subQ->orWhere($field, 'LIKE', $token);
                            }
                        });
                    } else {
                        // Non-wildcard token – we search for the token **anywhere** inside
                        // the column value by wrapping it with `%` on both sides.
                        $like = "%" . $token . "%";
                        // Same construction as above: OR all searchable columns for this
                        // single token, then couple that group with `$method`.
                        $outerQ->{$method}(function ($subQ) use ($like) {
                            foreach ($this->getSearchables() as $field) {
                                $subQ->orWhere($field, 'LIKE', $like);
                            }
                        });
                    }
                }
            });
        }

        // Apply exclusion conditions with AND
        foreach ($excludeTokens as $excludeTerm) {
            $builder->where(function ($q) use ($excludeTerm) {
                foreach ($this->getSearchables() as $field) {
                    $q->where($field, 'NOT LIKE', "%" . $excludeTerm . "%");
                }
            });
        }
    }
}
