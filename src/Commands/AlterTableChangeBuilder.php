<?php
namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\Common\Expr;
use Kir\DB\Migrations\Contracts\MigrationStep;
use Kir\DB\Migrations\DBAdapter;
use Kir\DB\Migrations\Schema\ColumnChange;
use Kir\DB\Migrations\Schema\ColumnDefinition;
use Kir\DB\Migrations\Schema\Feature;
use Kir\DB\Migrations\Schema\MigrationContext;

final class AlterTableChangeBuilder implements MigrationStep {
	/** @var ColumnChange[] */
	private array $changes = [];

	public function __construct(private string $tableName) {}

	public function column(
		string $name,
		?string $type = null,
		?bool $nullable = null,
		mixed $default = ColumnChange::UNSET,
		mixed $comment = ColumnChange::UNSET,
		?bool $unsigned = null,
		?bool $autoIncrement = null,
		mixed $onUpdate = ColumnChange::UNSET,
		mixed $charset = ColumnChange::UNSET,
		mixed $collation = ColumnChange::UNSET,
		?string $after = null,
		bool $first = false
	): self {
		$change = new ColumnChange(
			name: $name,
			dataType: $type,
			nullable: $nullable,
			unsigned: $unsigned,
			autoIncrement: $autoIncrement,
			comment: $comment === ColumnChange::UNSET ? null : (is_string($comment) ? $comment : null),
			commentSet: $comment !== ColumnChange::UNSET,
			charset: $charset === ColumnChange::UNSET ? null : (is_string($charset) ? $charset : null),
			charsetSet: $charset !== ColumnChange::UNSET,
			collation: $collation === ColumnChange::UNSET ? null : (is_string($collation) ? $collation : null),
			collationSet: $collation !== ColumnChange::UNSET,
			defaultValue: $default === ColumnChange::UNSET ? null : $this->normalizeDefaultValue($default),
			defaultSet: $default !== ColumnChange::UNSET,
			onUpdate: $this->normalizeOnUpdate($onUpdate),
			onUpdateSet: $onUpdate !== ColumnChange::UNSET,
			after: $after,
			first: $first
		);
		$this->changes[] = $change;
		return $this;
	}

	/**
	 * @return array<int, array{up: \Closure, down: \Closure}>
	 */
	public function statements(MigrationContext $context): array {
		$statements = [];
		foreach($this->changes as $change) {
			$statements[] = $this->buildChangeColumn($context, $change);
		}
		return $statements;
	}

	/**
	 * @return array{up: \Closure, down: \Closure}
	 */
	private function buildChangeColumn(MigrationContext $context, ColumnChange $change): array {
		$required = [Feature::ALTER_MODIFY_COLUMN];
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return $context->skipStatement($this->skipMessage($context, "change column {$change->name}", $unsupported));
		}
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$sql = $context->sql;
		$oldColumn = null;
		$newColumn = null;
		$changed = false;
		$up = function (DBAdapter $db) use ($inspector, $sql, $tableName, $change, &$oldColumn, &$newColumn, &$changed, $context): void {
			$oldColumn = $inspector->getColumn($tableName, $change->name);
			if($oldColumn === null) {
				$changed = false;
				return;
			}
			$newColumn = $this->mergeColumn($oldColumn, $change);
			$features = array_merge(
				$this->columnFeatures($oldColumn),
				$this->columnFeatures($newColumn)
			);
			$extraUnsupported = $context->features->unsupported($features);
			if($extraUnsupported !== []) {
				$context->logger->warning($this->skipMessage($context, "change column {$change->name}", $extraUnsupported));
				$changed = false;
				return;
			}
			$context->execSql($db, $sql->renderModifyColumn($tableName, $newColumn));
			$changed = true;
		};
		$down = function (DBAdapter $db) use ($sql, $tableName, &$oldColumn, &$changed, $context): void {
			if(!$changed || $oldColumn === null) {
				return;
			}
			$context->execSql($db, $sql->renderModifyColumn($tableName, $oldColumn));
		};
		return ['up' => $up, 'down' => $down];
	}

	private function mergeColumn(ColumnDefinition $current, ColumnChange $change): ColumnDefinition {
		$dataType = $change->dataType ?? $current->dataType;
		$unsigned = $change->unsigned ?? $current->unsigned;
		if($unsigned === false) {
			$dataType = preg_replace('/\s+unsigned\b/i', '', $dataType) ?? $dataType;
		}
		$nullable = $change->nullable ?? $current->nullable;
		$defaultValue = $change->defaultSet ? $change->defaultValue : $current->defaultValue;
		$comment = $change->commentSet ? $change->comment : $current->comment;
		$charset = $change->charsetSet ? $change->charset : $current->charset;
		$collation = $change->collationSet ? $change->collation : $current->collation;
		$onUpdate = $change->onUpdateSet ? $change->onUpdate : $current->onUpdateValue;
		$autoIncrement = $change->autoIncrement ?? $current->autoIncrement;
		$after = $change->after ?? $current->after;
		$first = $change->first ? true : $current->first;
		if($change->after !== null) {
			$first = false;
		}
		if($change->first) {
			$after = null;
		}

		return new ColumnDefinition(
			name: $current->name,
			dataType: $dataType,
			nullable: $nullable,
			defaultValue: $defaultValue,
			onUpdateValue: $onUpdate,
			comment: $comment,
			charset: $charset,
			collation: $collation,
			unsigned: $unsigned,
			autoIncrement: $autoIncrement,
			after: $after,
			first: $first
		);
	}

	private function normalizeOnUpdate(mixed $value): ?Expr {
		if($value === ColumnChange::UNSET) {
			return null;
		}
		if($value === null) {
			return null;
		}
		if($value instanceof Expr) {
			return $value;
		}
		if(is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
			return new Expr((string) $value);
		}
		if(is_object($value) && method_exists($value, '__toString')) {
			return new Expr((string) $value);
		}
		return null;
	}

	private function normalizeDefaultValue(mixed $value): bool|int|float|string|Expr|null {
		if($value === null) {
			return null;
		}
		if($value instanceof Expr) {
			return $value;
		}
		if(is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
			return $value;
		}
		if(is_object($value) && method_exists($value, '__toString')) {
			return (string) $value;
		}
		return null;
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
