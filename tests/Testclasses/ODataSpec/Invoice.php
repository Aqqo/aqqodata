<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ODataProperty('TotalAmount', source: 'total_amount', filterable: true)]
#[ODataProperty('CreditLimit', source: 'credit_limit', filterable: true)]
class Invoice extends Model
{
    protected $table = 'invoices';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Items', source: 'items')]
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }
}

