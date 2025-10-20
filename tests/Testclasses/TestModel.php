<?php

namespace Aqqo\OData\Tests\Testclasses;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

#[ODataProperty('name', searchable: true, filterable: true)]
#[ODataProperty('id', filterable: true)]
#[ODataProperty('description', searchable: true)]
#[ODataProperty('cost', filterable: true)]
#[ODataProperty('age', filterable: true)]
#[ODataProperty('quantity', filterable: true)]
#[ODataProperty('price', filterable: true)]
#[ODataProperty('test', filterable: true)]
#[ODataProperty('status', filterable: true)]
#[ODataProperty('tags', filterable: true)]
#[ODataProperty('is_active', filterable: true)]
#[ODataProperty('is_verified', filterable: true)]
#[ODataProperty('is_admin', filterable: true)]
#[ODataProperty('is_superuser', filterable: true)]
#[ODataProperty('created_at', filterable: true)]
#[ODataProperty('updated_at', filterable: true)]
#[ODataProperty('deleted_at', filterable: true)]
#[ODataProperty('test')]
#[ODataProperty('odatacol', source: 'dbcol')]
#[ODataProperty('start_datetime_utc')]
#[ODataProperty('end_datetime_utc')]
class TestModel extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function oDataDifcolumnResolver()
    {
        return 'source';
    }

    #[ODataRelationship(name: 'relatedModels')]
    public function relatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }

    #[ODataRelationship(name: 'relatedModel')]
    public function relatedModel(): HasOne
    {
        return $this->hasOne(RelatedModel::class);
    }

    public function otherRelatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }

    #[ODataRelationship(name: 'relatedThroughPivotModels')]
    public function relatedThroughPivotModels(): BelongsToMany
    {
        return $this->belongsToMany(RelatedThroughPivotModel::class, 'pivot_models');
    }

    public function relatedThroughPivotModelsWithPivot(): BelongsToMany
    {
        return $this->belongsToMany(RelatedThroughPivotModel::class, 'pivot_models')
            ->withPivot(['location']);
    }

    public function morphModels(): MorphMany
    {
        return $this->morphMany(MorphModel::class, 'parent');
    }

    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }

    public function scopeUser(Builder $query, self $user): Builder
    {
        return $query->where('id', $user->id);
    }

    public function scopeUserInfo(Builder $query, self $user, string $name): Builder
    {
        return $query
            ->where('id', $user->id)
            ->where('name', $name);
    }

    public function scopeCreatedBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('created_at', [
            Carbon::parse($from), Carbon::parse($to),
        ]);
    }
}
