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
        // Extract exact phrases and individual terms (and keep AND/OR/NOT tokens)
        preg_match_all('/"([^"]+)"|(\S+)/', $search, $matches);

        $exactPhrases = $matches[1];
        $terms = $matches[2];

        $rawTokens = [];
        for ($i = 0; $i < count($matches[0]); $i++) {
            if (!empty($exactPhrases[$i])) {
                $rawTokens[] = $exactPhrases[$i];
            } elseif (!empty($terms[$i])) {
                $rawTokens[] = $terms[$i];
            }
        }

        // If the search contains explicit boolean operators, interpret:
        // - OR joins tokens in the same clause
        // - AND joins clauses (each clause must match)
        // - NOT negates the next token
        $hasExplicitBoolean = collect($rawTokens)->contains(function ($t) {
            return in_array(strtoupper($t), ['AND', 'OR'], true);
        });

        $excludeTokens = [];

        // clauses = array of OR-groups; overall is AND between clauses
        $clauses = [];
        $currentOrGroup = [];
        $expectExclude = false;

        foreach ($rawTokens as $token) {
            $upper = strtoupper($token);

            if ($upper === 'NOT') {
                $expectExclude = true;
                continue;
            }

            if ($hasExplicitBoolean && $upper === 'OR') {
                continue;
            }

            if ($hasExplicitBoolean && $upper === 'AND') {
                if (!empty($currentOrGroup)) {
                    $clauses[] = $currentOrGroup;
                    $currentOrGroup = [];
                }
                continue;
            }

            if ($expectExclude) {
                $excludeTokens[] = $token;
                $expectExclude = false;
                continue;
            }

            $currentOrGroup[] = $token;
        }

        if ($hasExplicitBoolean) {
            if (!empty($currentOrGroup)) {
                $clauses[] = $currentOrGroup;
            }
        } else {
            // Backward compatible behavior: no explicit boolean operators => one OR-clause
            $clauses = [array_values(array_filter($currentOrGroup, fn ($t) => $t !== ''))];
        }

        // Build inclusion conditions:
        // - AND between clauses
        // - OR between tokens within each clause
        foreach ($clauses as $clause) {
            if (empty($clause)) {
                continue;
            }

            $builder->where(function ($outerQ) use ($clause) {
                foreach ($clause as $index => $token) {
                    $method = $index === 0 ? 'where' : 'orWhere';

                    if (strpos($token, '*') !== false) {
                        $like = str_replace('*', '%', $token);
                        $outerQ->{$method}(function ($subQ) use ($like) {
                            foreach ($this->getSearchables() as $field) {
                                $subQ->orWhere($field, 'LIKE', $like);
                            }
                        });
                    } else {
                        $like = "%" . $token . "%";
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
