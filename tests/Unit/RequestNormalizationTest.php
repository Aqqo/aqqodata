<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Query;
use Aqqo\OData\Tests\Testclasses\TestModel;
use Illuminate\Http\Request;

it('coerces non-integer skip values to zero', function () {
    $query = new Query(
        TestModel::query(),
        select: false,
        filter: false,
        expand: false,
        search: false,
        skip: false,
        top: false,
        count: false,
        orderby: false,
        request: new Request()
    );

    $reflection = new \ReflectionClass($query);
    $requestProperty = $reflection->getProperty('request');
    $requestProperty->setAccessible(true);
    $requestProperty->setValue($query, new class extends Request {
        public function __construct()
        {
            parent::__construct(['$skip' => 'invalid'], ['$skip' => 'invalid'], [], [], [], []);
        }

        public function integer($key, $default = 0)
        {
            if ($key === '$skip') {
                return 'invalid';
            }

            return parent::integer($key, $default);
        }
    });

    $query->addSkip();

    $subjectProperty = $reflection->getProperty('subject');
    $subjectProperty->setAccessible(true);
    $subject = $subjectProperty->getValue($query);

    expect($subject->getQuery()->offset)->toBe(0);
});

it('falls back to the configured default top when value is non-integer', function () {
    config()->set('odata.top.min', 1);
    config()->set('odata.top.default', 25);
    config()->set('odata.top.max', 50);

    $query = new Query(
        TestModel::query(),
        select: false,
        filter: false,
        expand: false,
        search: false,
        skip: false,
        top: false,
        count: false,
        orderby: false,
        request: new Request()
    );

    $reflection = new \ReflectionClass($query);
    $requestProperty = $reflection->getProperty('request');
    $requestProperty->setAccessible(true);
    $requestProperty->setValue($query, new class extends Request {
        public function __construct()
        {
            parent::__construct(['$top' => 'invalid'], ['$top' => 'invalid'], [], [], [], []);
        }

        public function integer($key, $default = 0)
        {
            if ($key === '$top') {
                return 'invalid';
            }

            return parent::integer($key, $default);
        }
    });

    $query->addTop();

    $subjectProperty = $reflection->getProperty('subject');
    $subjectProperty->setAccessible(true);
    $subject = $subjectProperty->getValue($query);

    expect($subject->getQuery()->limit)->toBe(25);
});

it('toggles the count flag based on the incoming request', function ($countValue, bool $expected) {
    $query = new Query(
        TestModel::query(),
        select: false,
        filter: false,
        expand: false,
        search: false,
        skip: false,
        top: false,
        count: false,
        orderby: false,
        request: new Request()
    );

    $reflection = new \ReflectionClass($query);
    $requestProperty = $reflection->getProperty('request');
    $requestProperty->setAccessible(true);
    $requestProperty->setValue($query, new Request(['$count' => $countValue]));

    $query->addCount();

    $countFlag = $reflection->getProperty('add_count');
    $countFlag->setAccessible(true);

    expect($countFlag->getValue($query))->toBe($expected);
})->with([
    'truthy boolean' => [true, true],
    'explicit false' => [false, false],
    'null default' => [null, false],
]);
