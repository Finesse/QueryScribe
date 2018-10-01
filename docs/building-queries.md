* [Getting started](getting-started.md)
* **Building queries**
* [Table prefixes](table-prefixes.md)
* [Grammars](grammars.md)


# Building queries

## Select

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

### Aggregates

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

## Insert

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
        })
);

// Value 1:
//  - SQL: ("name", "phone") SELECT "first_name", "primary_phone" FROM "contacts"
```

## Update

Use `$grammar->compile()` or `$grammar->compileUpdate()` to compile an update query.

```php
(new Query())
    ->table('posts')
    ->addUpdate(['title' => 'Awesome', 'position' => 1])
    ->where('id', 55);

// UPDATE "posts" SET "title" = ?, "positoin" = ? WHERE "id" = ?
// Bindings: ['Awesome', 1, 55]
```

## Delete

Use `$grammar->compile()` or `$grammar->compileDelete()` to compile a delete query.

```php
(new Query())
    ->setDelete()
    ->table('posts')
    ->where('date', '<', '2017-01-01');

// DELETE FROM "posts" WHERE "date" < ?
```

## Where

Simple where clauses:

```php
(new Query())
    ->from('table')
    ->where('name', 'Bill')
    ->where('age', '>', 5)
    ->orWhere('position', 'like', '%boss%');

// SELECT * FROM "table" WHERE "name" = ? AND "age" > ? OR "position" LIKE ?
```

### Grouped clauses

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

### Raw SQL criterion

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

### Between

```php
(new Query())
    ->from('table')
    ->whereBetween('age', 13, 19);

// SELECT * FROM "table" WHERE ("age" BETWEEN ? AND ?)
```

You can also use `orWhereBetween`, `whereNotBetween` and `orWhereNotBetween`.

### In

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

### Is null

```php
(new Query())
    ->from('table')
    ->whereNull('description');

// SELECT * FROM "table" WHERE "description" IS NULL
```

You can also use `orWhereNull`, `whereNotNull` and `orWhereNotNull`.

### Compare columns

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

### Exists

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

### How clauses are appended to each other

By default "where" clauses are appended to previous clauses using the AND logical rule.

Every logical clause is appended this way: _combined previous clauses APPEND_RULE clause_.

For example, the following clauses chain `where(...)->orWhere(...)->where(...)->orWhere(...)` 
is compiled to `((... OR ...) AND ...) OR ...`.

## Order

```php
(new Query())
    ->from('demo')
    ->orderBy('date', 'desc')
    ->orderBy('id');

// SELECT * FROM "demo" ORDER BY "date" DESC, "id" ASC
```

### In random order

```php
(new Query())
    ->from('demo')
    ->inRandomOrder();

// SELECT * FROM "demo" ORDER BY RANDOM()
```

You can combine the random order with a column order.

## Limit and offset

```php
(new Query())
    ->from('table')
    ->offset(150)
    ->limit(12);

// SELECT * FROM "table" OFFSET ? LIMIT ?
// Bindings: [150, 12]
```

Warning! SQL doesn't allow to use offset without using limit.

## Raw SQL and subqueries

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

