* [Getting started](getting-started.md)
* [Building queries](building-queries.md)
* [Table prefixes](table-prefixes.md)
* **Making SQL from query**
* [Helpers](helpers.md)


# Making SQL from query

A `Query` object can be compiled to an SQL text with bindings using a grammar object.

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
$query = (new Query)/* -> ... */;
$grammar = new SQLiteGrammar();

$compiled = $grammar->compileSelect($query);

$sql = $compiled->getSQL();
$parameters = $compiled->getBindings();
```  
