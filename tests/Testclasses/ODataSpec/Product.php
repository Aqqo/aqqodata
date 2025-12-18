<?php

namespace Aqqo\OData\Tests\Testclasses\ODataSpec;

use Aqqo\OData\Attributes\ODataProperty;
use Aqqo\OData\Attributes\ODataRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ODataProperty('Id', source: 'id', filterable: true)]
#[ODataProperty('Name', source: 'name', filterable: true)]
#[ODataProperty('CategoryId', source: 'category_id', filterable: true)]
class Product extends Model
{
    protected $table = 'products';
    protected $guarded = [];
    public $timestamps = false;

    #[ODataRelationship(name: 'Category', source: 'category')]
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_int_id');
    }

    #[ODataRelationship(name: 'Suppliers', source: 'suppliers')]
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier', 'product_id', 'supplier_id');
    }

    #[ODataRelationship(name: 'Reviews', source: 'reviews')]
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id');
    }
}

