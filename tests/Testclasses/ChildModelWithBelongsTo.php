<?php

namespace Aqqo\OData\Tests\Testclasses;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Child model that extends ParentModelWithBelongsTo to test BelongsTo inheritance.
 */
#[ODataProperty('childProperty', filterable: true)]
class ChildModelWithBelongsTo extends ParentModelWithBelongsTo
{
    use HasFactory;

    protected $table = 'test_models'; // Use the same table

    /**
     * Child model can also have its own relationships
     */
    #[ODataRelationship(name: 'ownRelatedModels')]
    public function ownRelatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }
}
