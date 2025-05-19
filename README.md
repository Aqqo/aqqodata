# Aqqo OData Query Builder

A powerful Laravel package that allows you to build Eloquent queries from OData-compliant API requests. This package extends Laravel's default Eloquent builder, maintaining compatibility with all your favorite methods and macros while adding OData query support.

## Features

- 🔍 **Filtering**: Filter records using OData filter syntax
- 📋 **Selecting**: Choose specific fields to return
- 🔄 **Expanding**: Include related entities in your response
- 🔎 **Searching**: Full-text search capabilities
- 📊 **Pagination**: Skip and top parameters for pagination
- 📈 **Ordering**: Sort results using orderby parameter
- 📝 **Counting**: Get total count of records
- 🛡️ **Type Safety**: Full PHP 8 type support with generics

## Installation

You can install the package via composer:

```bash
composer require aqqo/odata
```

## Basic Usage

### Filtering

Filter records using OData filter syntax:

```php
use Aqqo\OData\Query;

// Filter users by name
$users = Query::for(User::class)
    ->get();

// URL: /users?filter[name]=John
// Returns all users that contain "John" in their name
```

### Selecting Fields

Choose specific fields to return:

```php
// Select only name and email fields
$users = Query::for(User::class)
    ->get();

// URL: /users?select=name,email
```

### Expanding Relations

Include related entities in your response:

```php
// Include user's posts and comments
$users = Query::for(User::class)
    ->get();

// URL: /users?expand=posts,comments
```

### Complex Queries

Combine multiple OData operations:

```php
$users = Query::for(User::class)
    ->get();

// URL: /users?filter[age]=gt:18&select=name,email&expand=posts&orderby=name&top=10&skip=0
```

## Advanced Usage

### Custom Query Builder

You can use your own query builder instance:

```php
$query = User::where('active', true);
$users = Query::for($query)->get();
```

### Disabling Features

Disable specific OData features:

```php
$query = Query::for(User::class, select: false, filter: false);
```

### Response Format

The query results are automatically formatted according to OData specifications:

```php
$response = Query::for(User::class)->get();
// Returns a collection with properly formatted OData response
```

## Development

### Testing

Make sure you have SQLite installed:
```bash
sudo apt install php8.2-sqlite3
```

Run the tests:
```bash
composer test
```

### Code Analysis

Run static analysis:
```bash
composer analyse
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@aqqo.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.