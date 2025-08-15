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
use Aqqo\OData\Traits\FilterTraitNew;
use Aqqo\OData\Traits\OrderByTrait;
use Aqqo\OData\Traits\SkipTrait;
use Aqqo\OData\Traits\TopTrait;
use Aqqo\OData\Traits\ResponseTrait;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @template TModelClass of Model
 * @template TRelatedModel of Model
 */
class Query implements \JsonSerializable
{
    /** @use SelectTrait<TModelClass, TRelatedModel, TModelClass, TModelClass> */
    use SelectTrait;
    /** @use FilterTraitNew<TModelClass, TRelatedModel> */
    use FilterTraitNew;
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

    private function resolveModel(Model &$item, bool $ignore_selects = false): array
    {
        $attributes = [];
        if ($ignore_selects) {
            $attributes = $item->getAttributes();
        } else {
            foreach ($this->selects[ClassUtils::getShortName($item)] ?? [] as $odata_column => $db_column) {
                $attributes[$odata_column] = $item->getOriginal($db_column);
            }
        }

        $item->setRawAttributes($attributes);
        foreach ($item->getRelations() as $key => $relation) {
            if ($relation instanceof Collection) {
                $attributes[$key] = $this->resolveCollection($relation);
            } else if ($relation instanceof Model)  {
                $reflectionClass = new \ReflectionClass($item);
                $method = $reflectionClass->getMethod($key);
                if ($method->getReturnType()->getName() == MorphTo::class) {
                    $attributes[$key] = $this->resolveModel($relation, true);
                } else {
                    $attributes[$key] = $this->resolveModel($relation);
                }
            }
        }
        return $attributes;
    }
}
