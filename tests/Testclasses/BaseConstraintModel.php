<?php

namespace Aqqo\OData\Tests\Testclasses;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BaseConstraintModel extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'scope_models'; // Use existing table

    public function newQuery()
    {
        $builder = parent::newQuery();
        // Add constraints immediately so they're counted at line 289
        $builder->where('id', '>', 0);
        $builder->where('name', '!=', '');
        return $builder;
    }
}
