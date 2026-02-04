<?php
namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\Contracts\MigrationStep;
use Kir\DB\Migrations\Schema\ColumnDefinition;
use Kir\DB\Migrations\Schema\Feature;
use Kir\DB\Migrations\Schema\ForeignKeyDefinition;
use Kir\DB\Migrations\Schema\IndexDefinition;
use Kir\DB\Migrations\Schema\MigrationContext;

final class AlterTableAddBuilder implements MigrationStep {
	use ColumnBuilderTrait;

	/** @var array<int, array{type: string, payload: mixed}> */
	private array $operations = [];

	public function __construct(private string $tableName) {}

	protected function addColumnDefinition(ColumnDefinition $column): void {
		$this->operations[] = ['type' => 'addColumn', 'payload' => $column];
	}

	public function index(string $name, string ...$columns): self {
		$this->operations[] = ['type' => 'addIndex', 'payload' => new IndexDefinition($name, $columns, false, false)];
		return $this;
	}

	public function uniqueIndex(string $name, string ...$columns): self {
		$this->operations[] = ['type' => 'addUniqueIndex', 'payload' => new IndexDefinition($name, $columns, true, false)];
		return $this;
	}

	public function primaryKey(string ...$columns): self {
		$this->operations[] = ['type' => 'addPrimaryKey', 'payload' => $columns];
		return $this;
	}

	/**
	 * @param string[] $localColumns
	 * @param string[] $foreignColumns
	 */
	public function foreignKey(
		string $name,
		array $localColumns,
		string $foreignTable,
		array $foreignColumns,
		?string $onUpdate = null,
		?string $onDelete = null
	): self {
		$this->operations[] = [
			'type' => 'addForeignKey',
			'payload' => new ForeignKeyDefinition(
				name: $name,
				localColumns: $localColumns,
				foreignTable: $foreignTable,
				foreignColumns: $foreignColumns,
				onUpdate: $onUpdate,
				onDelete: $onDelete
			)
		];
		return $this;
	}

	public function statements(MigrationContext $context): array {
		$statements = [];
		foreach($this->operations as $operation) {
			$type = $operation['type'];
			$payload = $operation['payload'];
			$statement = match($type) {
				'addColumn' => $this->buildAddColumn($context, $payload),
				'addIndex' => $this->buildAddIndex($context, $payload),
				'addUniqueIndex' => $this->buildAddIndex($context, $payload),
				'addPrimaryKey' => $this->buildAddPrimaryKey($context, $payload),
				'addForeignKey' => $this->buildAddForeignKey($context, $payload),
				default => null,
			};
			if($statement !== null) {
				$statements[] = $statement;
			}
		}
		return $statements;
	}

