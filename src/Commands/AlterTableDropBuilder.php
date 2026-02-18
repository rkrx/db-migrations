<?php
namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\Contracts\MigrationStep;
use Kir\DB\Migrations\DBAdapter;
use Kir\DB\Migrations\Schema\ColumnDefinition;
use Kir\DB\Migrations\Schema\Feature;
use Kir\DB\Migrations\Schema\MigrationContext;

/**
 * @phpstan-type TDropColumn array{type: 'dropColumn'|'dropIndex'|'dropForeignKey', payload: string}
 * @phpstan-type TDropPrimaryKey array{type: 'dropPrimaryKey', payload: null}
 */
final class AlterTableDropBuilder implements MigrationStep {
	/**
	 * @var array<int, TDropColumn|TDropPrimaryKey>
	 */
	private array $operations = [];

	public function __construct(private string $tableName) {}

	public function column(string $name): self {
		$this->operations[] = ['type' => 'dropColumn', 'payload' => $name];
		return $this;
	}

	public function index(string $name): self {
		$this->operations[] = ['type' => 'dropIndex', 'payload' => $name];
		return $this;
	}

	public function primaryKey(): self {
		$this->operations[] = ['type' => 'dropPrimaryKey', 'payload' => null];
		return $this;
	}

	public function foreignKey(string $name): self {
		$this->operations[] = ['type' => 'dropForeignKey', 'payload' => $name];
		return $this;
	}

	/**
	 * @return array<array{up?: \Closure|string|array<int, string>, down?: \Closure|string|array<int, string>}>
	 */
	public function statements(MigrationContext $context): array {
		$statements = [];
		foreach($this->operations as $operation) {
			$type = $operation['type'];
			$payload = $operation['payload'];
			$statement = null;
			switch($type) {
				case 'dropColumn':
					if(is_string($payload)) {
						$statement = $this->buildDropColumn($context, $payload);
					}
					break;
				case 'dropIndex':
					if(is_string($payload)) {
						$statement = $this->buildDropIndex($context, $payload);
					}
					break;
				case 'dropPrimaryKey':
					$statement = $this->buildDropPrimaryKey($context);
					break;
				case 'dropForeignKey':
					if(is_string($payload)) {
						$statement = $this->buildDropForeignKey($context, $payload);
					}
					break;
				default:
					break;
			}
			if($statement !== null) {
				$statements[] = $statement;
			}
		}
		return $statements;
	}

