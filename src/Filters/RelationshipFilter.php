<?php

namespace Aqqo\OData\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filter for relationship operations (any/all)
 */
class RelationshipFilter implements FilterInterface
{
    private string $function; // 'any' or 'all'
    private string $relation;
    private FilterInterface $condition;

    public function __construct(string $function, string $relation, FilterInterface $condition)
    {
        $this->function = strtolower($function);
        $this->relation = $relation;
        $this->condition = $condition;
    }

    public function apply(Builder $builder): void
    {
        $expandable = $this->isPropertyExpandable($this->relation);
        
        if (!$expandable) {
            return;
        }

        $method = ($this->function === 'all' ? 'whereDoesntHave' : 'whereHas');

        $builder->{$method}($expandable, function (Builder $query) {
            $this->condition->apply($query);
        });
    }

    public function toString(): string
    {
        return "{$this->function}({$this->relation}, {$this->condition->toString()})";
    }

    /**
     * Check if a property is expandable (to be implemented by the trait)
     *
     * @param string $property
     * @return string|false
     */
    protected function isPropertyExpandable(string $property): string|false
    {
        // This will be injected by the trait
        if (is_callable($this->validationCallback)) {
            return call_user_func($this->validationCallback, $property);
        }
        return $property;
    }

    /**
     * @var callable|null
     */
    protected $validationCallback = null;
}
