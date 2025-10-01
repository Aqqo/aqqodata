<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Attributes\ODataRelationship;

describe('ODataRelationship', function () {
    
    it('can be created with basic properties', function () {
        $relationship = new ODataRelationship('relatedModel');
        
        expect($relationship->getName())->toBe('relatedModel');
        expect($relationship->getDescription())->toBeNull();
        expect($relationship->getSource())->toBeNull();
    });

    it('can be created with all properties', function () {
        $relationship = new ODataRelationship(
            name: 'userPosts',
            description: 'Posts created by the user',
            source: 'posts'
        );
        
        expect($relationship->getName())->toBe('userPosts');
        expect($relationship->getDescription())->toBe('Posts created by the user');
        expect($relationship->getSource())->toBe('posts');
    });

    it('can have description without source', function () {
        $relationship = new ODataRelationship(
            name: 'comments',
            description: 'Comments on this post'
        );
        
        expect($relationship->getName())->toBe('comments');
        expect($relationship->getDescription())->toBe('Comments on this post');
        expect($relationship->getSource())->toBeNull();
    });

    it('can have source without description', function () {
        $relationship = new ODataRelationship(
            name: 'author',
            source: 'user'
        );
        
        expect($relationship->getName())->toBe('author');
        expect($relationship->getDescription())->toBeNull();
        expect($relationship->getSource())->toBe('user');
    });

    it('handles empty name', function () {
        $relationship = new ODataRelationship('');
        
        expect($relationship->getName())->toBe('');
    });

    it('handles special characters in name', function () {
        $relationship = new ODataRelationship('user_posts');
        
        expect($relationship->getName())->toBe('user_posts');
    });

    it('handles long descriptions', function () {
        $longDescription = 'This is a very long description that explains the relationship between the user and their posts in great detail.';
        $relationship = new ODataRelationship('posts', description: $longDescription);
        
        expect($relationship->getDescription())->toBe($longDescription);
    });

    it('handles empty description', function () {
        $relationship = new ODataRelationship('posts', description: '');
        
        expect($relationship->getDescription())->toBe('');
    });

    it('handles empty source', function () {
        $relationship = new ODataRelationship('posts', source: '');
        
        expect($relationship->getSource())->toBe('');
    });

    it('can represent one-to-one relationships', function () {
        $relationship = new ODataRelationship(
            name: 'profile',
            description: 'User profile information',
            source: 'userProfile'
        );
        
        expect($relationship->getName())->toBe('profile');
        expect($relationship->getDescription())->toBe('User profile information');
        expect($relationship->getSource())->toBe('userProfile');
    });

    it('can represent one-to-many relationships', function () {
        $relationship = new ODataRelationship(
            name: 'orders',
            description: 'Orders placed by the user',
            source: 'userOrders'
        );
        
        expect($relationship->getName())->toBe('orders');
        expect($relationship->getDescription())->toBe('Orders placed by the user');
        expect($relationship->getSource())->toBe('userOrders');
    });

    it('can represent many-to-many relationships', function () {
        $relationship = new ODataRelationship(
            name: 'tags',
            description: 'Tags associated with the post',
            source: 'postTags'
        );
        
        expect($relationship->getName())->toBe('tags');
        expect($relationship->getDescription())->toBe('Tags associated with the post');
        expect($relationship->getSource())->toBe('postTags');
    });
});
