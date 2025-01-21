# Build Eloquent queries from API requests

This package allows you to filter, sort and include eloquent relations based on a request. The `QueryBuilder` used in this package extends Laravel's default Eloquent builder. This means all your favorite methods and macros are still available. Query parameter names follow the [JSON API specification](http://jsonapi.org/) as closely as possible.

## Basic usage

### Filter a query based on a request: `/users?filter[name]=John`:

```php
use Aqqo\OData\Query;

$users = Query::for(User::class)
    ->get();

// all `User`s that contain the string "John" in their name
```

## Installation

TODO
You can install the package via composer:

```bash
composer require aqqo/odata
```


### Testing

Make sure that you have installed sqllite. `sudo apt install php8.2-sqlite3`

```bash
composer test
```

### Code style

```bash
composer analyse
```