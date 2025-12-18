<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('CustomerId', source: 'customer_id', filterable: true)]
#[ODataProperty('Amount', source: 'amount', filterable: true)]
#[ODataProperty('DiscountLimit', source: 'discount_limit', filterable: true)]
#[ODataProperty('CreatedAt', source: 'created_at', filterable: true)]
class Order extends Model
{
    protected $table = 'orders';
    protected $guarded = [];

    #[ODataRelationship(name: 'Customer', source: 'customer')]
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    #[ODataRelationship(name: 'OrderLines', source: 'orderLines')]
    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'order_id');
    }
}

