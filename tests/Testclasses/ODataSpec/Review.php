<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('ReviewerId', source: 'reviewer_id', filterable: true)]
#[ODataProperty('Rating', source: 'rating', filterable: true)]
class Review extends Model
{
    protected $table = 'reviews';
    protected $guarded = [];
    public $timestamps = false;
}

