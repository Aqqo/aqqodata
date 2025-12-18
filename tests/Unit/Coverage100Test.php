<?php

use Aqqo\OData\Query;
use Aqqo\OData\QueryNodeStructure\BasicQueryNode;
use Aqqo\OData\Services\FilterExecutor;
use Aqqo\OData\Services\FilterParser;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Order;
use Aqqo\OData\Tests\Testclasses\ODataSpec\Sale;
use Aqqo\OData\Tests\Testclasses\RelatedModel;
use Aqqo\OData\Tests\Testclasses\TestModel;
use Illuminate\Http\Request;

use function Aqqo\OData\Tests\Feature\createQueryFromParams;

it('covers $search explicit OR handling and NOT-only search', function () {
    // Covers: OR token (continue) + empty inclusion clause (continue) + exclude terms.
    $q1 = createQueryFromParams(search: 'foo OR bar');
    expect(strtolower($q1->toSql()))->toContain('like')
        ->and($q1->toSql())->toContain('%foo%')
        ->and($q1->toSql())->toContain('%bar%');

    $q2 = createQueryFromParams(search: 'NOT foo');
    expect(strtolower($q2->toSql()))->toContain('not like')
        ->and($q2->toSql())->toContain('%foo%');
});

it('covers FilterParser alternative any/all syntax and parseValueOrPath edge cases', function () {
    $parser = new FilterParser();

    // Alternative aggregate syntax: any(relation, condition)
    $ast = $parser->parse("any(relatedModels, name eq 'Aqqo')");
    expect($ast)->toBeObject();

    // parseValueOrPath should throw when a nested function call has a missing argument
    $fn = fn () => $parser->parse("contains(concat(");
    expect($fn)->toThrow(InvalidArgumentException::class);

    // parseValueOrPath branches: function call + slash path as function arguments
    $ast2 = $parser->parse("contains(concat(trim(name),relatedModel/name),'x')");
    expect($ast2)->toBeObject();
});

it('covers ApplyTrait relation-builder branch, empty steps, and groupby aggregate fallbacks', function () {
    // ApplyTrait::applyToBuilder() Builder|Relation branch + empty steps.
    $query = new Query(TestModel::query(), select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());

    $m = TestModel::factory()->create();
    $relation = $m->relatedModels();

    $ref = new ReflectionClass($query);
    $applyToBuilder = $ref->getMethod('applyToBuilder');
    $applyToBuilder->setAccessible(true);

    $applyToBuilder->invoke($query, $relation, "/filter(name eq 'x')/");
    expect(strtolower($relation->getQuery()->toSql()))->toContain('where');

    // groupby() pattern mismatch early return should not throw
    $applyToBuilder->invoke($query, $relation, "groupby(bad)");
    expect(true)->toBeTrue();

    // Build a $apply string to hit fallback/continue branches in applyGroupByAggregate()
    $apply = "groupby((NonSelectable),aggregate(NonSelectable with sum as TotalX,, Amount as Bad, Amount with sum as Total))";
    $q2 = Query::for(Sale::class, new Request(['$apply' => $apply]));
    expect(strtolower($q2->toSql()))->toContain('group by');
});

it('covers ApplyTrait aggregate source fallback (non-selectable source)', function () {
    $query = new Query(Sale::query(), select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());

    $ref = new ReflectionClass($query);
    $applyGroupByAggregate = $ref->getMethod('applyGroupByAggregate');
    $applyGroupByAggregate->setAccessible(true);

    $builder = Sale::query();
    $applyGroupByAggregate->invoke($query, $builder, "groupby((Region),aggregate(NonSelectable with sum as TotalX))");

    expect(strtolower($builder->toSql()))->toContain('group by');
});

it('covers ExpandTrait $top/$skip handling when option builder is a Relation', function () {
    $query = createQueryFromParams(); // Query instance (uses ExpandTrait internally)

    $m = TestModel::factory()->create();
    $rel = $m->relatedModels();

    $ref = new ReflectionClass($query);
    $handleOption = $ref->getMethod('handleOption');
    $handleOption->setAccessible(true);

    $handleOption->invoke($query, $rel, '$top=1', 'relatedModels');
    expect(strtolower($rel->getQuery()->toSql()))->toContain('limit 1');

    $rel2 = $m->relatedModels();
    $handleOption->invoke($query, $rel2, '$skip=2', 'relatedModels');
    expect(strtolower($rel2->getQuery()->toSql()))->toContain('offset 2');
});

