<?php
namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\Contracts\MigrationStep;
use Kir\DB\Migrations\DBAdapter;
use Kir\DB\Migrations\Schema\ColumnDefinition;
use Kir\DB\Migrations\Schema\Feature;
use Kir\DB\Migrations\Schema\IndexDefinition;
use Kir\DB\Migrations\Schema\ForeignKeyDefinition;
use Kir\DB\Migrations\Schema\DbalSqlRenderer;
use Kir\DB\Migrations\Schema\MigrationContext;

final class CreateTableBuilder implements MigrationStep {
	use ColumnBuilderTrait;

	/** @var ColumnDefinition[] */
	private array $columns = [];
	/** @var string[] */
	private array $primaryKey = [];
	/** @var IndexDefinition[] */
	private array $indexes = [];
	/** @var IndexDefinition[] */
	private array $uniqueIndexes = [];
	/** @var ForeignKeyDefinition[] */
	private array $foreignKeys = [];
	private ?string $engine = null;
	private ?string $charset = null;
	private ?string $collation = null;
	/** @var string[] */
	private array $pendingAutoIncrement = [];

	public function __construct(private string $tableName) {}

	protected function addColumnDefinition(ColumnDefinition $column): void {
		if(in_array($column->name, $this->pendingAutoIncrement, true)) {
			$column->autoIncrement = true;
			$this->pendingAutoIncrement = array_values(array_filter(
				$this->pendingAutoIncrement,
				fn(string $name) => $name !== $column->name
			));
		}
		$this->columns[] = $column;
	}

	public function primaryKey(string ...$columns): self {
		$this->primaryKey = $columns;
		return $this;
	}

	public function autoIncrement(string $columnName): self {
		foreach($this->columns as $column) {
			if($column->name === $columnName) {
				$column->autoIncrement = true;
				return $this;
			}
		}
		$this->pendingAutoIncrement[] = $columnName;
		return $this;
	}

	public function index(string $name, string ...$columns): self {
		$this->indexes[] = new IndexDefinition($name, $columns, false, false);
		return $this;
	}

	public function uniqueIndex(string $name, string ...$columns): self {
		$this->uniqueIndexes[] = new IndexDefinition($name, $columns, true, false);
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
		$this->foreignKeys[] = new ForeignKeyDefinition(
			name: $name,
			localColumns: $localColumns,
			foreignTable: $foreignTable,
			foreignColumns: $foreignColumns,
			onUpdate: $onUpdate,
			onDelete: $onDelete
		);
		return $this;
	}

	public function engine(string $engine): self {
		$this->engine = $engine;
		return $this;
	}

	public function charset(string $charset): self {
		$this->charset = $charset;
		return $this;
	}

	public function collation(string $collation): self {
		$this->collation = $collation;
		return $this;
	}

	public function statements(MigrationContext $context): array {
		$required = [Feature::CREATE_TABLE, Feature::DROP_TABLE];
		foreach($this->columns as $column) {
			$required = array_merge($required, $this->columnFeatures($column));
		}
		if($this->engine !== null) {
			$required[] = Feature::TABLE_ENGINE;
		}
		if($this->charset !== null) {
			$required[] = Feature::COLUMN_CHARSET;
		}
		if($this->collation !== null) {
			$required[] = Feature::COLUMN_COLLATION;
		}
		$unsupported = $context->features->unsupported($required);
		if($unsupported !== []) {
			return [$context->skipStatement($this->skipMessage($context, 'create table', $unsupported))];
		}
		if($context->engine->engine === 'sqlite' && $this->indexes !== [] && !($context->sql instanceof DbalSqlRenderer)) {
			return [$context->skipStatement(
				sprintf(
					'Skip UP create table on %s: non-unique indexes are not supported in CREATE TABLE for %s. Use Add::toTable()->index instead.',
					$this->tableName,
					$context->features->describeEngine()
				)
			)];
		}

		$sql = $context->sql->renderCreateTable(
			tableName: $this->tableName,
			columns: $this->columns,
			primaryKey: $this->primaryKey,
			indexes: $this->indexes,
			uniqueIndexes: $this->uniqueIndexes,
			foreignKeys: $this->foreignKeys,
			engine: $this->engine,
			charset: $this->charset,
			collation: $this->collation
		);
		$exists = null;
		$tableName = $this->tableName;
		$inspector = $context->inspector;
		$dropSql = $context->sql->renderDropTable($this->tableName);

		$up = function (DBAdapter $db) use ($inspector, $tableName, $sql, &$exists, $context): void {
			if($inspector->tableExists($tableName)) {
				$exists = true;
				return;
			}
			$exists = false;
			$context->execSql($db, $sql);
		};
		$down = function (DBAdapter $db) use (&$exists, $dropSql, $context): void {
			if($exists) {
				return;
			}
			$context->execSql($db, $dropSql);
		};

		return [['up' => $up, 'down' => $down]];
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
