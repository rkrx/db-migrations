<?php
namespace Kir\DB\Migrations\Schema;

use Kir\DB\Migrations\Common\Expr;
use Kir\DB\Migrations\DBAdapter;

final class MySqlSchemaInspector implements SchemaInspector {
	public function __construct(private DBAdapter $db) {}

	public function tableExists(string $tableName): bool {
		$result = $this->db->query("
			SELECT COUNT(*)
			  FROM information_schema.tables
			 WHERE table_schema = DATABASE()
			   AND table_name = :t
		", ['t' => $tableName]);
		return (bool) $result->getValue();
	}

	public function getCreateTableSql(string $tableName): ?string {
		$table = $this->quoteIdentifier($tableName);
		$rows = $this->db->query("SHOW CREATE TABLE {$table}")->getRows();
		if(count($rows) === 0) {
			return null;
		}
		$row = $rows[0];
		foreach($row as $key => $value) {
			if(is_string($key) && stripos($key, 'create table') !== false && is_string($value)) {
				return $value;
			}
		}
		return null;
	}

	public function getColumns(string $tableName): array {
		$rows = $this->db->query("
			SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA,
			       COLUMN_COMMENT, CHARACTER_SET_NAME, COLLATION_NAME, ORDINAL_POSITION
			  FROM information_schema.columns
			 WHERE table_schema = DATABASE()
			   AND table_name = :t
			 ORDER BY ORDINAL_POSITION
		", ['t' => $tableName])->getRows();
		$columns = [];
		$previous = null;
		foreach($rows as $row) {
			$name = (string) $row['COLUMN_NAME'];
			$columnType = (string) $row['COLUMN_TYPE'];
			$nullable = $row['IS_NULLABLE'] === 'YES';
			$defaultValue = $this->parseDefaultValue($row['COLUMN_DEFAULT']);
			$extra = (string) $row['EXTRA'];
			$comment = $row['COLUMN_COMMENT'] !== null ? (string) $row['COLUMN_COMMENT'] : null;
			$charset = $row['CHARACTER_SET_NAME'] !== null ? (string) $row['CHARACTER_SET_NAME'] : null;
			$collation = $row['COLLATION_NAME'] !== null ? (string) $row['COLLATION_NAME'] : null;
			$autoIncrement = stripos($extra, 'auto_increment') !== false;
			$onUpdate = null;
			if(preg_match('/on update (.+)$/i', $extra, $matches)) {
				$onUpdate = new Expr($matches[1]);
			}

			$unsigned = stripos($columnType, 'unsigned') !== false;
			$columns[] = new ColumnDefinition(
				name: $name,
				dataType: $columnType,
				nullable: $nullable,
				defaultValue: $defaultValue,
				onUpdateValue: $onUpdate,
				comment: $comment,
				charset: $charset,
				collation: $collation,
				unsigned: $unsigned,
				autoIncrement: $autoIncrement,
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
		$rows = $this->db->query("
			SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX, INDEX_TYPE
			  FROM information_schema.statistics
			 WHERE table_schema = DATABASE()
			   AND table_name = :t
			 ORDER BY INDEX_NAME, SEQ_IN_INDEX
		", ['t' => $tableName])->getRows();
		$indexes = [];
		foreach($rows as $row) {
			$name = (string) $row['INDEX_NAME'];
			if(!array_key_exists($name, $indexes)) {
				$indexes[$name] = new IndexDefinition(
					name: $name,
					columns: [],
					unique: (int) $row['NON_UNIQUE'] === 0,
					primary: $name === 'PRIMARY',
					type: $row['INDEX_TYPE'] !== null ? (string) $row['INDEX_TYPE'] : null
				);
			}
			$indexes[$name]->columns[] = (string) $row['COLUMN_NAME'];
		}
		return array_values($indexes);
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
		$rows = $this->db->query("
			SELECT rc.CONSTRAINT_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME,
			       kcu.REFERENCED_COLUMN_NAME, rc.UPDATE_RULE, rc.DELETE_RULE, kcu.ORDINAL_POSITION
			  FROM information_schema.referential_constraints rc
			  JOIN information_schema.key_column_usage kcu
			    ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
			   AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
			 WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
			   AND rc.TABLE_NAME = :t
			 ORDER BY rc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
		", ['t' => $tableName])->getRows();
		$fks = [];
		foreach($rows as $row) {
			$name = (string) $row['CONSTRAINT_NAME'];
			if(!array_key_exists($name, $fks)) {
				$fks[$name] = new ForeignKeyDefinition(
					name: $name,
					localColumns: [],
					foreignTable: (string) $row['REFERENCED_TABLE_NAME'],
					foreignColumns: [],
					onUpdate: $row['UPDATE_RULE'] !== null ? (string) $row['UPDATE_RULE'] : null,
					onDelete: $row['DELETE_RULE'] !== null ? (string) $row['DELETE_RULE'] : null
				);
			}
			$fks[$name]->localColumns[] = (string) $row['COLUMN_NAME'];
			$fks[$name]->foreignColumns[] = (string) $row['REFERENCED_COLUMN_NAME'];
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

	private function parseDefaultValue(mixed $value): bool|int|float|string|Expr|null {
		if($value === null) {
			return null;
		}
		if(!is_string($value)) {
			return $value;
		}
		$trimmed = trim($value);
		if(preg_match("/^'.*'$/", $trimmed)) {
			return substr($trimmed, 1, -1);
		}
		if(preg_match('/^-?\d+$/', $trimmed)) {
			return (int) $trimmed;
		}
		if(preg_match('/^-?\d*\.\d+$/', $trimmed)) {
			return (float) $trimmed;
		}
		$upper = strtoupper($trimmed);
		if(in_array($upper, ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()', 'NOW()', 'NULL'], true)) {
			return new Expr($trimmed);
		}
		return $trimmed;
	}
}
