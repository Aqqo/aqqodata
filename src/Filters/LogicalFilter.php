<?php

namespace Aqqo\OData\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Logical filter for AND/OR operations
 */
class LogicalFilter implements FilterInterface
{
    private string $operator;
    private array $filters;

    public function __construct(string $operator, array $filters = [])
    {
        $this->operator = strtolower($operator);
        $this->filters = $filters;
    }

    public function addFilter(FilterInterface $filter): void
    {
        $this->filters[] = $filter;
    }

    public function apply(Builder $builder): void
    {
        if (empty($this->filters)) {
            return;
        }

        $builder->where(function (Builder $query) {
            foreach ($this->filters as $index => $filter) {
                if ($index === 0) {
                    $filter->apply($query);
                } else {
                    if ($this->operator === 'or') {
                        $query->orWhere(function (Builder $subQuery) use ($filter) {
                            $filter->apply($subQuery);
                        });
                    } else {
                        $query->where(function (Builder $subQuery) use ($filter) {
                            $filter->apply($subQuery);
                        });
                    }
                }
            }
        });
    }

    public function toString(): string
    {
        if (empty($this->filters)) {
            return '';
        }

        $filterStrings = array_map(fn($filter) => $filter->toString(), $this->filters);
        return '(' . implode(" {$this->operator} ", $filterStrings) . ')';
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }
}
