# Aqqo OData API Documentation

This document provides detailed information about the Aqqo OData package's API and usage examples.

## Table of Contents

1. [Query Building](#query-building)
2. [Filtering](#filtering)
3. [Expanding Relations](#expanding-relations)
4. [Sorting](#sorting)
5. [Pagination](#pagination)
6. [Custom Filters](#custom-filters)
7. [Error Handling](#error-handling)

## Query Building

### Basic Query

```php
use Aqqo\OData\Query;

// Create a new query builder
$query = Query::for(User::class);

// Execute the query
$users = $query->get();
```

### Chaining Methods

```php
$users = Query::for(User::class)
    ->where('active', true)
    ->orderBy('name')
    ->get();
```

## Filtering

### Basic Filters

```php
// URL: /users?filter[name]=John
$users = Query::for(User::class)->get();

// URL: /users?filter[age]=25
$users = Query::for(User::class)->get();
```

### Comparison Operators

```php
// URL: /users?filter[age][gt]=18
// Greater than
$users = Query::for(User::class)->get();

// URL: /users?filter[age][lt]=65
// Less than
$users = Query::for(User::class)->get();

// URL: /users?filter[age][gte]=18
// Greater than or equal
$users = Query::for(User::class)->get();

// URL: /users?filter[age][lte]=65
// Less than or equal
$users = Query::for(User::class)->get();
```

### Logical Operators

```php
// URL: /users?filter[or][0][age][gt]=18&filter[or][1][status]=active
// OR condition
$users = Query::for(User::class)->get();

// URL: /users?filter[and][0][age][gt]=18&filter[and][1][status]=active
// AND condition
$users = Query::for(User::class)->get();
```

### Like Operator

```php
// URL: /users?filter[name][like]=John%
// Starts with
$users = Query::for(User::class)->get();

// URL: /users?filter[name][like]=%John
// Ends with
$users = Query::for(User::class)->get();

// URL: /users?filter[name][like]=%John%
// Contains
$users = Query::for(User::class)->get();
```

## Expanding Relations

### Basic Expansion

```php
// URL: /users?include=posts
$users = Query::for(User::class)->get();
```

### Nested Expansion

```php
// URL: /users?include=posts.comments
$users = Query::for(User::class)->get();
```

### Multiple Relations

```php
// URL: /users?include=posts,profile,settings
$users = Query::for(User::class)->get();
```

## Sorting

### Basic Sorting

```php
// URL: /users?sort=name
// Ascending order
$users = Query::for(User::class)->get();

// URL: /users?sort=-name
// Descending order
$users = Query::for(User::class)->get();
```

### Multiple Sort Fields

```php
// URL: /users?sort=-created_at,name
// Sort by created_at DESC, then name ASC
$users = Query::for(User::class)->get();
```

## Pagination

### Basic Pagination

```php
// URL: /users?page[number]=1&page[size]=10
$users = Query::for(User::class)->get();
```

### Cursor Pagination

```php
// URL: /users?page[cursor]=eyJjcmVhdGVkX2F0IjoiMjAyNC0wMy0yMFQxMjowMDowMCJ9
$users = Query::for(User::class)->get();
```

## Custom Filters

### Creating Custom Filters

```php
use Aqqo\OData\Attributes\Filterable;

class User extends Model
{
    #[Filterable]
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    #[Filterable]
    public function scopeAgeRange($query, $min, $max)
    {
        return $query->whereBetween('age', [$min, $max]);
    }
}
```

### Using Custom Filters

```php
// URL: /users?filter[active]=true
$users = Query::for(User::class)->get();

// URL: /users?filter[ageRange][0]=18&filter[ageRange][1]=65
$users = Query::for(User::class)->get();
```

## Error Handling

### Catching Exceptions

```php
use Aqqo\OData\Exceptions\QueryException;

try {
    $users = Query::for(User::class)->get();
} catch (QueryException $e) {
    // Handle query building errors
    Log::error('Query building failed: ' . $e->getMessage());
    throw new HttpException(400, 'Invalid query parameters');
}
```

### Validation Errors

```php
use Aqqo\OData\Exceptions\ValidationException;

try {
    $users = Query::for(User::class)->get();
} catch (ValidationException $e) {
    // Handle validation errors
    return response()->json([
        'errors' => $e->getErrors()
    ], 422);
}
```

## Best Practices

1. **Query Optimization**
   - Use specific filters instead of broad ones
   - Limit the number of relations being expanded
   - Use appropriate indexes for filtered fields

2. **Security**
   - Always validate and sanitize input
   - Use parameter binding
   - Implement proper access control

3. **Performance**
   - Use pagination for large result sets
   - Cache frequently used queries
   - Monitor query execution time

4. **Maintenance**
   - Document custom filters
   - Keep filter logic simple and reusable
   - Follow consistent naming conventions 