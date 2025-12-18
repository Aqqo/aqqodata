<?php

namespace Aqqo\OData\Tests\Feature;

use Aqqo\OData\Tests\Testclasses\TestModel;
use Illuminate\Support\Collection;
use function Aqqo\OData\Tests\Feature\createQueryFromParams;

it('supports deep nested $expand with $filter + $orderby + $select at each level', function () {
    $root = TestModel::factory()->create(['name' => 'Root']);

    $r30 = $root->relatedModels()->create(['name' => 'r30', 'cost' => 30]);
    $r10 = $root->relatedModels()->create(['name' => 'r10', 'cost' => 10]);
    $r20 = $root->relatedModels()->create(['name' => 'r20', 'cost' => 20]);

    $r30->nestedRelatedModels()->createMany([
        ['name' => 'alpha'],
        ['name' => 'zeta'],
        ['name' => 'beta'],
    ]);
    $r20->nestedRelatedModels()->createMany([
        ['name' => 'delta'],
        ['name' => 'gamma'],
    ]);
    $r10->nestedRelatedModels()->createMany([
        ['name' => 'should-not-appear'],
    ]);

    $rows = createQueryFromParams(
        select: 'id,name',
        expand: 'relatedModels($select=name,cost;$filter=cost gt 10;$orderby=cost desc;$expand=nestedRelatedModels($select=name;$orderby=name desc))'
    )->get();

    expect($rows)->toHaveCount(1);

    $payload = $rows->first();
    expect($payload)->toHaveKeys(['id', 'name', 'relatedModels'])
        ->and($payload['name'])->toBe('Root');

    expect($payload['relatedModels'])->toBeInstanceOf(Collection::class);
    $relatedModels = $payload['relatedModels']->values();

    expect($relatedModels)->toHaveCount(2)
        ->and($relatedModels->pluck('cost')->all())->toBe([30, 20]);

    // Nested ordering inside expanded relation
    expect($relatedModels->get(0)['nestedRelatedModels'])->toBeInstanceOf(Collection::class)
        ->and($relatedModels->get(0)['nestedRelatedModels']->pluck('name')->all())->toBe(['zeta', 'beta', 'alpha'])
        ->and($relatedModels->get(1)['nestedRelatedModels'])->toBeInstanceOf(Collection::class)
        ->and($relatedModels->get(1)['nestedRelatedModels']->pluck('name')->all())->toBe(['gamma', 'delta']);
});

it('supports nested expands via relationship source alias (subModels -> nestedRelatedModels)', function () {
    $root = TestModel::factory()->create(['name' => 'Root']);
    $related = $root->relatedModel()->create(['name' => 'related', 'cost' => 1]);

    $related->nestedRelatedModels()->createMany([
        ['name' => 'one'],
        ['name' => 'two'],
    ]);

    $rows = createQueryFromParams(
        select: 'id,name',
        expand: 'relatedModel($select=name;$expand=subModels($select=name;$orderby=name desc))'
    )->get();

    $payload = $rows->first();

    expect($payload)->toHaveKey('relatedModel')
        ->and($payload['relatedModel'])->toHaveKeys(['name', 'nestedRelatedModels']);

    expect($payload['relatedModel']['nestedRelatedModels'])->toBeInstanceOf(Collection::class)
        ->and($payload['relatedModel']['nestedRelatedModels']->pluck('name')->all())->toBe(['two', 'one']);
});

it('handles complex filters with not + any/all + transforms', function () {
    $alice = TestModel::factory()->create(['name' => 'Alice']);
    $alice->relatedModels()->createMany([
        ['name' => 'a1', 'cost' => 100],
    ]);

    $bob = TestModel::factory()->create(['name' => 'Bob']);
    $bob->relatedModels()->createMany([
        ['name' => 'b1', 'cost' => 1],
        ['name' => 'b2', 'cost' => 2],
    ]);

    $charlie = TestModel::factory()->create(['name' => 'Charlie']);
    $charlie->relatedModels()->createMany([
        ['name' => 'c1', 'cost' => 10],
        ['name' => 'c2', 'cost' => 20],
    ]);

    $rows = createQueryFromParams(
        select: 'id,name',
        filter: "not (tolower(name) eq 'alice') and (relatedModels/any(r:r/cost gt 50) or relatedModels/all(r:r/cost gt 5))",
        orderby: 'id asc'
    )->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['name'])->toBe('Charlie');
});

it('supports alternative aggregate syntax any(relation, condition) and all(relation, condition)', function () {
    $m1 = TestModel::factory()->create(['name' => 'M1']);
    $m1->relatedModels()->createMany([
        ['name' => 'r1', 'cost' => 10],
        ['name' => 'r2', 'cost' => 20],
    ]);

    $m2 = TestModel::factory()->create(['name' => 'M2']);
    $m2->relatedModels()->createMany([
        ['name' => 'r3', 'cost' => 5],
    ]);

    $any = createQueryFromParams(
        select: 'name',
        filter: 'any(relatedModels, cost gt 15)',
        orderby: 'name asc'
    )->get();

    expect($any)->toHaveCount(1)
        ->and($any->first()['name'])->toBe('M1');

    $all = createQueryFromParams(
        select: 'name',
        filter: 'all(relatedModels, cost gt 7)',
        orderby: 'name asc'
    )->get();

    expect($all)->toHaveCount(1)
        ->and($all->first()['name'])->toBe('M1');
});

it('parses OData doubled single-quotes correctly in string literals', function () {
    TestModel::factory()->create(['name' => "don't"]);
    TestModel::factory()->create(['name' => "do"]);

    $rows = createQueryFromParams(
        select: 'name',
        filter: "name eq 'don''t'",
        orderby: 'name asc'
    )->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['name'])->toBe("don't");
});

it('handles IN lists containing null alongside normal values', function () {
    TestModel::factory()->create(['name' => null]);
    TestModel::factory()->create(['name' => 'Bob']);
    TestModel::factory()->create(['name' => 'Alice']);

    $rows = createQueryFromParams(
        select: 'name',
        filter: "name in (null, 'Bob')",
        orderby: 'name asc'
    )->get();

    // SQL IN does not match NULL; this should only return 'Bob'
    expect($rows)->toHaveCount(1)
        ->and($rows->first()['name'])->toBe('Bob');
});

it('supports deeply nested lambda filters with alias stripping across relationships', function () {
    $hit = TestModel::factory()->create(['name' => 'Hit']);
    $miss = TestModel::factory()->create(['name' => 'Miss']);

    $rHit = $hit->relatedModels()->create(['name' => 'rel', 'cost' => 1]);
    $rHit->nestedRelatedModels()->createMany([
        ['name' => 'nope'],
        ['name' => 'hit'],
    ]);

    $rMiss = $miss->relatedModels()->create(['name' => 'rel', 'cost' => 1]);
    $rMiss->nestedRelatedModels()->createMany([
        ['name' => 'nope'],
    ]);

    $rows = createQueryFromParams(
        select: 'name',
        filter: "relatedModels/any(r:r/nestedRelatedModels/any(n:n/name eq 'hit'))",
        orderby: 'name asc'
    )->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['name'])->toBe('Hit');
});

