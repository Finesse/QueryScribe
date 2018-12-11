# Query Scribe

[![Latest Stable Version](https://poser.pugx.org/finesse/query-scribe/v/stable)](https://packagist.org/packages/finesse/query-scribe)
[![Total Downloads](https://poser.pugx.org/finesse/query-scribe/downloads)](https://packagist.org/packages/finesse/query-scribe)
![PHP from Packagist](https://img.shields.io/packagist/php-v/finesse/query-scribe.svg)
[![Build Status](https://travis-ci.org/Finesse/QueryScribe.svg?branch=master)](https://travis-ci.org/Finesse/QueryScribe)
[![Coverage Status](https://coveralls.io/repos/github/Finesse/QueryScribe/badge.svg?branch=master)](https://coveralls.io/github/Finesse/QueryScribe?branch=master)
[![Dependency Status](https://www.versioneye.com/php/finesse:query-scribe/badge)](https://www.versioneye.com/php/finesse:query-scribe)

Provides a convenient object syntax to build SQL queries. Compiles the queries to an SQL text with values for binding.
Doesn't perform queries to database.

```php
$query = (new Query)
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
$compiled = $grammar->compile($query->apply($prefixer));

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

echo $compiled->getBindings();
/*
    [3, 'Interesting', 4, 10]
 */
```

To perform compiled queries to a database, use a database connector like [PDO](http://php.net/manual/en/book.pdo.php), 
[MicroDB](https://github.com/Finesse/MicroDB) or [DBAL](http://www.doctrine-project.org/projects/dbal.html) or use
a ready database abstraction like [MiniDB](https://github.com/Finesse/MiniDB) or 
[Wired](https://github.com/Finesse/Wired).

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


## Documentation

The documentation is located in [the `docs` directory](docs/getting-started.md).

Also all the classes, methods and properties has a PHPDoc comment in the code.


## Versions compatibility

The project follows the [Semantic Versioning](http://semver.org).


## License

MIT. See [the LICENSE](LICENSE) file for details.
