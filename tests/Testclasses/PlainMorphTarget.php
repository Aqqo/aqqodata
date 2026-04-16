<?php

namespace Aqqo\OData\Tests\Testclasses;

use Illuminate\Database\Eloquent\Model;

/**
 * Morph target with no #[ODataProperty] metadata (tests raw getAttributes fallback).
 */
class PlainMorphTarget extends Model
{
    protected $table = 'related_models';

    protected $guarded = [];

    public $timestamps = false;
}
