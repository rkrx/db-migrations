<?php
namespace Kir\DB\Migrations\Schema;

interface SqlRendererInterface {
	/**
	 * @param ColumnDefinition[] $columns
	 * @param string[] $primaryKey
	 * @param IndexDefinition[] $indexes
	 * @param IndexDefinition[] $uniqueIndexes
	 * @param ForeignKeyDefinition[] $foreignKeys
	 * @return string|array<int, string>
	 */
	public function renderCreateTable(
		string $tableName,
		array $columns,
		array $primaryKey,
		array $indexes,
		array $uniqueIndexes,
		array $foreignKeys,
		?string $engine = null,
		?string $charset = null,
		?string $collation = null
	);

	/**
	 * @return string|array<int, string>
	 */
	public function renderDropTable(string $tableName);

	/**
	 * @return string|array<int, string>
	 */
	public function renderAddColumn(string $tableName, ColumnDefinition $column);

	/**
	 * @return string|array<int, string>
	 */
	public function renderDropColumn(string $tableName, string $columnName);

	/**
	 * @return string|array<int, string>
	 */
	public function renderModifyColumn(string $tableName, ColumnDefinition $column);

	/**
	 * @return string|array<int, string>
	 */
	public function renderAddIndex(string $tableName, IndexDefinition $index);

	/**
	 * @return string|array<int, string>
	 */
	public function renderDropIndex(string $tableName, string $indexName);

	/**
	 * @param string[] $columns
	 * @return string|array<int, string>
	 */
	public function renderAddPrimaryKey(string $tableName, array $columns);

	/**
	 * @return string|array<int, string>
	 */
	public function renderDropPrimaryKey(string $tableName);

	/**
	 * @return string|array<int, string>
	 */
	public function renderAddForeignKey(string $tableName, ForeignKeyDefinition $fk);

	/**
	 * @return string|array<int, string>
	 */
	public function renderDropForeignKey(string $tableName, string $fkName);
}
