<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('FirstName', source: 'first_name', filterable: true, searchable: true)]
#[ODataProperty('LastName', source: 'last_name', filterable: true, searchable: true)]
class User extends Model
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;
}

