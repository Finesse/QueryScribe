* [Getting started](getting-started.md)
* [Building queries](building-queries.md)
* [Table prefixes](table-prefixes.md)
* **Grammars**


# Grammars

Grammar compiles queries to an SQL text with bindings.

Available grammars:

* `Finesse\QueryScribe\Grammars\MySQLGrammar` — MySQL
* `Finesse\QueryScribe\Grammars\SQLiteGrammar` — SQLite
* `Finesse\QueryScribe\Grammars\CommonGrammar` — everything else

Use the `compile` method to compile a query object. Grammar guesses the query type from it's content.
You can use one of this methods to specify the query type explicitly:

* `compileSelect`
* `compileInsert` returns an array of compiled statements instead of one statement
* `compileUpdate`
* `compileDelete`

Compiled statement contains an SQL query text and values to bind to the statement.

```php
$compiled = $grammar->compileSelect($query);

$sql = $compiled->getSQL();
$parameters = $compiled->getBindings();
```  

## Helpers

Besides converting query objects to SQL, grammar contains some helper methods.

Escape LIKE special wildcard characters:

```php
$searchString = '%iamhacker%';

$query->where('name', 'like', $grammar->escapeLikeWildcards($searchString).'_'); // "name" LIKE \%iamhacker\%_
```

The backslash (` \ `) is used as the escape character.

Wrap a table or a column name in quotes:

```php
$query->whereRaw('MIN('.$grammar->quoteIdentifier('data"base').'.'.$grammar->quoteIdentifier('ta"ble').') > 10'); // MIN("data""base"."ta""ble") > 10
// or
$query->whereRaw('MIN('.$grammar->quoteCompositeIdentifier('data"base.ta"ble').') > 10'); // MIN("data""base"."ta""ble") > 10
```

