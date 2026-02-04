# Database Migrations

## Explanation

The concept of a migration file is that SQL statements are executed sequentially, which bring about changes to the database. Such changes can include both DML and DDL statements.

A migration file looks as follows:

```php
<?php 

use Kir\DB\Migrations\DBAdapter;  
  
return [  
    'statements' => [[  
       'up' => static fn (DBAdapter $db) =>  
          $db->exec(/* SQL statement to apply the change to the database */),  
       'down' => static fn (DBAdapter $db) =>  
          $db->exec(/* SQL statement to revert the UP statement */)  
    ], [  
       /* Another statement */
    ], [  
       /* Another statement ... */
    ]],
];
```

On the PHP side, the `return` statement returns a structure to an execution context. What is returned is an associative array that contains various keys:

- `statements`: This is where the individual "steps" are defined, which should be executed sequentially when this migration script is run. The steps will be further explained below.

Other keys are not yet defined.

## Steps

- Steps are always executed sequentially.
- Each step consists of an optional `up` part and a `down` part. If a step fails, the `down` part of the step is executed, as well as the `down` parts of all steps that were executed up to that point in reverse order. 
  - Any error, that might be encountered during the execution of `down` parts is ignored but printed out on `STDOUT`.
- Migration scripts are designed to serve as self-contained recipes for database changes. If a step within a migration encounters an error, causing all previously executed steps of that file to be rolled back. This does not affect other migration files that may have been executed just prior to it.

## Example:

```php
<?php 

use Kir\DB\Migrations\DBAdapter;  
  
return [  
    'statements' => [[  
       'up' => static fn (DBAdapter $db) =>  
          $db->exec("ALTER TABLE `my_table` ADD COLUMN `id` INT NOT NULL DEFAULT 0 FIRST"),  
       'down' => static fn (DBAdapter $db) =>  
          $db->exec("ALTER TABLE `my_table` DROP COLUMN `id`;")
    ], [  
       'up' => static fn (DBAdapter $db) =>  
          $db->exec("ALTER TABLE `my_table` ADD COLUMN `price` DECIMAL(15,2) NOT NULL DEFAULT 0.0 AFTER `id`"),  
       'down' => static fn (DBAdapter $db) =>  
          $db->exec("ALTER TABLE `my_table` DROP COLUMN `price`;")
    ], [  
       'up' => static fn (DBAdapter $db) =>  
          $db->exec("ALTER TABLE `my_table` ADD COLUMN `name` INT NOT NULL DEFAULT 0 AFTER `price`"),  
       'down' => static fn (DBAdapter $db) =>  
          $db->exec("ALTER TABLE `my_table` DROP COLUMN `name`;")
    ]],  
];
```

Multiline SQL statements are written as follows:

```php
<?php 

use Kir\DB\Migrations\DBAdapter;  
  
return [  
    'statements' => [[  
       'up' => static fn (DBAdapter $db) =>  
          $db->exec("
	          CREATE TABLE IF NOT EXISTS `my_table` (
					`sku` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'Stock keeping unit identifier',
					`active` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indicates whether the item is active',
					`ean` CHAR(13) NOT NULL DEFAULT '' COMMENT 'European Article Number',
	          ) ENGINE InnoDB;
          "),  
       'down' => static fn (DBAdapter $db) =>  
          $db->exec("DROP TABLE IF EXISTS `my_table`;")
    ]],  
];
```

## Fluent migration steps

Statements can also include fluent builders. These builders derive DOWN steps from the existing schema at runtime and apply strict feature gating per engine/version. If a feature is not supported, the UP step is skipped and a warning is logged.

```php
<?php

use Kir\DB\Migrations\Commands\Add;
use Kir\DB\Migrations\Commands\Change;
use Kir\DB\Migrations\Commands\Drop;

return [
    'statements' => [
        Add::table('test')
            ->intColumn(name: 'a', isUnsigned: true, nullable: false, comment: 'Primary id')
            ->varcharColumn(name: 'b', length: 255, comment: 'Label')
            ->primaryKey('a')
            ->autoIncrement('a')
            ->engine('InnoDB'),

        Add::toTable('test')
            ->intColumn(name: 'c', after: 'b', nullable: true, comment: 'Extra column'),

        Drop::fromTable('test')
            ->column('c'),

        Change::table('test')
            ->column(name: 'b', comment: 'New comment'),
    ]
];
```

Supported engines
- MariaDB 10.6 (strict feature detection).
- SQLite for a limited set of operations; unsupported features skip UP.

## Rules

- Regularily include the `IF NOT EXISTS` clause in `CREATE TABLE` statements: `CREATE TABLE IF NOT EXISTS ...`.
- Regularily include the `IF EXISTS` clause in `DROP TABLE` statements: `DROP TABLE IF EXISTS ...`.
- Always add semantically appropriate `COMMENT` clauses for the columns of a `CREATE TABLE` statement if they are not already present. The text of the `COMMENT` clause is always written in English.
- Use only one major SQL-Statement per bucket.
