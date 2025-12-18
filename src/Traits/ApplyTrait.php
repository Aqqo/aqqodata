<?php

namespace Aqqo\OData\Traits;

use Aqqo\OData\Utils\StringUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Illuminate\Database\Query\Expression;

/**
 * Minimal $apply support (groupby/aggregate + filter step).
 *
 * This is intentionally incremental: it implements the parts needed by the
 * "hard query" tests and can be expanded over time.
 *
 * @template TModelClass of Model
 */
trait ApplyTrait
{
    protected bool $hasApply = false;

    /**
     * Apply the $apply transformation to the root query.
     *
     * @throws \ReflectionException
     */
    public function addApply(): void
    {
        $apply = $this->request?->input('$apply');
        if (empty($apply)) {
            return;
        }

        $this->hasApply = true;
        $this->applyToBuilder($this->subject, (string) $apply);
    }

    /**
     * Apply $apply pipeline to a builder/relation.
     *
     * Supported (for now):
     * - filter(<odata filter>)
     * - groupby((A,B),aggregate(X with sum as Y, X with average as Z))
     *
     * @throws \ReflectionException
     */
    public function applyToBuilder(Builder|Relation $builder, string $apply): void
    {
        if ($builder instanceof Relation) {
            $builder = $builder->getQuery();
        }

        $steps = array_map('trim', explode('/', $apply));
        foreach ($steps as $step) {
            if ($step === '') {
                continue;
            }

            if (Str::startsWith(strtolower($step), 'filter(') && str_ends_with($step, ')')) {
                $inner = substr($step, strlen('filter('), -1);
                $this->appendFilterQuery($inner, $builder);
                continue;
            }

            if (Str::startsWith(strtolower($step), 'groupby(')) {
                $this->applyGroupByAggregate($builder, $step);
                continue;
            }
        }
    }

    /**
     * @throws \ReflectionException
     */
    private function applyGroupByAggregate(Builder $builder, string $step): void
    {
        // groupby((A,B),aggregate(X with sum as Y, X with average as Z))
        $pattern = '/^groupby\(\((.+)\)\s*,\s*aggregate\((.+)\)\)\s*$/i';
        if (!preg_match($pattern, trim($step), $m)) {
            return;
        }

        $groupFieldsRaw = trim($m[1]);
        $aggregateRaw = trim($m[2]);

        $groupFields = array_values(array_filter(array_map('trim', explode(',', $groupFieldsRaw))));
        $aggregateParts = StringUtils::splitODataExpression($aggregateRaw, ',');

        $modelShort = strtolower((new \ReflectionClass($builder->getModel()))->getShortName());

        // If we're aggregating, we MUST keep linking keys for eager-loading.
        // We conservatively include all "*_id" columns in group/select so expanded relations still match.
        $table = $builder->getModel()->getTable();
        $idColumns = array_filter(
            $builder->getConnection()->getSchemaBuilder()->getColumnListing($table),
            fn (string $col) => str_ends_with($col, '_id')
        );

        // Reset selects; we're building a transformed projection
        $builder->select([]);

        $groupByQualified = [];

        foreach ($idColumns as $col) {
            // Keep foreign keys; alias them as-is.
            $qualified = $builder->qualifyColumn($col);
            $builder->selectRaw($builder->getQuery()->getGrammar()->wrap($qualified) . ' as ' . $builder->getQuery()->getGrammar()->wrap($col));
            $groupByQualified[] = $qualified;
        }

        foreach ($groupFields as $odataField) {
            $dbCol = $this->isPropertySelectable($odataField, $modelShort);
            if ($dbCol === false) {
                // If not explicitly selectable, fall back to raw field name
                $dbCol = $odataField;
            }

            $qualified = $builder->qualifyColumn((string) $dbCol);
            $builder->selectRaw($builder->getQuery()->getGrammar()->wrap($qualified) . ' as ' . $builder->getQuery()->getGrammar()->wrap($odataField));
            $groupByQualified[] = $qualified;

            // Ensure output includes these keys even if $select is not used
            $this->selects[$modelShort][$odataField] = $odataField;
            $this->registerComputedProperty($odataField, class_basename($builder->getModel()));
        }

        foreach ($aggregateParts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+with\s+(sum|average|avg)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $part, $am)) {
                continue;
            }

            $source = $am[1];
            $func = strtolower($am[2]);
            $alias = $am[3];

            $dbCol = $this->isPropertySelectable($source, $modelShort);
            if ($dbCol === false) {
                $dbCol = $source;
            }

            $sqlFunc = match ($func) {
                'sum' => 'SUM',
                'average', 'avg' => 'AVG',
            };

            $qualified = $builder->qualifyColumn((string) $dbCol);
            $exprSql = $sqlFunc . '(' . $builder->getQuery()->getGrammar()->wrap($qualified) . ')';
            $builder->selectRaw($exprSql . ' as ' . $builder->getQuery()->getGrammar()->wrap($alias));

            $this->selects[$modelShort][$alias] = $alias;
            // For post-apply $filter (HAVING), prefer filtering on the aggregate expression.
            $this->registerComputedProperty($alias, class_basename($builder->getModel()), new Expression($exprSql));
        }

        if (!empty($groupByQualified)) {
            $builder->groupBy($groupByQualified);
        }
    }
}

