<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('ManagerId', source: 'manager_id', filterable: true)]
#[ODataProperty('HireDate', source: 'hire_date', filterable: true)]
#[ODataProperty('FirstName', source: 'first_name', filterable: true, searchable: true)]
#[ODataProperty('LastName', source: 'last_name', filterable: true, searchable: true)]
class Employee extends Model
{
    protected $table = 'employees';
    protected $guarded = [];
    public $timestamps = false;
}

