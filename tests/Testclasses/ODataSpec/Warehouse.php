<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $table = 'warehouses';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Stock', source: 'stock')]
    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class, 'warehouse_id');
    }
}

