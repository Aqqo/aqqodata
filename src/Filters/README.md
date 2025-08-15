# Object-Oriented Filter System

This new filter system replaces the complex string parsing approach with a clean, object-oriented design that supports nested filters and is much easier to understand and extend.

## Overview

The new system consists of:

- **FilterInterface**: Base interface for all filters
- **AbstractFilter**: Base class for simple filters
- **SimpleFilter**: For basic field comparisons (`name eq 'test'`)
- **LogicalFilter**: For AND/OR operations (`(name eq 'test') OR (age gt 18)`)
- **RelationshipFilter**: For relationship operations (`any(orders, total gt 100)`)
- **FunctionFilter**: For function-based filters (`contains(name, 'john')`)
- **FilterParser**: Converts OData filter strings to filter objects
- **FilterBuilder**: Fluent interface for building filters programmatically
- **FilterTraitNew**: Clean trait that uses the new filter objects

## Usage Examples

### 1. Using the New Trait

Replace your old `FilterTrait` with `FilterTraitNew`:

```php
use Aqqo\OData\Traits\FilterTraitNew;

class YourController
{
    use FilterTraitNew;
    
    // The trait will automatically handle OData filter strings
    // like: "name eq 'test' and age gt 18"
    // or: "(name eq 'test') OR (contains(description, 'important'))"
}
```

### 2. Programmatic Filter Creation

Use the `FilterBuilder` to create filters programmatically:

```php
use Aqqo\OData\Filters\FilterBuilder;

// Simple filter
$filter = FilterBuilder::create()
    ->eq('name', 'John')
    ->gt('age', 18)
    ->build();

// Complex nested filter
$filter = FilterBuilder::create()
    ->and(function($builder) {
        $builder->eq('status', 'active')
                ->or(function($builder) {
                    $builder->contains('name', 'john')
                            ->contains('name', 'jane');
                });
    })
    ->any('orders', function($builder) {
        $builder->gt('total', 100);
    })
    ->build();
```

### 3. Direct Filter Object Creation

Create filters directly:

```php
use Aqqo\OData\Filters\SimpleFilter;
use Aqqo\OData\Filters\LogicalFilter;
use Aqqo\OData\Filters\FunctionFilter;

// Simple filter
$filter = new SimpleFilter('name', '=', 'John');

// Logical filter
$logicalFilter = new LogicalFilter('or');
$logicalFilter->addFilter(new SimpleFilter('name', '=', 'John'));
$logicalFilter->addFilter(new SimpleFilter('name', '=', 'Jane'));

// Function filter
$functionFilter = new FunctionFilter('contains', 'description', 'important');
```

### 4. Parsing OData Filter Strings

```php
use Aqqo\OData\Filters\FilterParser;

$parser = new FilterParser();

// Parse complex nested filters
$filterString = "(name eq 'test') OR (age gt 18 and status eq 'active')";
$filter = $parser->parse($filterString);

// Apply to query builder
$filter->apply($queryBuilder);
```

## Supported Filter Types

### Simple Filters
- `name eq 'test'`
- `age gt 18`
- `status in ('active', 'pending')`

### Logical Filters
- `(name eq 'test') AND (age gt 18)`
- `(name eq 'john') OR (name eq 'jane')`
- Complex nesting: `((name eq 'test') OR (age gt 18)) AND (status eq 'active')`

### Function Filters
- `contains(name, 'john')`
- `startswith(name, 'j')`
- `endswith(name, 'n')`

### Relationship Filters
- `any(orders, total gt 100)`
- `all(orders, status eq 'completed')`
- Nested: `any(orders, (total gt 100) AND (status eq 'pending'))`

## Benefits of the New System

1. **Object-Oriented**: Each filter type is a proper object with clear responsibilities
2. **Extensible**: Easy to add new filter types by implementing `FilterInterface`
3. **Testable**: Each filter can be unit tested independently
4. **Type Safe**: Better IDE support and compile-time error checking
5. **Readable**: Much easier to understand and maintain than string parsing
6. **Flexible**: Can create filters programmatically or parse from strings
7. **Nested Support**: Proper support for complex nested expressions

## Migration from Old System

1. Replace `FilterTrait` with `FilterTraitNew` in your classes
2. Update any custom filter logic to use the new filter objects
3. The new system is backward compatible with existing OData filter strings

## Advanced Usage

### Custom Filter Types

You can create custom filter types by implementing `FilterInterface`:

```php
class CustomFilter implements FilterInterface
{
    public function apply(Builder $builder): void
    {
        // Your custom logic here
    }
    
    public function toString(): string
    {
        return 'custom_filter_expression';
    }
}
```

### Filter Validation

The trait provides validation methods that you can override:

```php
protected function isPropertyFilterable(string $property, string $modelName): bool|string
{
    // Your custom validation logic
    return $property;
}

protected function isPropertyExpandable(string $property): string|false
{
    // Your custom relationship validation
    return $property;
}
```

## Performance Considerations

- Filter objects are lightweight and can be cached
- The parser is efficient and handles complex nested expressions
- No more complex regex parsing or string manipulation
- Better memory usage due to object reuse
