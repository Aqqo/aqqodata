<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Taxes', source: 'taxes')]
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'invoice_item_id');
    }
}

