<?php

namespace Aqqo\OData\Tests\Testclasses;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ODataProperty('name')]
#[ODataProperty('cost')]
class NestedRelatedModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    #[ODataProperty(name: 'relatedModel')]
    #[ODataRelationship(name: 'relatedModel')]
    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }
}
