<?php

namespace Aqqo\OData\Tests\Testclasses;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('name', searchable: true, filterable: true)]
#[ODataProperty('id', filterable: true)]
class TestModelWithResource extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'test_models'; // Use the same table as TestModel

    // This property will trigger the resource collection transformation
    public $resource = TestResource::class;
}
