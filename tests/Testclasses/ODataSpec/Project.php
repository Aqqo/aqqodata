<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ODataProperty('OwnerId', source: 'owner_id', filterable: true)]
class Project extends Model
{
    protected $table = 'projects';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Tasks', source: 'tasks')]
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }
}

