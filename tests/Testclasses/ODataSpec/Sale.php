<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('Region', source: 'region', filterable: true)]
#[ODataProperty('Year', source: 'year', filterable: true)]
#[ODataProperty('Amount', source: 'amount', filterable: true)]
class Sale extends Model
{
    protected $table = 'sales';
    protected $guarded = [];
    public $timestamps = false;
}

