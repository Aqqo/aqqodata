<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('Body', source: 'body', searchable: true, filterable: true)]
#[ODataProperty('CreatedAt', source: 'created_at', filterable: true)]
class Message extends Model
{
    protected $table = 'messages';
    protected $guarded = [];
    public $timestamps = false;
}

