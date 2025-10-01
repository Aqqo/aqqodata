<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Utils\ClassUtils;
use Aqqo\OData\Tests\Testclasses\TestModel;

it('can get short name from object', function () {
    $model = new TestModel();
    $shortName = ClassUtils::getShortName($model);
    
    expect($shortName)->toBe('testmodel');
});

it('caches short names for performance', function () {
    $model1 = new TestModel();
    $model2 = new TestModel();
    
    $shortName1 = ClassUtils::getShortName($model1);
    $shortName2 = ClassUtils::getShortName($model2);
    
    expect($shortName1)->toBe($shortName2);
    expect($shortName1)->toBe('testmodel');
});

it('handles different model classes correctly', function () {
    $testModel = new TestModel();
    $relatedModel = new \Aqqo\OData\Tests\Testclasses\RelatedModel();
    
    $testModelShortName = ClassUtils::getShortName($testModel);
    $relatedModelShortName = ClassUtils::getShortName($relatedModel);
    
    expect($testModelShortName)->toBe('testmodel');
    expect($relatedModelShortName)->toBe('relatedmodel');
    expect($testModelShortName)->not()->toBe($relatedModelShortName);
});

it('returns lowercase short names', function () {
    $model = new TestModel();
    $shortName = ClassUtils::getShortName($model);
    
    expect($shortName)->toBe(strtolower($shortName));
});
