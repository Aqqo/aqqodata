<?php

namespace Aqqo\OData;

use Aqqo\OData\Exceptions\QueryException;
use Aqqo\OData\Traits\AttributesTrait;
use Aqqo\OData\Traits\CountTrait;
use Aqqo\OData\Traits\SearchTrait;
use Aqqo\OData\Traits\SelectTrait;
use Aqqo\OData\Utils\ClassUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Aqqo\OData\Traits\ExpandTrait;
use Aqqo\OData\Traits\FilterTrait;
use Aqqo\OData\Traits\OrderByTrait;
use Aqqo\OData\Traits\SkipTrait;
use Aqqo\OData\Traits\TopTrait;
use Aqqo\OData\Traits\ResponseTrait;

/**
 * @template TModelClass of Model
 * @template TRelatedModel of Model
 */
class Query implements \JsonSerializable
{
    /** @use SelectTrait<TModelClass, TRelatedModel, TModelClass, TModelClass> */
    use SelectTrait;
    /** @use FilterTrait<TModelClass, TRelatedModel> */
    use FilterTrait;
    /** @use ExpandTrait<TModelClass, TRelatedModel> */
    use ExpandTrait;
    /** @use SearchTrait<TModelClass, TRelatedModel> */
    use SearchTrait;
    /** @use SkipTrait */
    use SkipTrait;
    /** @use TopTrait */
    use TopTrait;
    /** @use CountTrait */
    use CountTrait;
    /** @use OrderByTrait<TModelClass> */
    use OrderByTrait;
    /** @use ResponseTrait */
    use ResponseTrait;
    /** @use AttributesTrait */
    use AttributesTrait;

    /**
     * @var \ReflectionClass<TModelClass>
     */
    protected \ReflectionClass $subjectModelReflectionClass;

    /**
     * @param Builder<TModelClass> $subject
     * @param bool $select
     * @param bool $filter
     * @param bool $expand
     * @param bool $skip
     * @param bool $top
     * @param bool $count
     * @param bool $orderby
     * @param Request|null $request
     * @throws \ReflectionException
     */
    public function __construct(
        protected Builder $subject,
        protected bool            $select = true,
        protected bool            $filter = true,
        protected bool            $expand = true,
        protected bool            $search = true,
        protected bool            $skip = true,
        protected bool            $top = true,
        protected bool            $count = true,
        protected bool            $orderby = true,
        protected ?Request        $request = null
    )
    {
        $this->request = !is_null($this->request) ? Request::createFrom($this->request) : app(Request::class);
        $this->subjectModelReflectionClass = new \ReflectionClass($this->subject->getModel());

        $this->handleAttributes();

        if ($select) $this->addSelect();

        if ($filter) $this->addFilters();

        if ($expand) $this->addExpands();

        if ($this->search) $this->addSearch();

        if ($skip) $this->addSkip();

        if ($top) $this->addTop();

        if ($count) $this->addCount();

        if ($orderby) $this->addOrderBy();
    }

    /**
     * @param Builder<TModelClass>|string $subject
     * @param Request|null $request
     * @return static
     * @throws \ReflectionException
     */
    public static function for(Builder|string $subject, ?Request $request = null): static
    {
        $subject = is_subclass_of($subject, Model::class) ? $subject::query() : $subject;
        return new static($subject, request: $request);
    }

    /**
     * @return $this
     */
    public function clone(): static
    {
        return clone $this;
    }

    public function __clone()
    {
        $this->subject = clone $this->subject;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->subject->{$name};
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value)
    {
        $this->subject->{$name} = $value;
    }

    /**
     * @return string
     */
    public function toSql(): string
    {
        return $this->subject->toRawSql();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string,mixed>>
     * @throws QueryException
     */
    public function get(): \Illuminate\Support\Collection
    {
        try {
            return $this->resolveCollection($this->subject->get());
        } catch (\Exception $e) {
            throw new QueryException($e->getMessage(), (int)$e->getCode() ?: 0, $e);
        }
    }

    /**
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->getResponse();
    }

    /**
     * @param Collection<int, TModelClass> $collection
     * @return \Illuminate\Support\Collection<int, array<string,mixed>>
     */
    private function resolveCollection(Collection $collection): \Illuminate\Support\Collection
    {
        return $collection->map(function ($item) {
            return $this->resolveModel($item);
        });
    }

    /**
     *
     * Be aware; the issue with not cloning the item might result in infinite loops when
     * a mutated or casted attribute loads in more relations and maybe even a relation to the current item,
     * therefor an infinite loop is introduced.
     *
     * @param Model $item
     * @param bool $ignore_selects
     * @return array
     */
    private function resolveModel(Model &$item, bool $ignore_selects = false): array
    {
        // First we clone the item. As the getAttribute might load extra relations we do not want.
        $cloned_item = clone $item;

        // Get the selected attributes from the item.
        $shortName = ClassUtils::getShortName($item);
        $attributes = [];
        // If the ignore_selects is true, we just get the attributes from the item.
        if ($ignore_selects) {
            $attributes = $item->getAttributes();
        } 
        // If we processed selects from the request, we use those.
        else if (array_key_exists($shortName, $this->selects)) {
            foreach ($this->selects[$shortName] ?? [] as $odata_column => $db_column) {
                $attributes[$odata_column] = $item->getAttribute($db_column);
            }
        } 
        // If we did not process selects from the request, but we have processed the model before, we use the default selects.
        // This is a fallback to handle relations like morphTo where its not predefined which model will be loaded.
        else if (array_key_exists($shortName, $this->defaultSelectables)) {
            foreach ($this->defaultSelectables[$shortName] ?? [] as $odata_column => $db_column) {
                $attributes[$odata_column] = $item->getAttribute($db_column);
            }
        } 
        // If we did not process selects from the request and we did not have default selects, we process the model now to find the default selects.
        // This is a fallback to handle relations like morphTo where its not predefined which model will be loaded.
        else {
            $this->handleModel($item->newQuery());
            foreach ($this->defaultSelectables[$shortName] ?? [] as $odata_column => $db_column) {
                $attributes[$odata_column] = $item->getAttribute($db_column);
            }
        }

        // Then set the attribute on the clonedItem
        $cloned_item->setRawAttributes($attributes);
        foreach ($cloned_item->getRelations() as $key => $relation) {
            if ($relation instanceof Collection) {
                $attributes[$key] = $this->resolveCollection($relation);
            } else if ($relation instanceof Model)  {
                $reflectionClass = new \ReflectionClass($item);

                if ($reflectionClass->hasMethod($key)) {
                    $attributes[$key] = $this->resolveModel($relation);
                } else {
                    // Handle relations like BelongsToMany pivot without defined accessors on the model.
                    $attributes[$key] = $this->resolveModel($relation, true);
                }
            }
        }
        return $attributes;
    }
}
