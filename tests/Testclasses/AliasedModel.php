<?php

namespace Aqqo\OData\Tests\Testclasses;

use Aqqo\OData\Attributes\ODataProperty;
use Illuminate\Database\Eloquent\Model;

#[ODataProperty('display_name')]
class AliasedModel extends Model
{
    protected $table = 'test_models';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return strtoupper($this->full_name);
    }
}
