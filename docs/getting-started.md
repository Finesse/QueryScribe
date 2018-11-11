* **Getting started**
* [Building queries](building-queries.md)
* [Table prefixes](table-prefixes.md)
* [Grammars](grammars.md)


# Getting started

## Installation

### Using [composer](https://getcomposer.org)

Run in a console

```bash
composer require finesse/query-scribe
```


## Usage

First make a grammar object. Grammar compiles queries to SQL with bindings.

```php
use Finesse\QueryScribe\Grammars\CommonGrammar;

$grammar = new CommonGrammar();
``` 

Available grammars:

* `Finesse\QueryScribe\Grammars\MySQLGrammar` — MySQL
* `Finesse\QueryScribe\Grammars\SQLiteGrammar` — SQLite
* `Finesse\QueryScribe\Grammars\CommonGrammar` — everything else

Then make an empty query:

```php
use Finesse\QueryScribe\Query;

$query = new Query;
```

Build a query:

```php
$query
    ->addSelect(['name', 'id'])
    ->from('users')
    ->limit(10);
```

Compile the query:

```php
$compiled = $grammar->compile($query);

// You can specify the query type explicitly:
$compiled = $grammar->compileSelect($query);

$sql = $compiled->getSQL();
$parameters = $compiled->getBindings();
```

One grammar can be used many times for different queries.

