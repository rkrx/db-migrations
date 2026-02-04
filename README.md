db-migrations
=============

Lightweight library to execute ordered DB migration files.

Features
--------
- Sequential execution with automatic rollback of previous steps when a step fails.
- Supports plain SQL and fluent migration builders.
- Fluent migrations derive DOWN steps from the current schema.
- Strict feature gating per engine/version (unsupported features skip the UP step with a log warning).

Basic usage
-----------
```php
<?php

use Kir\DB\Migrations\DBAdapter;

return [
    'statements' => [[
        'up' => static fn (DBAdapter $db) =>
            $db->exec("ALTER TABLE `my_table` ADD COLUMN `id` INT NOT NULL"),
        'down' => static fn (DBAdapter $db) =>
            $db->exec("ALTER TABLE `my_table` DROP COLUMN `id`")
    ]],
];
```

Fluent usage
------------
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

Foreign keys + index example
----------------------------
```php
<?php

use Kir\DB\Migrations\Commands\Add;

return [
    'statements' => [
        Add::table('authors')
            ->intColumn(name: 'id', isUnsigned: true, nullable: false, comment: 'Primary id')
            ->varcharColumn(name: 'name', length: 255, comment: 'Display name')
            ->primaryKey('id')
            ->autoIncrement('id')
            ->engine('InnoDB'),

        Add::table('books')
            ->intColumn(name: 'id', isUnsigned: true, nullable: false, comment: 'Primary id')
            ->intColumn(name: 'author_id', isUnsigned: true, nullable: false, comment: 'Author reference')
            ->varcharColumn(name: 'title', length: 255, comment: 'Book title')
            ->primaryKey('id')
            ->autoIncrement('id')
            ->engine('InnoDB'),

        Add::toTable('books')
            ->index('idx_books_author', 'author_id')
            ->foreignKey(
                name: 'fk_books_author',
                localColumns: ['author_id'],
                foreignTable: 'authors',
                foreignColumns: ['id'],
                onDelete: 'CASCADE',
                onUpdate: 'CASCADE'
            ),
    ]
];
```

Atomic DDL operations
---------------------
Each operation generates an UP and derives DOWN from the live schema. Unsupported features are skipped with a warning.

Create/alter/drop tables
- `Add::table(name: string)`
- `Add::toTable(name: string)`
- `Drop::table(name: string)`
- `Drop::fromTable(name: string)`
- `Change::table(name: string)`

Columns
- `column(name, type, nullable = false, default = null, comment = null, charset = null, collation = null, onUpdate = null, autoIncrement = false, isUnsigned = false, after = null, first = false)`
- `intColumn(name, length = null, isUnsigned = false, nullable = false, default = null, comment = null, after = null, first = false)`
- `bigIntColumn(name, length = null, isUnsigned = false, nullable = false, default = null, comment = null, after = null, first = false)`
- `smallIntColumn(name, length = null, isUnsigned = false, nullable = false, default = null, comment = null, after = null, first = false)`
- `tinyIntColumn(name, length = null, isUnsigned = false, nullable = false, default = null, comment = null, after = null, first = false)`
- `varcharColumn(name, length, nullable = false, default = null, comment = null, charset = null, collation = null, after = null, first = false)`
- `charColumn(name, length, nullable = false, default = null, comment = null, charset = null, collation = null, after = null, first = false)`
- `textColumn(name, nullable = false, comment = null, after = null, first = false)`
- `decimalColumn(name, precision, scale, nullable = false, default = null, comment = null, isUnsigned = false, after = null, first = false)`
- `floatColumn(name, nullable = false, default = null, comment = null, isUnsigned = false, after = null, first = false)`
- `doubleColumn(name, nullable = false, default = null, comment = null, isUnsigned = false, after = null, first = false)`
- `booleanColumn(name, nullable = false, default = null, comment = null, after = null, first = false)`
- `dateColumn(name, nullable = false, default = null, comment = null, after = null, first = false)`
- `dateTimeColumn(name, nullable = false, default = null, onUpdate = null, comment = null, after = null, first = false)`
- `timestampColumn(name, nullable = false, default = null, onUpdate = null, comment = null, after = null, first = false)`
- `jsonColumn(name, nullable = false, comment = null, after = null, first = false)`

Keys and indexes
- `primaryKey(...columns)`
- `autoIncrement(columnName)`
- `index(name, ...columns)`
- `uniqueIndex(name, ...columns)`
- `foreignKey(name, localColumns, foreignTable, foreignColumns, onUpdate = null, onDelete = null)`

Table options
- `engine(name)`
- `charset(name)`
- `collation(name)`

Engine support
--------------
- MariaDB 10.6 is supported by default with strict feature detection.
- SQLite is supported for a limited set of operations; unsupported features skip UP.
- Unsupported features never throw; they log a warning and skip UP.
