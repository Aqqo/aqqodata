<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('Name', source: 'name', filterable: true)]
class Blog extends Model
{
    protected $table = 'blogs';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Posts', source: 'posts')]
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'blog_id');
    }
}

