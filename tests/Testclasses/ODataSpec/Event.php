<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('AttendeesCount', source: 'attendees_count', filterable: true)]
#[ODataProperty('OptionalNote', source: 'optional_note', filterable: true)]
class Event extends Model
{
    protected $table = 'events';
    protected $guarded = [];
    public $timestamps = false;
}

