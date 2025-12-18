<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flight extends Model
{
    protected $table = 'flights';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Segments', source: 'segments')]
    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class, 'flight_id');
    }
}

