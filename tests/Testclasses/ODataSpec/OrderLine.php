<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('Price', source: 'price', filterable: true)]
class OrderLine extends Model
{
    protected $table = 'order_lines';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Order', source: 'order')]
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    #[ODataRelationship(name: 'Product', source: 'product')]
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

