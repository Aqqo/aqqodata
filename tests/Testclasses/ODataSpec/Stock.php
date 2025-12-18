<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ODataProperty('Quantity', source: 'quantity', filterable: true)]
class Stock extends Model
{
    protected $table = 'stocks';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Product', source: 'product')]
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

