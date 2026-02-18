<?php
namespace Kir\DB\Migrations\Schema;

use Kir\DB\Migrations\Common\Expr;

final class SqlRenderer implements SqlRendererInterface {
	public function __construct(private EngineInfo $engine) {}

	public function quoteIdentifier(string $name): string {
		return '`' . str_replace('`', '``', $name) . '`';
	}

	public function quoteValue(string $value): string {
		return "'" . str_replace("'", "''", $value) . "'";
	}

	public function renderDefaultValue(bool|int|float|string|Expr $value): string {
		if($value instanceof Expr) {
			return $value->expr ?? 'NULL';
		}
		if(is_bool($value)) {
			return $value ? '1' : '0';
		}
		if(is_int($value) || is_float($value)) {
			return (string) $value;
		}
		return $this->quoteValue($value);
	}

	public function renderColumnDefinition(ColumnDefinition $column): string {
		$type = $column->dataType;
		if($column->unsigned && stripos($type, 'unsigned') === false) {
			$type .= ' UNSIGNED';
		}
		$parts = [
			$this->quoteIdentifier($column->name),
			$type,
			$column->nullable ? 'NULL' : 'NOT NULL'
		];
		if($column->defaultValue !== null) {
			$parts[] = 'DEFAULT ' . $this->renderDefaultValue($column->defaultValue);
		}
		if($column->onUpdateValue !== null) {
			$parts[] = 'ON UPDATE ' . $this->renderDefaultValue($column->onUpdateValue);
		}
		if($column->autoIncrement) {
			$parts[] = 'AUTO_INCREMENT';
		}
		if($column->comment !== null) {
			$parts[] = 'COMMENT ' . $this->quoteValue($column->comment);
		}
		if($column->charset !== null) {
			$parts[] = 'CHARACTER SET ' . $column->charset;
		}
		if($column->collation !== null) {
			$parts[] = 'COLLATE ' . $column->collation;
		}
		return implode(' ', $parts);
	}

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
	): string {
		$isSqlite = $this->engine->engine === 'sqlite';
		if($this->engine->engine === 'mysql' || $this->engine->engine === 'mariadb') {
			if($engine === null) {
				$engine = 'InnoDB';
			}
			if($charset === null) {
				$charset = 'utf8mb4';
			}
		}
		$lines = [];
		foreach($columns as $column) {
			$lines[] = $this->renderColumnDefinition($column);
		}
		if(count($primaryKey) > 0) {
			$lines[] = sprintf('PRIMARY KEY (%s)', $this->renderColumnList($primaryKey));
		}
		foreach($uniqueIndexes as $index) {
			$lines[] = sprintf(
				$isSqlite ? 'CONSTRAINT %s UNIQUE (%s)' : 'UNIQUE KEY %s (%s)',
				$this->quoteIdentifier($index->name),
				$this->renderColumnList($index->columns)
			);
		}
		if(!$isSqlite) {
			foreach($indexes as $index) {
				$lines[] = sprintf(
					'KEY %s (%s)',
					$this->quoteIdentifier($index->name),
					$this->renderColumnList($index->columns)
				);
			}
		}
		foreach($foreignKeys as $fk) {
			$lines[] = sprintf(
				'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s)%s%s',
				$this->quoteIdentifier($fk->name),
				$this->renderColumnList($fk->localColumns),
				$this->quoteIdentifier($fk->foreignTable),
				$this->renderColumnList($fk->foreignColumns),
				$fk->onDelete ? ' ON DELETE ' . $fk->onDelete : '',
				$fk->onUpdate ? ' ON UPDATE ' . $fk->onUpdate : ''
			);
		}
		$table = $this->quoteIdentifier($tableName);
		$sql = sprintf("CREATE TABLE IF NOT EXISTS %s (\n\t%s\n)", $table, implode(",\n\t", $lines));
		if($engine !== null) {
			$sql .= ' ENGINE=' . $engine;
		}
		if($charset !== null) {
			$sql .= ' DEFAULT CHARSET=' . $charset;
		}
		if($collation !== null) {
			$sql .= ' COLLATE=' . $collation;
		}
		$sql .= ';';
		return $sql;
	}

	public function renderDropTable(string $tableName): string {
		$table = $this->quoteIdentifier($tableName);
		return "DROP TABLE IF EXISTS {$table};";
	}

	public function renderAddColumn(string $tableName, ColumnDefinition $column): string {
		$table = $this->quoteIdentifier($tableName);
		$sql = sprintf('ALTER TABLE %s ADD COLUMN %s', $table, $this->renderColumnDefinition($column));
		if($this->engine->engine !== 'sqlite') {
			if($column->first) {
				$sql .= ' FIRST';
			} elseif($column->after !== null) {
				$sql .= ' AFTER ' . $this->quoteIdentifier($column->after);
			}
		}
		return $sql . ';';
	}

	public function renderDropColumn(string $tableName, string $columnName): string {
		$table = $this->quoteIdentifier($tableName);
		$column = $this->quoteIdentifier($columnName);
		return "ALTER TABLE {$table} DROP COLUMN {$column};";
	}

	public function renderModifyColumn(string $tableName, ColumnDefinition $column): string {
		$table = $this->quoteIdentifier($tableName);
		$sql = sprintf('ALTER TABLE %s MODIFY COLUMN %s', $table, $this->renderColumnDefinition($column));
		if($this->engine->engine !== 'sqlite') {
			if($column->first) {
				$sql .= ' FIRST';
			} elseif($column->after !== null) {
				$sql .= ' AFTER ' . $this->quoteIdentifier($column->after);
			}
		}
		return $sql . ';';
	}

	public function renderAddIndex(string $tableName, IndexDefinition $index): string {
		$table = $this->quoteIdentifier($tableName);
		$indexName = $this->quoteIdentifier($index->name);
		$columns = $this->renderColumnList($index->columns);
		$unique = $index->unique ? 'UNIQUE ' : '';
		return sprintf('CREATE %sINDEX %s ON %s (%s);', $unique, $indexName, $table, $columns);
	}

	public function renderDropIndex(string $tableName, string $indexName): string {
		$index = $this->quoteIdentifier($indexName);
		if($this->engine->engine === 'sqlite') {
			return "DROP INDEX {$index};";
		}
		$table = $this->quoteIdentifier($tableName);
		return "DROP INDEX {$index} ON {$table};";
	}

	public function renderAddPrimaryKey(string $tableName, array $columns): string {
		$table = $this->quoteIdentifier($tableName);
		return sprintf('ALTER TABLE %s ADD PRIMARY KEY (%s);', $table, $this->renderColumnList($columns));
	}

	public function renderDropPrimaryKey(string $tableName): string {
		$table = $this->quoteIdentifier($tableName);
		return "ALTER TABLE {$table} DROP PRIMARY KEY;";
	}

	public function renderAddForeignKey(string $tableName, ForeignKeyDefinition $fk): string {
		$table = $this->quoteIdentifier($tableName);
		$sql = sprintf(
			'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s)',
			$table,
			$this->quoteIdentifier($fk->name),
			$this->renderColumnList($fk->localColumns),
			$this->quoteIdentifier($fk->foreignTable),
			$this->renderColumnList($fk->foreignColumns)
		);
		if($fk->onDelete) {
			$sql .= ' ON DELETE ' . $fk->onDelete;
		}
		if($fk->onUpdate) {
			$sql .= ' ON UPDATE ' . $fk->onUpdate;
		}
		return $sql . ';';
	}

	public function renderDropForeignKey(string $tableName, string $fkName): string {
		$table = $this->quoteIdentifier($tableName);
		$fk = $this->quoteIdentifier($fkName);
		return "ALTER TABLE {$table} DROP FOREIGN KEY {$fk};";
	}

	/**
	 * @param string[] $columns
	 */
	private function renderColumnList(array $columns): string {
		$parts = array_map(fn(string $column) => $this->quoteIdentifier($column), $columns);
		return implode(', ', $parts);
	}
}
