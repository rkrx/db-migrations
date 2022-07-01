<?php
namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\DBAdapter;

class AddForeignKey {
	/**
	 * @param string $tableName
	 * @param string $fkName
	 * @param array<int, string> $localColumns
	 * @param string $foreignTableName
	 * @param array<int, string> $foreignColumns
	 * @param string&('NO ACTION'|'CASCADE'|'RESTRICT'|'SET NULL'|'SET DEFAULT') $updateMode
	 * @param string $deleteMode
	 * @return array{up: Closure, down: Closure}
	 */
	public static function of(string $tableName, string $fkName, array $localColumns, string $foreignTableName, array $foreignColumns, ?string $updateMode, ?string $deleteMode) {
		if($updateMode === null || $updateMode === 'NO ACTION') {
			$onUpdate = 'NO ACTION';
		} elseif($updateMode === 'CASCADE') {
			$onUpdate = 'CASCADE';
		} elseif($updateMode === 'RESTRICT') {
			$onUpdate = 'RESTRICT';
		} elseif($updateMode === 'SET NULL') {
			$onUpdate = 'SET NULL';
		} elseif($updateMode === 'SET DEFAULT') {
			$onUpdate = 'SET DEFAULT';
		} else {
			throw new \InvalidArgumentException("Invalid update mode: $updateMode");
		}
		
		if($deleteMode === null || $deleteMode === 'NO ACTION') {
			$onDelete = 'NO ACTION';
		} elseif($deleteMode === 'CASCADE') {
			$onDelete = 'CASCADE';
		} elseif($deleteMode === 'RESTRICT') {
			$onDelete = 'RESTRICT';
		} elseif($deleteMode === 'SET NULL') {
			$onDelete = 'SET NULL';
		} elseif($deleteMode === 'SET DEFAULT') {
			$onDelete = 'SET DEFAULT';
		} else {
			throw new \InvalidArgumentException("Invalid delete mode: $deleteMode");
		}

		$localColumnStr = implode(', ', array_map(static fn($column) => "`{$column}`", $localColumns));
		$foreignColumnStr = implode(', ', array_map(static fn($column) => "`{$column}`", $foreignColumns));

		$upStmt = sprintf('ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (%s) REFERENCES `%s`(%s) ON DELETE %s ON UPDATE %s;', $tableName, $fkName, $localColumnStr, $foreignTableName, $foreignColumnStr, $onDelete, $onUpdate);
		$downStmt = sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`;', $tableName, $fkName);

		return [
			'up' => fn(DBAdapter $db) => $db->exec($upStmt),
			'down' => fn(DBAdapter $db) => $db->exec($downStmt)
		];
	}
}
