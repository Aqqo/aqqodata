<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('Name', source: 'name', filterable: true)]
class Category extends Model
{
    protected $table = 'categories';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Parent', source: 'parent')]
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}

