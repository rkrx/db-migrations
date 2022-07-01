<?php

namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\DBAdapter;

class AddIndex {
	public static function of(string $tableName, string $indexName, array $columns) {
		$columnStr = implode(', ', array_map(fn($column) => "`{$column}`", $columns));
		$upStmt = sprintf('CREATE INDEX IF NOT EXISTS `%s` ON `%s`(%s);', $indexName, $tableName, $columnStr);
		$downStmt = sprintf('DROP INDEX IF EXISTS `%s` ON `%s`;', $indexName, $tableName);

		return [
			'up' => fn(DBAdapter $db) => $db->exec($upStmt),
			'down' => fn(DBAdapter $db) => $db->exec($downStmt)
		];
	}
}
