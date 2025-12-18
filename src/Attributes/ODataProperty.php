<?php

declare(strict_types=1);

namespace Aqqo\OData\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ODataProperty
{
    public function __construct(
        protected string  $name,
        protected ?string $description = null,
        protected bool    $selectable = true,
        protected bool    $defaultSelectable = true,
        protected bool    $filterable = true,
        protected bool    $searchable = false,
        protected bool    $orderable = true,
        protected ?string $source = null // Source defines the database column. Might differ from name to have 'object_name' resolve as 'name'.
    )
    {

    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return bool
     */
    public function isSelectable(): bool
    {
        return $this->selectable;
    }

    /**
     * @return bool
     */
    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    /**
     * @return bool
     */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * @return bool
     */
    public function isOrderable(): bool
    {
        return $this->orderable;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * @return bool
     */
    public function isDefaultSelectable(): bool
    {
        return $this->defaultSelectable;
    }
}
