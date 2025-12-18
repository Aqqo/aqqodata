<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Filters\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FilterTrait
{
    private ?FilterApplier $filterApplier = null;

    /**
     * @return void
     */
    public function addFilters(): void
    {
        $this->filterApplier()->apply($this->subject);
    }

    /**
     * Append filter queries to the builder based on the OData filter string.
     *
     * @param string $filter
     * @param Builder $builder
     * @param string $statement
     * @return void
     */
    public function appendFilterQuery(string $filter, Builder $builder, string $statement = 'where'): void
    {
        $this->filterApplier()->appendFilterQuery($filter, $builder, $statement);
    }

    private function filterApplier(): FilterApplier
    {
        if ($this->filterApplier instanceof FilterApplier) {
            return $this->filterApplier;
        }

        $this->filterApplier = new FilterApplier(
            $this->request ?? app(Request::class),
            fn(string $property, ?string $className = null) => $this->isPropertyFilterable($property, $className),
            fn(string $property, ?string $className = null) => $this->isPropertyExpandable($property, $className),
        );

        return $this->filterApplier;
    }
}
