<?php
namespace Kir\DB\Migrations\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Kir\DB\Migrations\Common\Expr;
use Throwable;

final class DbalSqlRenderer implements SqlRendererInterface {
	/**
	 * @param AbstractSchemaManager<\Doctrine\DBAL\Platforms\AbstractPlatform> $schemaManager
	 */
	public function __construct(
		private AbstractSchemaManager $schemaManager,
		private AbstractPlatform $platform
	) {}

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
	): array {
		$table = new Table($tableName);
		foreach($columns as $column) {
			[$typeName, $typeOptions] = $this->parseType($column->dataType);
			$options = $this->mergeColumnOptions($column, $typeOptions);
			$table->addColumn($column->name, $typeName, $options);
		}
		if($primaryKey !== []) {
			$table->setPrimaryKey($primaryKey);
		}
		foreach($uniqueIndexes as $index) {
			$table->addUniqueIndex($index->columns, $index->name);
		}
		foreach($indexes as $index) {
			$table->addIndex($index->columns, $index->name);
		}
		foreach($foreignKeys as $fk) {
			$table->addForeignKeyConstraint(
				$fk->foreignTable,
				$fk->localColumns,
				$fk->foreignColumns,
				$this->foreignKeyOptions($fk),
				$fk->name
			);
		}
		if($this->isMySqlPlatform()) {
			if($engine === null) {
				$engine = 'InnoDB';
			}
			if($charset === null) {
				$charset = 'utf8mb4';
			}
			$table->addOption('engine', $engine);
			$table->addOption('charset', $charset);
			if($collation !== null) {
				$table->addOption('collation', $collation);
			}
		}
		return $this->platform->getCreateTableSQL($table);
	}

	public function renderDropTable(string $tableName): string {
		return $this->platform->getDropTableSQL($tableName);
	}

	public function renderAddColumn(string $tableName, ColumnDefinition $column): array {
		return $this->diffTable($tableName, function (Table $table) use ($column): void {
			[$typeName, $typeOptions] = $this->parseType($column->dataType);
			$options = $this->mergeColumnOptions($column, $typeOptions);
			$table->addColumn($column->name, $typeName, $options);
		});
	}

	public function renderDropColumn(string $tableName, string $columnName): array {
		return $this->diffTable($tableName, function (Table $table) use ($columnName): void {
			if($table->hasColumn($columnName)) {
				$table->dropColumn($columnName);
			}
		});
	}

	public function renderModifyColumn(string $tableName, ColumnDefinition $column): array {
		return $this->diffTable($tableName, function (Table $table) use ($column): void {
			if(!$table->hasColumn($column->name)) {
				return;
			}
			[$typeName, $typeOptions] = $this->parseType($column->dataType);
			$options = $this->mergeColumnOptions($column, $typeOptions);
			try {
				$options['type'] = Type::getType($typeName);
			} catch(Throwable $e) {
				$options['type'] = Type::getType('string');
				$options['columnDefinition'] ??= $column->dataType;
			}
			$table->changeColumn($column->name, $options);
		});
	}

	public function renderAddIndex(string $tableName, IndexDefinition $index): array {
		return $this->diffTable($tableName, function (Table $table) use ($index): void {
			if($table->hasIndex($index->name)) {
				return;
			}
			if($index->unique) {
				$table->addUniqueIndex($index->columns, $index->name);
				return;
			}
			$table->addIndex($index->columns, $index->name);
		});
	}

	public function renderDropIndex(string $tableName, string $indexName): array {
		return $this->diffTable($tableName, function (Table $table) use ($indexName): void {
			if($table->hasIndex($indexName)) {
				$table->dropIndex($indexName);
			}
		});
	}

	public function renderAddPrimaryKey(string $tableName, array $columns): array {
		return $this->diffTable($tableName, function (Table $table) use ($columns): void {
			$table->setPrimaryKey($columns);
		});
	}

	public function renderDropPrimaryKey(string $tableName): array {
		return $this->diffTable($tableName, function (Table $table): void {
			if($table->hasPrimaryKey()) {
				$table->dropPrimaryKey();
			}
		});
	}

	public function renderAddForeignKey(string $tableName, ForeignKeyDefinition $fk): array {
		return $this->diffTable($tableName, function (Table $table) use ($fk): void {
			$table->addForeignKeyConstraint(
				$fk->foreignTable,
				$fk->localColumns,
				$fk->foreignColumns,
				$this->foreignKeyOptions($fk),
				$fk->name
			);
		});
	}

	public function renderDropForeignKey(string $tableName, string $fkName): array {
		return $this->diffTable($tableName, function (Table $table) use ($fkName): void {
			if($table->hasForeignKey($fkName)) {
				$table->removeForeignKey($fkName);
			}
		});
	}

	/**
	 * @return array<int, string>
	 */
	private function diffTable(string $tableName, callable $mutator): array {
		try {
			$fromTable = $this->schemaManager->introspectTable($tableName);
		} catch(Throwable $e) {
			return [];
		}
		$toTable = clone $fromTable;
		$mutator($toTable);
		$comparator = new Comparator();
		$diff = $comparator->diffTable($fromTable, $toTable);
		if($diff === false) {
			return [];
		}
		return $this->platform->getAlterTableSQL($diff);
	}

	/**
	 * @return array{0: string, 1: array<string, mixed>}
	 */
	private function parseType(string $raw): array {
		$upper = strtoupper(trim($raw));
		if(!preg_match('/^([A-Z]+)\s*(?:\(([^)]+)\))?/', $upper, $matches)) {
			return ['string', ['columnDefinition' => $raw]];
		}
		$base = $matches[1];
		$args = $matches[2] ?? null;
		$options = [];
		return match($base) {
			'INT', 'INTEGER' => ['integer', $options],
			'BIGINT' => ['bigint', $options],
			'SMALLINT' => ['smallint', $options],
			'TINYINT' => ['smallint', $options],
			'VARCHAR' => ['string', $this->withLength($options, $args)],
			'CHAR' => ['string', $this->withLength($this->withFixed($options), $args)],
			'TEXT' => ['text', $options],
			'DECIMAL' => ['decimal', $this->withPrecisionScale($options, $args)],
			'FLOAT' => ['float', $options],
			'DOUBLE' => ['double', $options],
			'BOOLEAN', 'BOOL' => ['boolean', $options],
			'DATE' => ['date', $options],
			'DATETIME' => ['datetime', $options],
			'TIMESTAMP' => ['datetime', $options],
			'JSON' => ['json', $options],
			default => ['string', ['columnDefinition' => $raw]],
		};
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private function withLength(array $options, ?string $args): array {
		if($args === null) {
			return $options;
		}
		$length = (int) trim($args);
		if($length > 0) {
			$options['length'] = $length;
		}
		return $options;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private function withPrecisionScale(array $options, ?string $args): array {
		if($args === null) {
			return $options;
		}
		$parts = array_map('trim', explode(',', $args));
		if(is_numeric($parts[0])) {
			$options['precision'] = (int) $parts[0];
		}
		if(isset($parts[1]) && is_numeric($parts[1])) {
			$options['scale'] = (int) $parts[1];
		}
		return $options;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private function withFixed(array $options): array {
		$options['fixed'] = true;
		return $options;
	}

	/**
	 * @param array<string, mixed> $typeOptions
	 * @return array<string, mixed>
	 */
	private function mergeColumnOptions(ColumnDefinition $column, array $typeOptions): array {
		$options = $typeOptions;
		$options['notnull'] = !$column->nullable;
		$options['autoincrement'] = $column->autoIncrement;

		$default = $column->defaultValue;
		if($default instanceof Expr) {
			$default = $default->expr;
		}
		if($default !== null) {
			$options['default'] = $default;
		}
		if($column->comment !== null) {
			$options['comment'] = $column->comment;
		}
		if($this->isMySqlPlatform()) {
			if($column->unsigned !== null) {
				$options['unsigned'] = $column->unsigned;
			}
			$platformOptions = [];
			if($column->charset !== null) {
				$platformOptions['charset'] = $column->charset;
			}
			if($column->collation !== null) {
				$platformOptions['collation'] = $column->collation;
			}
			if($column->onUpdateValue !== null) {
				$platformOptions['onUpdate'] = $column->onUpdateValue->expr ?? null;
			}
			if($platformOptions !== []) {
				$options['platformOptions'] = $platformOptions;
			}
		}
		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	private function foreignKeyOptions(ForeignKeyDefinition $fk): array {
		$options = [];
		if($fk->onDelete !== null) {
			$options['onDelete'] = $fk->onDelete;
		}
		if($fk->onUpdate !== null) {
			$options['onUpdate'] = $fk->onUpdate;
		}
		return $options;
	}

	private function isMySqlPlatform(): bool {
		return $this->platform instanceof AbstractMySQLPlatform;
	}
}
