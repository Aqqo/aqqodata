<?php

namespace Aqqo\OData\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @template TModelClass of Model
 * @template TDeclaringModel of Model
 * @template TResult of Model
 * @template TRelatedModel of Model
 *
 */
trait SelectTrait
{
    /**
     * @var array<string, array<string,string>>
     */
    public array $selects = [];

    /**
     * OData property names requested per expanded relation key (e.g. relatedModel, parent)
     * when $expand includes a nested $select=. Used at serialization with the concrete
     * loaded model (required for MorphTo). Empty list means an explicit empty $select.
     *
     * @var array<string, list<string>>
     */
    public array $nestedExpandExplicitSelectTokens = [];

    /**
     * @return void
     */
    public function addSelect(): void
    {
        $select = $this->request?->input('$select');

        if (!empty($select)) {
            $this->appendSelectQuery($select, $this->subject);
        } else {
            $this->resolveToDefaultSelects($this->subject);
        }
    }

    /**
     * Append select clauses to the builder or relation.
     *
     * @param string $select
     * @param Builder<TModelClass> $builder
     * @return void
     */
    public function appendSelectQuery(string $select, Builder $builder): void
    {
        $shortName = strtolower((new \ReflectionClass($builder->getModel()))->getShortName());
        if (!empty($select)) {
            foreach (explode(',', $select) as $item) {
                if (is_string($item)) {
                    $item = trim($item);
                    if (($selectable = $this->isPropertySelectable($item, $shortName)) && is_string($selectable)) {
                        $this->selects[$shortName][$item] = trim($selectable);
                    }
                }
            }
        }

        if (empty($this->selects[$shortName])) {
            $this->resolveToDefaultSelects($builder);
        }
    }

    /**
     * @param Builder<TModelClass> $parent
     * @param string $relation
     * @return void
     */
    public function addSelectForExpand(Builder $parent, string $relation): void
    {
        if ($parent->getQuery()->columns !== null) {
            $relationshipBinding = $parent->getRelation($relation);

            if ($relationshipBinding instanceof HasOneOrMany || $relationshipBinding instanceof HasOneOrManyThrough) {
                $parent->addSelect($parent->qualifyColumn($relationshipBinding->getLocalKeyName()));
            }

            if ($relationshipBinding instanceof BelongsTo || $relationshipBinding instanceof BelongsToMany) {
                if ($relationshipBinding instanceof BelongsTo) {
                    $foreignKey = $relationshipBinding->getForeignKeyName();
                    $parent->addSelect($parent->qualifyColumn($foreignKey));
                }

                if ($relationshipBinding instanceof BelongsToMany) {
                    $parentKeyName = $relationshipBinding->getParentKeyName();
                    $parent->addSelect($parent->qualifyColumn($parentKeyName));
                }
            }
        }
    }

    /**
     * @param Builder<TModelClass>|Relation<TModelClass, TDeclaringModel, TResult> $builder
     * @return void
     * @throws \ReflectionException
     */
    public function resolveToDefaultSelects(Builder|Relation $builder): void
    {
        $shortName = strtolower((new \ReflectionClass($builder->getModel()))->getShortName());
        foreach ($this->defaultSelectables[$shortName] ?? [] as $odata_column => $db_column) {
            $this->selects[$shortName][$odata_column] = $db_column;
        }
    }
}
