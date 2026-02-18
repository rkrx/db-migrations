<?php
namespace Kir\DB\Migrations\Helpers;

use Kir\DB\Migrations\Common\ColumnDef;
use Kir\DB\Migrations\Common\Expr;
use Kir\DB\Migrations\DBAdapters\Columns;
use Kir\DB\Migrations\Schema\SchemaInspector;

class TableObj {
	public function __construct(
		private SchemaInspector $inspector,
		private string $tableName
	) {}

	public function exists(): bool {
		return $this->inspector->tableExists($this->tableName);
	}

	/**
	 * @return Columns
	 */
	public function getColumns(): Columns {
		$columns = [];
		foreach($this->inspector->getColumns($this->tableName) as $column) {
			$default = $column->defaultValue;
			if($default instanceof Expr) {
				$default = new Expr($default->expr ?? 'NULL');
			}
			$columns[] = new ColumnDef(
				name: $column->name,
				dataType: $column->dataType,
				nullable: $column->nullable,
				defaultValue: $default,
				onUpdateValue: $column->onUpdateValue,
				comment: $column->comment,
				charset: $column->charset,
				collation: $column->collation
			);
		}
		return new Columns($columns);
	}

	public function hasColumn(string $name): bool {
		return $this->inspector->getColumn($this->tableName, $name) !== null;
	}

	public function hasIndex(string $name): bool {
		return $this->inspector->getIndex($this->tableName, $name) !== null;
	}
}
