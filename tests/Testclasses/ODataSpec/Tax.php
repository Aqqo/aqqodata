<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('Rate', source: 'rate', filterable: true)]
class Tax extends Model
{
    protected $table = 'taxes';
    protected $guarded = [];
    public $timestamps = false;
}

