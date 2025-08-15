<?php

namespace Aqqo\OData\Filters;

/**
 * Fluent builder for creating filters programmatically
 */
class FilterBuilder
{
    private array $filters = [];
    private string $currentOperator = 'and';

    /**
     * Create a new filter builder
     *
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Add a simple equality filter
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function eq(string $column, mixed $value): static
    {
        $this->addFilter(new SimpleFilter($column, '=', $value));
        return $this;
    }

    /**
     * Add a not equals filter
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function ne(string $column, mixed $value): static
    {
        $this->addFilter(new SimpleFilter($column, '!=', $value));
        return $this;
    }

    /**
     * Add a greater than filter
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function gt(string $column, mixed $value): static
    {
        $this->addFilter(new SimpleFilter($column, '>', $value));
        return $this;
    }

    /**
     * Add a greater than or equal filter
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function ge(string $column, mixed $value): static
    {
        $this->addFilter(new SimpleFilter($column, '>=', $value));
        return $this;
    }

    /**
     * Add a less than filter
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function lt(string $column, mixed $value): static
    {
        $this->addFilter(new SimpleFilter($column, '<', $value));
        return $this;
    }

    /**
     * Add a less than or equal filter
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function le(string $column, mixed $value): static
    {
        $this->addFilter(new SimpleFilter($column, '<=', $value));
        return $this;
    }

    /**
     * Add an IN filter
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function in(string $column, array $values): static
    {
        $this->addFilter(new SimpleFilter($column, 'IN', $values));
        return $this;
    }

    /**
     * Add a contains filter
     *
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function contains(string $column, string $value): static
    {
        $this->addFilter(new FunctionFilter('contains', $column, $value));
        return $this;
    }

    /**
     * Add a starts with filter
     *
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function startsWith(string $column, string $value): static
    {
        $this->addFilter(new FunctionFilter('startswith', $column, $value));
        return $this;
    }

    /**
     * Add an ends with filter
     *
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function endsWith(string $column, string $value): static
    {
        $this->addFilter(new FunctionFilter('endswith', $column, $value));
        return $this;
    }

    /**
     * Add a custom filter
     *
     * @param FilterInterface $filter
     * @return $this
     */
    public function add(FilterInterface $filter): static
    {
        $this->addFilter($filter);
        return $this;
    }

    /**
     * Start an AND group
     *
     * @param callable $callback
     * @return $this
     */
    public function and(callable $callback): static
    {
        $previousOperator = $this->currentOperator;
        $this->currentOperator = 'and';
        
        $callback($this);
        
        $this->currentOperator = $previousOperator;
        return $this;
    }

    /**
     * Start an OR group
     *
     * @param callable $callback
     * @return $this
     */
    public function or(callable $callback): static
    {
        $previousOperator = $this->currentOperator;
        $this->currentOperator = 'or';
        
        $callback($this);
        
        $this->currentOperator = $previousOperator;
        return $this;
    }

    /**
     * Add a relationship filter with 'any'
     *
     * @param string $relation
     * @param callable $callback
     * @return $this
     */
    public function any(string $relation, callable $callback): static
    {
        $subBuilder = new FilterBuilder();
        $callback($subBuilder);
        
        $subFilters = $subBuilder->getFilters();
        if (!empty($subFilters)) {
            $this->addFilter(new RelationshipFilter('any', $relation, $subFilters[0]));
        }
        
        return $this;
    }

    /**
     * Add a relationship filter with 'all'
     *
     * @param string $relation
     * @param callable $callback
     * @return $this
     */
    public function all(string $relation, callable $callback): static
    {
        $subBuilder = new FilterBuilder();
        $callback($subBuilder);
        
        $subFilters = $subBuilder->getFilters();
        if (!empty($subFilters)) {
            $this->addFilter(new RelationshipFilter('all', $relation, $subFilters[0]));
        }
        
        return $this;
    }

    /**
     * Build the final filter
     *
     * @return FilterInterface|null
     */
    public function build(): ?FilterInterface
    {
        if (empty($this->filters)) {
            return null;
        }

        if (count($this->filters) === 1) {
            return $this->filters[0];
        }

        $logicalFilter = new LogicalFilter($this->currentOperator);
        foreach ($this->filters as $filter) {
            $logicalFilter->addFilter($filter);
        }

        return $logicalFilter;
    }

    /**
     * Get all filters
     *
     * @return array<FilterInterface>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Add a filter to the internal collection
     *
     * @param FilterInterface $filter
     * @return void
     */
    private function addFilter(FilterInterface $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * Get the string representation of the built filter
     *
     * @return string
     */
    public function toString(): string
    {
        $filter = $this->build();
        return $filter ? $filter->toString() : '';
    }
}
