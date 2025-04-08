<?php

namespace Aqqo\OData\Tests\Testclasses;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ScopeModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public static function boot()
    {
        parent::boot();

        static::addGlobalScope('nameNotTest', function (Builder $builder) {
            $builder->where('name', '<>', 'test');
        });
    }
}