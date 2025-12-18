<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('Country', source: 'country', filterable: true)]
#[ODataProperty('Balance', source: 'balance', filterable: true)]
class Account extends Model
{
    protected $table = 'accounts';
    protected $guarded = [];
    public $timestamps = false;
}

