<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Attributes\ODataProperty;

describe('ODataProperty', function () {
    
    it('can be created with basic properties', function () {
        $property = new ODataProperty('name');
        
        expect($property->getName())->toBe('name');
        expect($property->getDescription())->toBeNull();
        expect($property->isSelectable())->toBeTrue();
        expect($property->isFilterable())->toBeTrue();
        expect($property->isSearchable())->toBeFalse();
        expect($property->isOrderable())->toBeTrue();
        expect($property->getSource())->toBeNull();
    });

    it('can be created with all properties', function () {
        $property = new ODataProperty(
            name: 'fullName',
            description: 'The full name of the user',
            selectable: true,
            filterable: false,
            searchable: true,
            orderable: false,
            source: 'full_name'
        );
        
        expect($property->getName())->toBe('fullName');
        expect($property->getDescription())->toBe('The full name of the user');
        expect($property->isSelectable())->toBeTrue();
        expect($property->isFilterable())->toBeFalse();
        expect($property->isSearchable())->toBeTrue();
        expect($property->isOrderable())->toBeFalse();
        expect($property->getSource())->toBe('full_name');
    });

    it('has correct default values', function () {
        $property = new ODataProperty('test');
        
        expect($property->isSelectable())->toBeTrue();
        expect($property->isFilterable())->toBeTrue();
        expect($property->isSearchable())->toBeFalse();
        expect($property->isOrderable())->toBeTrue();
    });

    it('can be configured as non-selectable', function () {
        $property = new ODataProperty('secret', selectable: false);
        
        expect($property->isSelectable())->toBeFalse();
        expect($property->isFilterable())->toBeTrue(); // Still filterable by default
    });

    it('can be configured as non-filterable', function () {
        $property = new ODataProperty('computed', filterable: false);
        
        expect($property->isFilterable())->toBeFalse();
        expect($property->isSelectable())->toBeTrue(); // Still selectable by default
    });

    it('can be configured as searchable', function () {
        $property = new ODataProperty('description', searchable: true);
        
        expect($property->isSearchable())->toBeTrue();
    });

    it('can be configured as non-orderable', function () {
        $property = new ODataProperty('complex', orderable: false);
        
        expect($property->isOrderable())->toBeFalse();
    });

    it('can have a different source than name', function () {
        $property = new ODataProperty('objectName', source: 'object_name');
        
        expect($property->getName())->toBe('objectName');
        expect($property->getSource())->toBe('object_name');
    });

    it('can have description', function () {
        $property = new ODataProperty('email', description: 'User email address');
        
        expect($property->getDescription())->toBe('User email address');
    });

    it('handles empty name', function () {
        $property = new ODataProperty('');
        
        expect($property->getName())->toBe('');
    });

    it('handles special characters in name', function () {
        $property = new ODataProperty('user_name');
        
        expect($property->getName())->toBe('user_name');
    });

    it('can be fully configured for computed fields', function () {
        $property = new ODataProperty(
            name: 'computedField',
            description: 'A computed field that cannot be filtered or ordered',
            selectable: true,
            filterable: false,
            searchable: false,
            orderable: false,
            source: 'computed_value'
        );
        
        expect($property->getName())->toBe('computedField');
        expect($property->getDescription())->toBe('A computed field that cannot be filtered or ordered');
        expect($property->isSelectable())->toBeTrue();
        expect($property->isFilterable())->toBeFalse();
        expect($property->isSearchable())->toBeFalse();
        expect($property->isOrderable())->toBeFalse();
        expect($property->getSource())->toBe('computed_value');
    });
});
