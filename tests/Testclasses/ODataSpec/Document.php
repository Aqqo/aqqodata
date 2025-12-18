<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ODataProperty('Body', source: 'body', searchable: true, filterable: true)]
class Document extends Model
{
    protected $table = 'documents';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Tags', source: 'tags')]
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'document_id');
    }
}

