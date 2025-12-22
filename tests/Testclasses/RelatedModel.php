<?php

namespace Aqqo\OData\Tests\Testclasses;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ODataProperty('name')]
#[ODataProperty('cost')]
#[ODataProperty('status')]
class RelatedModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function testModel(): BelongsTo
    {
        return $this->belongsTo(TestModel::class);
    }

    #[ODataRelationship(name: 'nestedRelatedModels')]
    public function nestedRelatedModels(): HasMany
    {
        return $this->hasMany(NestedRelatedModel::class);
    }

    #[ODataRelationship(name: 'subModels', source: 'nestedRelatedModels')]
    public function subModels(): HasMany
    {
        return $this->nestedRelatedModels();
    }

    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }
}
