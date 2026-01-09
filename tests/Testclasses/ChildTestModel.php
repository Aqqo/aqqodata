<?php

namespace Aqqo\OData\Tests\Testclasses;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Child model that extends TestModel to test nested model inheritance.
 * This model should inherit all properties and relationships from TestModel.
 */
#[ODataProperty('childProperty', filterable: true)]
class ChildTestModel extends TestModel
{
    use HasFactory;

    protected $table = 'test_models'; // Use the same table as TestModel

    /**
     * Child model can also have its own relationships
     */
    #[ODataRelationship(name: 'childRelatedModels')]
    public function childRelatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }
}
