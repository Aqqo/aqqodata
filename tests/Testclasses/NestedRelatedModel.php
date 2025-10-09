<?php

namespace Aqqo\OData\Tests\Testclasses;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ODataProperty('name')]
#[ODataProperty('cost')]
#[ODataProperty('status')]
class NestedRelatedModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    #[ODataProperty(name: 'relatedModel')]
    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }
}
