<?php

namespace Aqqo\OData\Tests\Testclasses;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ODataProperty('name', searchable: true, filterable: true)]
#[ODataProperty('id', filterable: true)]
class ParentModelWithBelongsTo extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'test_models'; // Use the same table as TestModel

    #[ODataRelationship(name: 'parentModel')]
    public function parentModel(): BelongsTo
    {
        return $this->belongsTo(TestModel::class, 'parent_id');
    }

    #[ODataRelationship(name: 'childModels')]
    public function childModels(): HasMany
    {
        return $this->hasMany(TestModel::class, 'parent_id');
    }
}
