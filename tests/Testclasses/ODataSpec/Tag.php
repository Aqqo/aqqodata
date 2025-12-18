<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

// NOTE: the query uses "Tags/any(t:t eq 'research')" where the lambda parameter is used
// directly as the value. Our current implementation treats it like a column named "t".
#[ODataProperty('t', source: 't', filterable: true)]
class Tag extends Model
{
    protected $table = 'tags';
    protected $guarded = [];
    public $timestamps = false;
}

