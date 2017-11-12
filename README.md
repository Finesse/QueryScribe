# Query Scribe

[![Latest Stable Version](https://poser.pugx.org/finesse/query-scribe/v/stable)](https://packagist.org/packages/finesse/query-scribe)
[![Total Downloads](https://poser.pugx.org/finesse/query-scribe/downloads)](https://packagist.org/packages/finesse/query-scribe)
[![Build Status](https://php-eye.com/badge/finesse/query-scribe/tested.svg)](https://travis-ci.org/FinesseRus/QueryScribe)
[![Coverage Status](https://coveralls.io/repos/github/FinesseRus/QueryScribe/badge.svg?branch=master)](https://coveralls.io/github/FinesseRus/QueryScribe?branch=master)
[![Dependency Status](https://www.versioneye.com/php/finesse:query-scribe/badge)](https://www.versioneye.com/php/finesse:query-scribe)

Provides a convenient object syntax for building SQL queries. Compiles the queries to SQL text with values for binding.
Doesn't perform queries to a database.

```php
$query = (new Query())
    ->from('posts')
    ->where('level', '>', 3)
    ->whereIn('category_id', function ($query) {
        $query
            ->addSelect('id')
            ->from('categories')
            ->where('categories.name', 'Interesting');
    })
    ->where(new Raw('MONTH(date)'), 4)
    ->orderBy('date', 'desc')
    ->limit(10);
    
$prefixer = new TablePrefixer('demo_');
$grammar = new MySQLGrammar();
$compiled = $grammar->compile($prefixer->process($query));

echo $compiled->getSQL();
/*
    SELECT *
    FROM `demo_posts`
    WHERE
        `level` > ? AND
        `category_id` IN (
            SELECT `id`
            FROM `demo_categories`
            WHERE `demo_categories`.`name` = ?
        ) AND
        (MONTH(date)) = ?
    ORDER BY `date` DESC
    LIMIT ?
 */

echo $compiled->getBindings(); // [3, 'Interesting', 4, 10]
```

To perform compiled queries to a database use a database connector like [PDO](http://php.net/manual/en/book.pdo.php), 
[MicroDB](https://github.com/FinesseRus/MicroDB) or [DBAL](http://www.doctrine-project.org/projects/dbal.html).

Key features:

* The builder has a single responsibility: build SQL.
* Designed for further extension. You may build a database tool or an ORM on top of it without major problems. 
  Examples will come soon.
* Very flexible. You can pass a [raw SQL or a subquery](#raw-sql-and-subqueries) almost everywhere (see the PHPDoc 
  comments in the code to know where you can pass them).
* Smart table prefixes which consider table aliases (don't work in raw expressions).
* All the values go to bindings, even from subqueries.
* No dependencies. Requires only PHP ≥ 7.

Supported SQL dialects:

* MySQL
* SQLite
* ~~SQL Server~~ (not fully supported)
* Maybe any other, didn't test it

If you need a dialect support please extend the `CommonGrammar` class and make a pull request.


## Installation

### Using [composer](https://getcomposer.org)

Run in a console

```bash
composer require finesse/query-scribe
```


## Reference

First make a grammar object. Grammar compiles queries to SQL with bindings.

```php
use Finesse\QueryScribe\Grammars\CommonGrammar;

$grammar = new CommonGrammar();
``` 

* `Finesse\QueryScribe\Grammars\MySQLGrammar` for MySQL
* `Finesse\QueryScribe\Grammars\SQLiteGrammar` for SQLite
* `Finesse\QueryScribe\Grammars\CommonGrammar` for everything else

Then make an empty query:

```php
use Finesse\QueryScribe\Query;

$query = new Query();
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
$compiled = $grammar->compile($query); // You can specify the query type explicitly: $grammar->compileSelect($query)

$sql = $compiled->getSQL();
$parameters = $compiled->getBindings();
```

One grammar can be used many times for different queries.

### Building queries

#### Select

If no fields are set, all fields are selected:

```php
(new Query())
    ->from('table');

// SELECT * FROM "table"
```

Specify fields:

```php
(new Query())
    ->addSelect(['id', 'name'])
    ->table('table');
    
// SELECT "id", "name" FROM "table"
```

With aliases:

```php
(new Query())
    ->addSelect('id', 'i')
    ->addSelect(['n' => 'name', 'p' => 'price'])
    ->from('table', 't');

// SELECT "id" AS "i", "name" AS "n", "price" AS "p" FROM "table" AS "t"
```

##### Aggregates

```php
(new Query())
    ->addCount()
    ->addAvg('price', 'avg_price')
    ->addMin('value'),
    ->addMax('value')
    ->addSum('amount', 'sum')
    ->from('orders');

// SELECT COUNT(*), AVG("price") AS "avg_price", MIN("value"), MAX("value"), SUM("AMOUNT") AS "sum" FORM "orders"
```

#### Insert

Use `$grammar->compileInsert()` to compile an insert query. It returns an array of compiled queries because not all the 
DBMSs support all cases of inserting many rows at once.

```php
$grammar->compileInsert(
    (new Query())
        ->table('users')
        ->addInsert(['name' => 'John', 'role' => 5])
        ->addInsert(['name' => 'Bob', 'role' => 1])
);

// Value 1:
//  - SQL: INSERT INTO "users" ("name". "role") VALUES (?, ?), (?, ?)
//  - Bindings: ['John', 5, 'Bob', 1]
```

Many rows at once:

```php
$grammar->compileInsert(
    (new Query())
        ->table('users')
        ->addInsert([
            ['name' => 'Jack', 'role' => 2],
            ['name' => 'Bob', 'role' => 5]
        ])
);

// Value 1:
//  - SQL: INSERT INTO "users" ("name". "role") VALUES (?, ?), (?, ?)
//  - Bindings: ['Jack', 2, 'Bob', 5]
```

Insert from a select statement:

```php
$grammar->compileInsert(
(new Query())
    ->table('users')
    ->addInsertFromSelect(['name', 'phone'], function ($query) {
        $query
            ->addSelect(['first_name', 'primary_phone'])
            ->from('contacts');
    });

// Value 1:
//  - SQL: ("name", "phone") SELECT "first_name", "primary_phone" FROM "contacts"
```

#### Update

Use `$grammar->compile()` or `$grammar->compileUpdate()` to compile an update query.

```php
(new Query())
    ->table('posts')
    ->addUpdate(['title' => 'Awesome', 'position' => 1])
    ->where('id', 55);

// UPDATE "posts" SET "title" = ?, "positoin" = ? WHERE "id" = ?
// Bindings: ['Awesome', 1, 55]
```

#### Delete

Use `$grammar->compile()` or `$grammar->compileDelete()` to compile a delete query.

```php
(new Query())
    ->setDelete()
    ->table('posts')
    ->where('date', '<', '2017-01-01');

// DELETE FROM "posts" WHERE "date" < ?
```

#### Where

Simple where clauses:

```php
(new Query())
    ->from('table')
    ->where('name', 'Bill')
    ->where('age', '>', 5)
    ->orWhere('position', 'like', '%boss%');

// SELECT * FROM "table" WHERE "name" = ? AND "age" > ? OR "position" LIKE ?
```

##### Grouped clauses

```php
(new Query())
    ->from('fruits')
    ->where([
        ['name', 'Orange'],
        ['weight' > 6]
    ])
    ->orWhere([
        ['name', 'Banana'],
        ['weight' < 15]
    ]);

// SELECT * FROM "fruits" WHERE ("name" = ? AND "weight" > ?) OR ("name" = ? AND "weight" < ?)
```

Or using a closure:

```php
(new Query())
    ->from('fruits')
    ->where(function ($query) {
        $query
            ->where('name', 'Apple')
            ->orWhere('name', 'Pine');
    })
    ->notWhere(function ($query) {
        $query
            ->where('weight', '<', 1)
            ->orWhere('weight', '>', 100)
    });

// SELECT * FROM "fruits" WHERE ("name" = ? OR "name" = ?) AND NOT("weight" < ? OR "weight" > ?)
```

##### Raw SQL criterion

```php
(new Query())
    ->from('table')
    ->whereRaw('YEAR(date) = ?', [1997]);
// or
(new Query())
    ->from('table')
    ->where(new Raw('YEAR(date) = ?', [1997]));

// SELECT * FROM "table" WHERE (YEAR(date) = ?)
// Bindings: [1997]
```

You can also use `orWhereRaw`.

##### Between

```php
(new Query())
    ->from('table')
    ->whereBetween('age', 13, 19);

// SELECT * FROM "table" WHERE ("age" BETWEEN ? AND ?)
```

You can also use `orWhereBetween`, `whereNotBetween` and `orWhereNotBetween`.

##### In

```php
(new Query())
    ->from('table')
    ->whereIn('caterogy_id', [5, 17, 10]);

// SELECT * FROM "table" WHERE "category_id" IN (?, ?, ?)
```

Using subquery:

```php
(new Query())
    ->from('table')
    ->whereIn('user_id', function ($query) {
        $query
            ->addSelect('id')
            ->from('users')
            ->where('name', 'Charles');
    });

// SELECT * FROM "table" WHERE "category_id" IN (SELECT "id" FROM "users" WHERE "name" = ?)
```

You can also use `orWhereIn`, `whereNotIn` and `orWhereNotIn`.

##### Is null

```php
(new Query())
    ->from('table')
    ->whereNull('description');

// SELECT * FROM "table" WHERE "description" IS NULL
```

You can also use `orWhereNull`, `whereNotNull` and `orWhereNotNull`.

##### Compare columns

```php
(new Query())
    ->from('table')
    ->whereColumn('age', '<', 'experiance');

// SELECT * FROM "table" WHERE "age" < "experiance"
```

Or

```php
(new Query())
    ->from('table')
    ->whereColumn([
        ['first_name', 'last_name'],
        ['account', '>=', 'debpt']
    ]);

// SELECT * FROM "table" WHERE ("first_name" = "last_name" AND "account" >= "debpt")
```

You can also use `orWhereColumn`.

##### Exists

```php
(new Query())
    ->from('posts')
    ->whereExists(function ($query) {
        $query
            ->from('comments')
            ->whereColumn('comments.post_id', 'posts.id');
    });

// SELECT * FROM "posts" WHERE EXISTS (SELECT * FROM "comments" WHERE "comments"."post_id" = "posts"."id")
```

##### How clauses are appended to each other

By default where clauses are appended to previous clauses using the AND logical rule.

Every logical clause is appended this way: _combined previous clauses APPEND_RULE clause_.

For example, the following clauses chain `where(...)->orWhere(...)->where(...)->orWhere(...)` 
is compiled to `((... OR ...) AND ...) OR ...`.

#### Order

```php
(new Query())
    ->from('demo')
    ->orderBy('date', 'desc')
    ->orderBy('id');

// SELECT * FROM "demo" ORDER BY "date" DESC, "id" ASC
```

##### In random order

```php
(new Query())
    ->from('demo')
    ->inRandomOrder();

// SELECT * FROM "demo" ORDER BY RANDOM()
```

You can combine the random order with a column order.

#### Limit and offset

```php
(new Query())
    ->from('table')
    ->offset(150)
    ->limit(12);

// SELECT * FROM "table" OFFSET ? LIMIT ?
// Bindings: [150, 12]
```

Warning! SQL doesn't allow to use offset without using limit.

#### Raw SQL and subqueries

You can pass raw SQL and subqueries in many of the `Query` methods.

Call to make a raw:

```php
use Finesse\QueryScribe\Raw;
$raw = new Raw('CONCAT(?, ?) # Your raw SQL', ['Bindings', 'here']);

// or
use Finesse\QueryScribe\Query;
$query = new Query();
$raw = $query->raw('CONCAT(?, ?)', ['Bindings', 'here']);
```

Example of what is possible:

```php
(new Query())
    ->from(function($query) {
        $query
            ->addSelect(new Raw('SOMETHING()'))
            ->from('other_table');
    }, 'table')
    ->addSelect([
        'column1' => (new Query())->addAvg('price')->from('products'),
        'column2' => function ($query) {
            $query
                ->addSelect('name')
                ->from('users')
                ->orderBy('rating', 'desc')
                ->limit(1);
        }
    ])
    ->where(function ($query) {
        $query
            ->from('events')
            ->addSelect('date')
            ->whereColumn('events.type', new Raw('table.id'))
            ->orderBy('date')
            ->offest(1)
            ->limit(1);
    }, '<', new Raw('NOW()'))
    ->orderBy(function ($query) {
        $query
            ->addMax(new Raw('price * quantity'))
            ->from('orders')
            ->whereExists((new Query())->addSelect('height')->from('person')->whereColumn([
                ['orders.person_id', 'order.id'],
                ['person.id', new Raw('table.user_id')]
            ]));
    }, 'desc')
    ->offset(function ($query) {
        $query->from('pages')->addMax('length');
    })
    ->limit(3);
```

### Table prefix

Use `TablePrefixer` to add prefixes to all the tables in a query:

```php
use Finesse\QueryScribe\PostProcessors\TablePrefixer;
use Finesse\QueryScribe\Query;

$prefixer = new TablePrefixer('prefix_'); // Needs to be created once

$query = (new Query())
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
(new Query())
    ->from(new Raw('MAGIC('.$prefixer->addTablePrefix('my_table').')'))
    ->addSelect(new Raw('REPLACE('.$prefixer->addTablePrefixToColumn('my_table.name').', ?, ?)', ['small', 'big']));
```

Prefixer doesn't modify a given `Query` object therefore doing this is safe:

```php
$prefixer1 = new TablePrefixer('prefix1_');
$prefixer2 = new TablePrefixer('prefix2_');

$query = (new Query())/* -> ... */;

$prefixedQuery1 = $prefixer1->process($query); // The prefix is `prefix1_
$prefixedQuery2 = $prefixer2->process($query); // The prefix is `prefix2_
```

### Using grammar

Besides converting query objects to SQL, grammar contains some helper methods.

Escape LIKE special wildcard characters:

```php
$searchString = '%iamhacker%';

$query->where('name', 'like', $grammar->escapeLikeWildcards($searchString).'_'); // "name" LIKE \%iamhacker\%_
```

Wrap a table or column name in quotes:

```php
$query->whereRaw('MIN('.$grammar->quoteIdentifier('data"base').'.'.$grammar->quoteIdentifier('ta"ble').') > 10');
// or
$query->whereRaw('MIN('.$grammar->quoteCompositeIdentifier('data"base.ta"ble').') > 10'); // MIN("data""base.ta""ble") > 10
```


## Versions compatibility

The project follows the [Semantic Versioning](http://semver.org).


## License

MIT. See [the LICENSE](LICENSE) file for details.
