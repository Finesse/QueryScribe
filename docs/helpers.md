* [Getting started](getting-started.md)
* [Building queries](building-queries.md)
* [Table prefixes](table-prefixes.md)
* [Making SQL from query](making-sql.md)
* Helpers


# Helpers

## Escape LIKE special wildcard characters

Can be done through a [grammar](making-sql.md) object:

```php
$grammar = new SQLiteGrammar();

$searchString = '%iamhacker%';

$query->where('name', 'like', $grammar->escapeLikeWildcards($searchString).'_'); // "name" LIKE \%iamhacker\%_
```

The backslash (` \ `) is used as the escape character.

## Wrap a table or a column name in quotes

Can be done through a [grammar](making-sql.md) object:

```php
$query->whereRaw('MIN('.$grammar->quoteIdentifier('data"base').'.'.$grammar->quoteIdentifier('ta"ble').') > 10');
// WHERE MIN("data""base"."ta""ble") > 10

// or

$query->whereRaw('MIN('.$grammar->quoteCompositeIdentifier('data"base.ta"ble').') > 10');
// WHERE MIN("data""base"."ta""ble") > 10
```

## Add table names to the column names

Makes all the column names in a query have explicit table name or alias:

```php
$query = (new Query)
    ->table('users', 'u')
    ->addSelect('name')
    ->where('status', 'verified')
    ->orWhere('u.type', 'admin');

// SELECT "name" FROM "users" AS "u" WHERE "status" = ? OR "u"."type" = ?

$query = $query->apply(new \Finesse\QueryScribe\PostProcessors\ExplicitTables);

// SELECT "u"."name" FROM "users" AS "u" WHERE "u"."status" = ? OR "u"."type" = ?
```

You can utilize it when you use `apply` with `join` to resolve ambiguous column names:

```php
$unknownTransform = function ($query) {
    $query->orderBy('id'); // The `id` column is presented in both tables
};

$query = (new Query)
    ->table('users')
    ->apply($unknownTransform)
    ->apply(new ExplicitTables)
    ->join('posts', 'posts.author_id', '=', 'users.id');

// SELECT * FROM "users" INNER JOIN "posts" ON "posts"."author_id" = "users.id" ORDER BY "users"."id" 
```

It doesn't work with raw statements, you need to add a table name yourself.
You can get the current query table identifier using the `getTableIdentifier` method:

```php
$table = $query->getTableIdentifier();
$query->whereRaw($table.'.date > NOW()');
``` 
