<?php

namespace Aqqo\OData\Tests\Unit;

use Aqqo\OData\Exceptions\QueryException;

describe('QueryException', function () {
    
    it('can be created with message only', function () {
        $exception = new QueryException('Something went wrong');
        
        expect($exception->getMessage())->toBe('Something went wrong');
        expect($exception->getCode())->toBe(0);
        expect($exception->getPrevious())->toBeNull();
    });

    it('can be created with message and code', function () {
        $exception = new QueryException('Database error', 500);
        
        expect($exception->getMessage())->toBe('Database error');
        expect($exception->getCode())->toBe(500);
        expect($exception->getPrevious())->toBeNull();
    });

    it('can be created with message, code, and previous exception', function () {
        $previousException = new \Exception('Original error');
        $exception = new QueryException('Query failed', 400, $previousException);
        
        expect($exception->getMessage())->toBe('Query failed');
        expect($exception->getCode())->toBe(400);
        expect($exception->getPrevious())->toBe($previousException);
    });

    it('extends base Exception class', function () {
        $exception = new QueryException('Test error');
        
        expect($exception)->toBeInstanceOf(\Exception::class);
    });

    it('can be thrown and caught', function () {
        expect(function () {
            throw new QueryException('Test error');
        })->toThrow(QueryException::class, 'Test error');
    });

    it('preserves stack trace', function () {
        try {
            throw new QueryException('Test error');
        } catch (QueryException $e) {
            expect($e->getTrace())->toBeArray();
            expect($e->getFile())->toBeString();
            expect($e->getLine())->toBeInt();
        }
    });

    it('can be serialized', function () {
        $exception = new QueryException('Serializable error', 123);
        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);
        
        expect($unserialized->getMessage())->toBe('Serializable error');
        expect($unserialized->getCode())->toBe(123);
    });

    it('handles empty message', function () {
        $exception = new QueryException('');
        
        expect($exception->getMessage())->toBe('');
    });

    it('handles very long messages', function () {
        $longMessage = str_repeat('This is a very long error message. ', 100);
        $exception = new QueryException($longMessage);
        
        expect($exception->getMessage())->toBe($longMessage);
    });

    it('handles special characters in message', function () {
        $message = "Error with special chars: !@#$%^&*()_+-=[]{}|;':\",./<>?";
        $exception = new QueryException($message);
        
        expect($exception->getMessage())->toBe($message);
    });

    it('can be used in exception chaining', function () {
        $originalException = new \PDOException('Database connection failed');
        $queryException = new QueryException('Query execution failed', 0, $originalException);
        
        expect($queryException->getPrevious())->toBe($originalException);
        expect($queryException->getPrevious()->getMessage())->toBe('Database connection failed');
    });
});
