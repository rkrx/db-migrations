<?php
namespace Kir\DB\Migrations\Schema;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Kir\DB\Migrations\Common\Expr;
use Throwable;

final class DbalSchemaInspector implements SchemaInspector {
	/**
	 * @param AbstractSchemaManager<AbstractPlatform> $schemaManager
	 */
	public function __construct(
		private Connection $connection,
		private AbstractSchemaManager $schemaManager,
	) {}

	public function tableExists(string $tableName): bool {
		return $this->schemaManager->tablesExist([$tableName]);
	}

	/**
	 * @return array<int, string>|null
	 */
	public function getCreateTableSql(string $tableName): array|null {
		if(!$this->tableExists($tableName)) {
			return null;
		}
		try {
			$table = $this->schemaManager->introspectTable($tableName);
			$sql = $this->connection->getDatabasePlatform()->getCreateTableSQL($table);
			return $sql;
		} catch(Throwable $e) {
			return null;
		}
	}

	public function getColumns(string $tableName): array {
		try {
			$rows = $this->schemaManager->listTableColumns($tableName);
		} catch(Throwable $e) {
			return [];
		}
		$columns = [];
		$previous = null;
		foreach($rows as $column) {
			$columns[] = $this->mapColumn($column, $previous);
			$previous = $column->getName();
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
		try {
			$rows = $this->schemaManager->listTableIndexes($tableName);
		} catch(Throwable $e) {
			return [];
		}
		$indexes = [];
		foreach($rows as $index) {
			$indexes[] = $this->mapIndex($index);
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
		try {
			$rows = $this->schemaManager->listTableForeignKeys($tableName);
		} catch(Throwable $e) {
			return [];
		}
		$fks = [];
		foreach($rows as $fk) {
			$fks[] = $this->mapForeignKey($fk, $tableName);
		}
		return $fks;
	}

	public function getForeignKey(string $tableName, string $fkName): ?ForeignKeyDefinition {
		foreach($this->getForeignKeys($tableName) as $fk) {
			if($fk->name === $fkName) {
				return $fk;
			}
		}
		return null;
	}

	private function mapColumn(Column $column, ?string $previous): ColumnDefinition {
		$platform = $this->connection->getDatabasePlatform();
		$dataType = $column->getType()->getSQLDeclaration($column->toArray(), $platform);
		$defaultValue = $this->normalizeDefaultValue($column->getDefault());
		$unsigned = $column->getUnsigned();
		$charset = $this->getPlatformOption($column, 'charset');
		$collation = $this->getPlatformOption($column, 'collation');

		return new ColumnDefinition(
			name: $column->getName(),
			dataType: $dataType,
			nullable: !$column->getNotnull(),
			defaultValue: $defaultValue,
			onUpdateValue: null,
			comment: $column->getComment() !== '' ? $column->getComment() : null,
			charset: is_string($charset) ? $charset : null,
			collation: is_string($collation) ? $collation : null,
			unsigned: $unsigned,
			autoIncrement: $column->getAutoincrement(),
			after: $previous,
			first: $previous === null
		);
	}

	private function mapIndex(Index $index): IndexDefinition {
		$name = $index->isPrimary() ? 'PRIMARY' : $index->getName();
		return new IndexDefinition(
			name: $name,
			columns: $index->getColumns(),
			unique: $index->isUnique(),
			primary: $index->isPrimary(),
			type: null
		);
	}

	private function mapForeignKey(ForeignKeyConstraint $fk, string $tableName): ForeignKeyDefinition {
		$name = $fk->getName() ?: sprintf('fk_%s_%s', $tableName, spl_object_id($fk));
		$onDelete = $fk->hasOption('onDelete') ? $fk->getOption('onDelete') : null;
		$onUpdate = $fk->hasOption('onUpdate') ? $fk->getOption('onUpdate') : null;
		return new ForeignKeyDefinition(
			name: $name,
			localColumns: $fk->getLocalColumns(),
			foreignTable: $fk->getForeignTableName(),
			foreignColumns: $fk->getForeignColumns(),
			onUpdate: is_string($onUpdate) ? $onUpdate : null,
			onDelete: is_string($onDelete) ? $onDelete : null
		);
	}

	private function getPlatformOption(Column $column, string $key): mixed {
		$options = $column->getPlatformOptions();
		return $options[$key] ?? null;
	}

	private function looksLikeExpression(string $value): bool {
		$upper = strtoupper(trim($value));
		return in_array($upper, ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()', 'CURRENT_DATE', 'CURRENT_TIME', 'NOW()', 'NULL'], true);
	}

	private function normalizeDefaultValue(mixed $value): bool|int|float|string|Expr|null {
		if($value === null) {
			return null;
		}
		if($value instanceof Expr) {
			return $value;
		}
		if(is_string($value) && $this->looksLikeExpression($value)) {
			return new Expr($value);
		}
		if(is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
			return $value;
		}
		if(is_object($value) && method_exists($value, '__toString')) {
			return (string) $value;
		}
		return null;
	}
}