	private function buildAddColumn(MigrationContext $context, ColumnDefinition $column): array {
		$required = array_merge(
			[Feature::ALTER_ADD_COLUMN, Feature::ALTER_DROP_COLUMN],
			$this->columnFeatures($column)
		);
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return $context->skipStatement($this->skipMessage($context, "add column {$column->name}", $unsupported));
		}

		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$sql = $context->sql;
		$added = false;
		$up = function ($db) use ($inspector, $sql, $tableName, $column, &$added): void {
			if($inspector->getColumn($tableName, $column->name) !== null) {
				$added = false;
				return;
			}
			$db->exec($sql->renderAddColumn($tableName, $column));
			$added = true;
		};
		$down = function ($db) use ($sql, $tableName, $column, &$added): void {
			if(!$added) {
				return;
			}
			$db->exec($sql->renderDropColumn($tableName, $column->name));
		};
		return ['up' => $up, 'down' => $down];
	}

	private function buildAddIndex(MigrationContext $context, IndexDefinition $index): array {
		$required = [Feature::ALTER_ADD_INDEX, Feature::ALTER_DROP_INDEX];
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return $context->skipStatement($this->skipMessage($context, "add index {$index->name}", $unsupported));
		}
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$sql = $context->sql;
		$created = false;
		$up = function ($db) use ($inspector, $sql, $tableName, $index, &$created): void {
			if($inspector->getIndex($tableName, $index->name) !== null) {
				$created = false;
				return;
			}
			$db->exec($sql->renderAddIndex($tableName, $index));
			$created = true;
		};
		$down = function ($db) use ($sql, $tableName, $index, &$created): void {
			if(!$created) {
				return;
			}
			$db->exec($sql->renderDropIndex($tableName, $index->name));
		};
		return ['up' => $up, 'down' => $down];
	}

	private function buildAddPrimaryKey(MigrationContext $context, array $columns): array {
		$required = [Feature::ALTER_ADD_PRIMARY_KEY, Feature::ALTER_DROP_PRIMARY_KEY];
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return $context->skipStatement($this->skipMessage($context, 'add primary key', $unsupported));
		}
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$sql = $context->sql;
		$created = false;
		$up = function ($db) use ($inspector, $sql, $tableName, $columns, &$created): void {
			if($inspector->getIndex($tableName, 'PRIMARY') !== null) {
				$created = false;
				return;
			}
			$db->exec($sql->renderAddPrimaryKey($tableName, $columns));
			$created = true;
		};
		$down = function ($db) use ($sql, $tableName, &$created): void {
			if(!$created) {
				return;
			}
			$db->exec($sql->renderDropPrimaryKey($tableName));
		};
		return ['up' => $up, 'down' => $down];
	}

	private function buildAddForeignKey(MigrationContext $context, ForeignKeyDefinition $fk): array {
		$required = [Feature::ALTER_ADD_FOREIGN_KEY, Feature::ALTER_DROP_FOREIGN_KEY];
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return $context->skipStatement($this->skipMessage($context, "add foreign key {$fk->name}", $unsupported));
		}
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$sql = $context->sql;
		$created = false;
		$up = function ($db) use ($inspector, $sql, $tableName, $fk, &$created, $context): void {
			if($inspector->getForeignKey($tableName, $fk->name) !== null) {
				$created = false;
				return;
			}
			if($context->engine->engine === 'mysql' || $context->engine->engine === 'mariadb') {
				$indexes = $inspector->getIndexes($tableName);
				if(!$this->hasSupportingIndex($indexes, $fk->localColumns)) {
					$context->logger->warning(sprintf(
						'Skip UP add foreign key %s on %s: missing index covering (%s).',
						$fk->name,
						$tableName,
						implode(', ', $fk->localColumns)
					));
					$created = false;
					return;
				}
			}
			$db->exec($sql->renderAddForeignKey($tableName, $fk));
			$created = true;
		};
		$down = function ($db) use ($sql, $tableName, $fk, &$created): void {
			if(!$created) {
				return;
			}
			$db->exec($sql->renderDropForeignKey($tableName, $fk->name));
		};
		return ['up' => $up, 'down' => $down];
	}

	/**
	 * @param IndexDefinition[] $indexes
	 * @param string[] $localColumns
	 */
	private function hasSupportingIndex(array $indexes, array $localColumns): bool {
		$needed = count($localColumns);
		foreach($indexes as $index) {
			if(count($index->columns) < $needed) {
				continue;
			}
			if(array_slice($index->columns, 0, $needed) === $localColumns) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return string[]
	 */
	private function columnFeatures(ColumnDefinition $column): array {
		$features = [];
		if($column->comment !== null) {
			$features[] = Feature::COLUMN_COMMENT;
		}
		if($column->charset !== null) {
			$features[] = Feature::COLUMN_CHARSET;
		}
		if($column->collation !== null) {
			$features[] = Feature::COLUMN_COLLATION;
		}
		if($column->autoIncrement) {
			$features[] = Feature::AUTO_INCREMENT;
		}
		if($column->onUpdateValue !== null) {
			$features[] = Feature::COLUMN_ON_UPDATE;
		}
		if($column->unsigned || stripos($column->dataType, 'unsigned') !== false) {
			$features[] = Feature::COLUMN_UNSIGNED;
		}
		return $features;
	}

	private function skipMessage(MigrationContext $context, string $action, array $unsupported): string {
		return sprintf(
			'Skip UP %s on %s: unsupported features for %s (%s).',
			$action,
			$this->tableName,
			$context->features->describeEngine(),
			implode(', ', $unsupported)
		);
	}
}
