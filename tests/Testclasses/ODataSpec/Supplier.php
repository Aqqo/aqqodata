<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('Country', source: 'country', filterable: true)]
#[ODataProperty('Rating', source: 'rating', filterable: true)]
class Supplier extends Model
{
    protected $table = 'suppliers';
    protected $guarded = [];
    public $timestamps = false;
}