	/**
	 * @return array{up: \Closure, down: \Closure}|array{up: \Closure}
	 */
	private function buildDropColumn(MigrationContext $context, string $columnName): array {
		$required = [Feature::ALTER_DROP_COLUMN, Feature::ALTER_ADD_COLUMN];
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return $context->skipStatement($this->skipMessage($context, "drop column {$columnName}", $unsupported));
		}
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$sql = $context->sql;
		$columnSnapshot = null;
		$dropped = false;
		$up = function (DBAdapter $db) use ($inspector, $sql, $tableName, $columnName, &$columnSnapshot, &$dropped, $context): void {
			$columnSnapshot = $inspector->getColumn($tableName, $columnName);
			if($columnSnapshot === null) {
				$dropped = false;
				return;
			}
			$extraUnsupported = $context->features->unsupported($this->columnFeatures($columnSnapshot));
			if($extraUnsupported !== []) {
				$context->logger->warning($this->skipMessage($context, "drop column {$columnName}", $extraUnsupported));
				$dropped = false;
				return;
			}
			$context->execSql($db, $sql->renderDropColumn($tableName, $columnName));
			$dropped = true;
		};
		$down = function (DBAdapter $db) use ($sql, $tableName, &$columnSnapshot, &$dropped, $context): void {
			if(!$dropped || $columnSnapshot === null) {
				return;
			}
			$context->execSql($db, $sql->renderAddColumn($tableName, $columnSnapshot));
		};
		return ['up' => $up, 'down' => $down];
	}

	/**
	 * @return array{up: \Closure, down: \Closure}|array{up: \Closure}
	 */
	private function buildDropIndex(MigrationContext $context, string $indexName): array {
		$required = [Feature::ALTER_DROP_INDEX, Feature::ALTER_ADD_INDEX];
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return $context->skipStatement($this->skipMessage($context, "drop index {$indexName}", $unsupported));
		}
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$sql = $context->sql;
		$indexSnapshot = null;
		$dropped = false;
		$up = function (DBAdapter $db) use ($inspector, $sql, $tableName, $indexName, &$indexSnapshot, &$dropped, $context): void {
			$indexSnapshot = $inspector->getIndex($tableName, $indexName);
			if($indexSnapshot === null) {
				$dropped = false;
				return;
			}
			$context->execSql($db, $sql->renderDropIndex($tableName, $indexName));
			$dropped = true;
		};
		$down = function (DBAdapter $db) use ($sql, $tableName, &$indexSnapshot, &$dropped, $context): void {
			if(!$dropped || $indexSnapshot === null) {
				return;
			}
			$context->execSql($db, $sql->renderAddIndex($tableName, $indexSnapshot));
		};
		return ['up' => $up, 'down' => $down];
	}

	/**
	 * @return array{up: \Closure, down: \Closure}|array{up: \Closure}
	 */
	private function buildDropPrimaryKey(MigrationContext $context): array {
		$required = [Feature::ALTER_DROP_PRIMARY_KEY, Feature::ALTER_ADD_PRIMARY_KEY];
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return $context->skipStatement($this->skipMessage($context, 'drop primary key', $unsupported));
		}
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$sql = $context->sql;
		$primaryColumns = [];
		$dropped = false;
		$up = function (DBAdapter $db) use ($inspector, $sql, $tableName, &$primaryColumns, &$dropped, $context): void {
			$index = $inspector->getIndex($tableName, 'PRIMARY');
			if($index === null || $index->columns === []) {
				$dropped = false;
				return;
			}
			$primaryColumns = $index->columns;
			$context->execSql($db, $sql->renderDropPrimaryKey($tableName));
			$dropped = true;
		};
		$down = function (DBAdapter $db) use ($sql, $tableName, &$primaryColumns, &$dropped, $context): void {
			if(!$dropped || $primaryColumns === []) {
				return;
			}
			$context->execSql($db, $sql->renderAddPrimaryKey($tableName, $primaryColumns));
		};
		return ['up' => $up, 'down' => $down];
	}

	/**
	 * @return array{up: \Closure, down: \Closure}|array{up: \Closure}
	 */
	private function buildDropForeignKey(MigrationContext $context, string $fkName): array {
		$required = [Feature::ALTER_DROP_FOREIGN_KEY, Feature::ALTER_ADD_FOREIGN_KEY];
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return $context->skipStatement($this->skipMessage($context, "drop foreign key {$fkName}", $unsupported));
		}
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$sql = $context->sql;
		$fkSnapshot = null;
		$dropped = false;
		$up = function (DBAdapter $db) use ($inspector, $sql, $tableName, $fkName, &$fkSnapshot, &$dropped, $context): void {
			$fkSnapshot = $inspector->getForeignKey($tableName, $fkName);
			if($fkSnapshot === null) {
				$dropped = false;
				return;
			}
			$context->execSql($db, $sql->renderDropForeignKey($tableName, $fkName));
			$dropped = true;
		};
		$down = function (DBAdapter $db) use ($sql, $tableName, &$fkSnapshot, &$dropped, $context): void {
			if(!$dropped || $fkSnapshot === null) {
				return;
			}
			$context->execSql($db, $sql->renderAddForeignKey($tableName, $fkSnapshot));
		};
		return ['up' => $up, 'down' => $down];
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

	/**
	 * @param string[] $unsupported
	 */
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
