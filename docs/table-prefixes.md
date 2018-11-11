* [Getting started](getting-started.md)
* [Building queries](building-queries.md)
* **Table prefixes**
* [Grammars](grammars.md)


# Table prefixes

Use the `TablePrefixer` class to add prefixes to all the tables in a query:

```php
use Finesse\QueryScribe\PostProcessors\TablePrefixer;
use Finesse\QueryScribe\Query;

$prefixer = new TablePrefixer('prefix_'); // Needs to be created once

$query = (new Query)
    ->from('posts')
    ->whereExists(function ($query) {
        $query
            ->from('comments', 'c')
            ->whereColumn('c.post_id', 'posts.id');
    })
    ->where('posts.date', '>', '2017-11-11');
    
$prefixedQuery = $prefixer->process($query);

/*
    SELECT * FROM "prefix_posts" 
    WHERE EXISTS (
        SELECT * FROM "prefix_comments" AS "c" 
        WHERE "c"."post_id" = "prefix_posts"."id"
    ) AND "prefix_posts"."date" > ?
 */
```

As you can see table aliases are not prefixed. Prefixer automatically detects which identifiers are table aliases.

Table prefixes are not added in raw expressions. You can use the helper methods to add a prefix:

```php
(new Query)
    ->from(new Raw('MAGIC('.$prefixer->addTablePrefix('my_table').')'))
    ->addSelect(new Raw('REPLACE('.$prefixer->addTablePrefixToColumn('my_table.name').', ?, ?)', ['small', 'big']));
```

Prefixer doesn't modify a given `Query` object therefore doing this is safe:

```php
$prefixer1 = new TablePrefixer('prefix1_');
$prefixer2 = new TablePrefixer('prefix2_');

$query = (new Query)/* -> ... */;

$prefixedQuery1 = $prefixer1->process($query); // The prefix is `prefix1_`
$prefixedQuery2 = $prefixer2->process($query); // The prefix is `prefix2_`
```
