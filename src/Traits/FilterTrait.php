<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Services\FilterParser;
use Aqqo\OData\Services\FilterExecutor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModelClass of Model
 * @template TRelatedModel of Model
 */
trait FilterTrait
{
    /**
     * @return void
     * @throws \ReflectionException
     */
    public function addFilters(): void
    {
        $filter = $this->request?->input('$filter');

        if ($filter === null || trim((string) $filter) === '') {
            preg_match('/\(([^)]+)\)/', $this->request?->url() ?? '', $matches);
            if (!empty($matches[1])) {
                $filter = "{$this->subject->getModel()->getKeyName()} eq '{$matches[1]}'";
            } else {
                return;
            }
        }

        $this->appendFilterQuery(strval($filter), $this->subject);
    }

    /**
     * Append filter queries to the builder based on the OData filter string.
     *
     * @param string $filter
     * @param Builder<TModelClass> $builder
     * @param string $statement
     * @return void
     * @throws \ReflectionException
     */
    public function appendFilterQuery(string $filter, Builder $builder, string $statement = 'where'): void
    {
        // parse into AST
        $parser   = new FilterParser();
        $ast      = $parser->parse($filter);
        // pass $this (the Query) so we can resolve attribute‐based source mappings
        (new FilterExecutor($this, $builder))->execute($ast, $statement);
    }
}
