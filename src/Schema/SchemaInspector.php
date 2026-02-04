<?php
namespace Kir\DB\Migrations\Schema;

interface SchemaInspector {
	public function tableExists(string $tableName): bool;
	public function getCreateTableSql(string $tableName): ?string;

	/**
	 * @return ColumnDefinition[]
	 */
	public function getColumns(string $tableName): array;
	public function getColumn(string $tableName, string $columnName): ?ColumnDefinition;

	/**
	 * @return IndexDefinition[]
	 */
	public function getIndexes(string $tableName): array;
	public function getIndex(string $tableName, string $indexName): ?IndexDefinition;

	/**
	 * @return ForeignKeyDefinition[]
	 */
	public function getForeignKeys(string $tableName): array;
	public function getForeignKey(string $tableName, string $fkName): ?ForeignKeyDefinition;
}
