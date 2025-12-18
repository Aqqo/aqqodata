<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('Name', source: 'name', searchable: true, filterable: true)]
class Customer extends Model
{
    protected $table = 'customers';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Orders', source: 'orders')]
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}

