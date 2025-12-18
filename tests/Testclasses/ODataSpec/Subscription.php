<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('Status', source: 'status', filterable: true)]
#[ODataProperty('RenewalDate', source: 'renewal_date', filterable: true)]
class Subscription extends Model
{
    protected $table = 'subscriptions';
    protected $guarded = [];
    public $timestamps = false;
}

