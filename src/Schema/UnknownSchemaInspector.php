<?php
namespace Kir\DB\Migrations\Schema;

final class UnknownSchemaInspector implements SchemaInspector {
	public function tableExists(string $tableName): bool {
		return false;
	}

	public function getCreateTableSql(string $tableName): ?string {
		return null;
	}

	public function getColumns(string $tableName): array {
		return [];
	}

	public function getColumn(string $tableName, string $columnName): ?ColumnDefinition {
		return null;
	}

	public function getIndexes(string $tableName): array {
		return [];
	}

	public function getIndex(string $tableName, string $indexName): ?IndexDefinition {
		return null;
	}

	public function getForeignKeys(string $tableName): array {
		return [];
	}

	public function getForeignKey(string $tableName, string $fkName): ?ForeignKeyDefinition {
		return null;
	}
}
