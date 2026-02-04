<?php
namespace Kir\DB\Migrations\Schema;

use Kir\DB\Migrations\Common\Expr;
use Kir\DB\Migrations\DBAdapter;

final class SqliteSchemaInspector implements SchemaInspector {
	public function __construct(private DBAdapter $db) {}

	public function tableExists(string $tableName): bool {
		$result = $this->db->query(
			"SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:t",
			['t' => $tableName]
		);
		return (bool) $result->getValue();
	}

	public function getCreateTableSql(string $tableName): ?string {
		$result = $this->db->query(
			"SELECT sql FROM sqlite_master WHERE type='table' AND name=:t",
			['t' => $tableName]
		)->getValue();
		return is_string($result) ? $result : null;
	}

	public function getColumns(string $tableName): array {
		$table = $this->quoteIdentifier($tableName);
		$rows = $this->db->query("PRAGMA table_info({$table})")->getRows();
		$columns = [];
		$previous = null;
		foreach($rows as $row) {
			$name = (string) $row['name'];
			$type = (string) $row['type'];
			$nullable = ((int) $row['notnull']) === 0;
			$defaultValue = $row['dflt_value'] !== null ? new Expr((string) $row['dflt_value']) : null;
			$columns[] = new ColumnDefinition(
				name: $name,
				dataType: $type,
				nullable: $nullable,
				defaultValue: $defaultValue,
				onUpdateValue: null,
				comment: null,
				charset: null,
				collation: null,
				unsigned: null,
				autoIncrement: false,
				after: $previous,
				first: $previous === null
			);
			$previous = $name;
		}
		return $columns;
	}

	public function getColumn(string $tableName, string $columnName): ?ColumnDefinition {
		foreach($this->getColumns($tableName) as $column) {
			if($column->name === $columnName) {
				return $column;
			}
		}
		return null;
	}

	public function getIndexes(string $tableName): array {
		$table = $this->quoteIdentifier($tableName);
		$rows = $this->db->query("PRAGMA index_list({$table})")->getRows();
		$indexes = [];
		foreach($rows as $row) {
			$name = (string) $row['name'];
			$indexInfo = $this->db->query("PRAGMA index_info({$this->quoteIdentifier($name)})")->getRows();
			$columns = [];
			foreach($indexInfo as $info) {
				$columns[] = (string) $info['name'];
			}
			$indexes[] = new IndexDefinition(
				name: $name,
				columns: $columns,
				unique: ((int) $row['unique']) === 1,
				primary: (string) $row['origin'] === 'pk',
				type: null
			);
		}
		return $indexes;
	}

	public function getIndex(string $tableName, string $indexName): ?IndexDefinition {
		foreach($this->getIndexes($tableName) as $index) {
			if($index->name === $indexName) {
				return $index;
			}
		}
		return null;
	}

	public function getForeignKeys(string $tableName): array {
		$table = $this->quoteIdentifier($tableName);
		$rows = $this->db->query("PRAGMA foreign_key_list({$table})")->getRows();
		$fks = [];
		foreach($rows as $row) {
			$id = (string) $row['id'];
			if(!array_key_exists($id, $fks)) {
				$name = sprintf('fk_%s_%s', $tableName, $id);
				$fks[$id] = new ForeignKeyDefinition(
					name: $name,
					localColumns: [],
					foreignTable: (string) $row['table'],
					foreignColumns: [],
					onUpdate: $row['on_update'] !== null ? (string) $row['on_update'] : null,
					onDelete: $row['on_delete'] !== null ? (string) $row['on_delete'] : null
				);
			}
			$fks[$id]->localColumns[] = (string) $row['from'];
			$fks[$id]->foreignColumns[] = (string) $row['to'];
		}
		return array_values($fks);
	}

	public function getForeignKey(string $tableName, string $fkName): ?ForeignKeyDefinition {
		foreach($this->getForeignKeys($tableName) as $fk) {
			if($fk->name === $fkName) {
				return $fk;
			}
		}
		return null;
	}

	private function quoteIdentifier(string $name): string {
		return '`' . str_replace('`', '``', $name) . '`';
	}
}