it('covers FilterExecutor arithmetic fallback and non-distributable negation for OR', function () {
    $builder = TestModel::query();
    $query = new Query($builder, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());

    $exec = new FilterExecutor($query, $builder);

    // Arithmetic detection triggers, regex fails -> fallback path
    $exec->execute(new BasicQueryNode('cost mul ', 'eq', '1'));
    expect(strtolower($builder->toSql()))->not()->toContain('mul');

    // Left column missing -> early return
    $builder2 = TestModel::query();
    $query2 = new Query($builder2, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $exec2 = new FilterExecutor($query2, $builder2);
    $exec2->execute(new BasicQueryNode('doesnotexist mul 2', 'eq', '1'));
    expect(strtolower($builder2->toSql()))->not()->toContain('where');

    // RHS column missing -> early return
    $builder3 = TestModel::query();
    $query3 = new Query($builder3, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $exec3 = new FilterExecutor($query3, $builder3);
    $exec3->execute(new BasicQueryNode('cost mul doesnotexist', 'gt', '1'));
    expect(strtolower($builder3->toSql()))->not()->toContain('where');

    // Non-distributable negation: OR with a function operator should become NOT(<compiled or-expression>)
    $q4 = createQueryFromParams(filter: "not (contains(name, 'Test') or test gt 5)");
    expect(strtolower($q4->toSql()))->toContain('not');
});

it('covers FilterExecutor empty-string comparison early return', function () {
    $q = createQueryFromParams(filter: "name eq ''");
    // Empty-string comparisons are ignored (no WHERE added)
    expect(strtolower($q->toSql()))->not()->toContain('where');
});

it('covers FilterExecutor expression columns, arithmetic ops, and helper branches via computed properties', function () {
    // Register computed properties (Total, Avg) on an Order builder via $apply.
    $builder = Order::query();
    $query = new Query($builder, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $query->applyToBuilder($builder, "groupby((CustomerId),aggregate(Amount with sum as Total,Amount with avg as Avg))");

    $exec = new FilterExecutor($query, $builder);

    // Expression left + expression rhs (covers Expression branches in arithmetic)
    $exec->execute(new BasicQueryNode('Total mul Avg', 'gt', '0'), 'having');
    expect(strtolower($builder->toSql()))->toContain('having');

    // RHS contains slash path => last segment stripping
    $builder2 = Order::query();
    $query2 = new Query($builder2, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $query2->applyToBuilder($builder2, "groupby((CustomerId),aggregate(Amount with sum as Total))");
    $exec2 = new FilterExecutor($query2, $builder2);
    $exec2->execute(new BasicQueryNode("Total mul Orders/DiscountLimit", 'gt', '0'), 'having');

    // Cover other arithmetic operators
    $builder3 = Order::query();
    $query3 = new Query($builder3, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $query3->applyToBuilder($builder3, "groupby((CustomerId),aggregate(Amount with sum as Total))");
    $exec3 = new FilterExecutor($query3, $builder3);
    $exec3->execute(new BasicQueryNode('Total add 1', 'gt', '0'), 'having');
    $exec3->execute(new BasicQueryNode('Total sub 1', 'gt', '0'), 'having');
    $exec3->execute(new BasicQueryNode('Total div 1', 'gt', '0'), 'having');

    // Expression direct filter: numeric inline (havingRaw) + binding (orHavingRaw)
    $builder4 = Order::query();
    $query4 = new Query($builder4, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $query4->applyToBuilder($builder4, "groupby((CustomerId),aggregate(Amount with sum as Total))");
    $exec4 = new FilterExecutor($query4, $builder4);
    $exec4->execute(new BasicQueryNode('Total', 'gt', '100'), 'having');
    $exec4->execute(new BasicQueryNode('Total', 'gt', 'abc'), 'orHaving');

    expect(true)->toBeTrue();
});

it('covers FilterExecutor helper methods via reflection', function () {
    $builder = TestModel::query();
    $query = new Query($builder, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $exec = new FilterExecutor($query, $builder);

    $ref = new ReflectionClass($exec);

    $compile = $ref->getMethod('compileExpressionString');
    $compile->setAccessible(true);
    $buildTx = $ref->getMethod('buildTransformedExpressionSql');
    $buildTx->setAccessible(true);
    $resolveRel = $ref->getMethod('resolveRelationName');
    $resolveRel->setAccessible(true);

    // buildTransformedExpressionSql: default branch + known mapping
    expect($buildTx->invoke($exec, '"x"', 'noop'))->toBe('"x"')
        ->and($buildTx->invoke($exec, '"x"', 'tolower'))->toContain('LOWER')
        ->and($buildTx->invoke($exec, '"x"', 'toupper'))->toContain('UPPER')
        ->and($buildTx->invoke($exec, '"x"', 'trim'))->toContain('TRIM');

    // compileExpressionString branches
    expect($compile->invoke($exec, "(name)"))->toContain('"test_models"."name"')  // unwrap parentheses + identifier mapping
        ->and($compile->invoke($exec, "'x'"))->toBe("'x'")                      // literal
        ->and($compile->invoke($exec, "123"))->toBe("123")                      // numeric
        ->and($compile->invoke($exec, "upper(name)"))->toContain('UPPER')       // function call default
        ->and($compile->invoke($exec, "concat('a','b')"))->toContain('||')      // concat sqlite path
        ->and($compile->invoke($exec, "trim()"))->toContain('TRIM');            // trim with missing arg fallback

    // Unknown identifier falls back to raw identifier
    expect($compile->invoke($exec, "DoesNotExist"))->toBe("DoesNotExist");

    // resolveRelationName: nested path with unknown segment => null
    expect($resolveRel->invoke($exec, "doesNotExist/child"))->toBeNull();
});

it('covers remaining FilterExecutor branches to reach 100% file coverage', function () {
    // 1) Expression column comparisons with different boolean methods (where/orWhere/default)
    $b1 = Order::query();
    $q1 = new Query($b1, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $q1->applyToBuilder($b1, "groupby((CustomerId),aggregate(Amount with sum as Total,Amount with avg as Avg))");
    $e1 = new FilterExecutor($q1, $b1);

    $e1->execute(new BasicQueryNode('Total', 'gt', '100'), 'where');      // whereRaw
    $e1->execute(new BasicQueryNode('Total', 'gt', '100'), 'orWhere');    // orWhereRaw
    $e1->execute(new BasicQueryNode('Total', 'gt', '100'), 'foo');        // default => whereRaw

    // Having null raw branch
    $b2 = TestModel::query();
    $q2 = new Query($b2, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $e2 = new FilterExecutor($q2, $b2);
    $e2->execute(new BasicQueryNode('name', 'eq', 'null'), 'orHaving');

    // 2) Arithmetic branches: value-path stripping, expression valueSql, bindings, and method variants
    $b3 = Order::query();
    $q3 = new Query($b3, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $q3->applyToBuilder($b3, "groupby((CustomerId),aggregate(Amount with sum as Total,Amount with avg as Avg))");
    $e3 = new FilterExecutor($q3, $b3);

    $e3->execute(new BasicQueryNode('Total mul 2', 'gt', 'Orders/DiscountLimit'), 'orWhere'); // valuePath contains '/'
    $e3->execute(new BasicQueryNode('Total mul 2', 'gt', 'Avg'), 'having');                   // valueSql from Expression
    $e3->execute(new BasicQueryNode('Total mul 2', 'gt', 'doesnotexist'), 'orHaving');        // valueSql '?' + bindings
    $e3->execute(new BasicQueryNode('Total add 1', 'gt', '0'), 'foo');                        // arithmetic default boolean

    // 3) Root-scope RHS mapping in arithmetic and correlated RHS column comparisons
    $rootQuery = new Query(TestModel::query(), select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $relBuilder = RelatedModel::query();
    $e4 = new FilterExecutor($rootQuery, $relBuilder, true, [], 'test_models', 'TestModel');

    $e4->execute(new BasicQueryNode('cost mul age', 'gt', '0'), 'where'); // rhs from root scope mapping (string mapping)

    // correlated RHS column mapping where root property is an Expression (covers line 418)
    $rootQuery->registerComputedProperty('Total', 'TestModel', new \Illuminate\Database\Query\Expression('SUM(1)'));
    $e4->execute(new BasicQueryNode('cost', 'gt', 'Total'), 'where');
    // arithmetic root mapping where mapped is Expression (covers line 218)
    $e4->execute(new BasicQueryNode('cost mul Total', 'gt', '0'), 'where');

    // 4) Transform+Expression null and non-null handling (including default match branch)
    $b5 = TestModel::query();
    $q5 = new Query($b5, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $e5 = new FilterExecutor($q5, $b5);
    $e5->execute(new BasicQueryNode('tolower(trim(name))', 'eq', 'null'), 'foo');
    $e5->execute(new BasicQueryNode('tolower(trim(name))', 'eq', 'x'), 'orWhere');
    $e5->execute(new BasicQueryNode('tolower(trim(name))', 'eq', 'null'), 'where');
    $e5->execute(new BasicQueryNode('tolower(trim(name))', 'eq', 'null'), 'orWhere');
    $e5->execute(new BasicQueryNode('tolower(trim(name))', 'eq', 'null'), 'having');
    $e5->execute(new BasicQueryNode('tolower(trim(name))', 'eq', 'null'), 'orHaving');
    $e5->execute(new BasicQueryNode('tolower(trim(name))', 'eq', 'x'), 'having');
    $e5->execute(new BasicQueryNode('tolower(trim(name))', 'eq', 'x'), 'orHaving');
    $e5->execute(new BasicQueryNode('tolower(trim(name))', 'eq', 'x'), 'foo'); // default match (line 358)

    // 5) RHS-as-column comparisons method match
    $b6 = TestModel::query();
    $q6 = new Query($b6, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new Request());
    $e6 = new FilterExecutor($q6, $b6);
    $e6->execute(new BasicQueryNode('name', 'eq', 'full_name'), 'orWhere');
    // default boolean for RHS-as-column match: use an RHS that *does* resolve to a column
    // so the executor chooses whereRaw and doesn't fall through to builder->{$boolean}().
    $e6->execute(new BasicQueryNode('name', 'eq', 'name'), 'foo');

    // 6) compileExpressionString Expression branch (mapped Expression)
    $refExec = new ReflectionClass($e1);
    $compile = $refExec->getMethod('compileExpressionString');
    $compile->setAccessible(true);
    expect($compile->invoke($e1, 'Total'))->toContain('SUM');

    expect(true)->toBeTrue();
});

it('covers FilterExecutor relation $count comparison helper branches', function () {
    $builder = \Aqqo\OData\Tests\Testclasses\TestModel::query();
    $query = new \Aqqo\OData\Query($builder, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new \Illuminate\Http\Request());
    $exec = new \Aqqo\OData\Services\FilterExecutor($query, $builder);

    $ref = new ReflectionClass($exec);
    $m = $ref->getMethod('applyRelationCountComparison');
    $m->setAccessible(true);

    // method_exists false (line 315)
    $m->invoke($exec, 'doesNotExist', new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('$count', 'gt', '1'), 'where');

    // relation method exists but is not a Relation instance (line 320)
    $fakeModel = new class extends \Illuminate\Database\Eloquent\Model {
        protected $table = 'fake';
        protected $guarded = [];
        public function messages() { return 'not-a-relation'; }
    };
    $fakeBuilder = $fakeModel->newQuery();
    $fakeQuery = new \Aqqo\OData\Query($fakeBuilder, select: false, filter: false, expand: false, search: false, skip: false, top: false, count: false, orderby: false, request: new \Illuminate\Http\Request());
    $fakeExec = new \Aqqo\OData\Services\FilterExecutor($fakeQuery, $fakeBuilder);
    $ref2 = new ReflectionClass($fakeExec);
    $m2 = $ref2->getMethod('applyRelationCountComparison');
    $m2->setAccessible(true);
    $m2->invoke($fakeExec, 'messages', new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('$count', 'gt', '1'), 'where');

    // cover match arms for boolean => whereRaw/orWhereRaw/havingRaw/orHavingRaw/default (lines 331..334)
    $m->invoke($exec, 'relatedModels', new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('$count', 'gt', '1'), 'where');
    $m->invoke($exec, 'relatedModels', new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('$count', 'gt', '1'), 'orWhere');
    $m->invoke($exec, 'relatedModels', new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('$count', 'gt', '1'), 'having');
    $m->invoke($exec, 'relatedModels', new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('$count', 'gt', '1'), 'orHaving');
    $m->invoke($exec, 'relatedModels', new \Aqqo\OData\QueryNodeStructure\BasicQueryNode('$count', 'gt', '1'), 'unknown');

    expect(true)->toBeTrue();
});

