<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('PublishedDate', source: 'published_date', filterable: true)]
class Post extends Model
{
    protected $table = 'posts';
    protected $guarded = [];
    public $timestamps = false;
}

