<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('DepartureTime', source: 'departure_time', filterable: true)]
#[ODataProperty('ArrivalTime', source: 'arrival_time', filterable: true)]
#[ODataProperty('Delay', source: 'delay_minutes', filterable: true)]
class Segment extends Model
{
    protected $table = 'segments';
    protected $guarded = [];
    public $timestamps = false;
}

