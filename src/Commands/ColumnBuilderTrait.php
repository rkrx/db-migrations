<?php
namespace Kir\DB\Migrations\Commands;

use Kir\DB\Migrations\Common\Expr;
use Kir\DB\Migrations\Schema\ColumnDefinition;

trait ColumnBuilderTrait {
	abstract protected function addColumnDefinition(ColumnDefinition $column): void;

	public function column(
		string $name,
		string $type,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		?string $charset = null,
		?string $collation = null,
		?Expr $onUpdate = null,
		bool $autoIncrement = false,
		bool $isUnsigned = false,
		?string $after = null,
		bool $first = false
	): static {
		$column = new ColumnDefinition(
			name: $name,
			dataType: $type,
			nullable: $nullable,
			defaultValue: $default,
			onUpdateValue: $onUpdate,
			comment: $comment,
			charset: $charset,
			collation: $collation,
			unsigned: $isUnsigned,
			autoIncrement: $autoIncrement,
			after: $after,
			first: $first
		);
		$this->addColumnDefinition($column);
		return $this;
	}

	public function intColumn(
		string $name,
		?int $length = null,
		bool $isUnsigned = false,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		$type = $length === null ? 'INT' : "INT({$length})";
		return $this->column(
			name: $name,
			type: $type,
			nullable: $nullable,
			default: $default,
			comment: $comment,
			isUnsigned: $isUnsigned,
			after: $after,
			first: $first
		);
	}

	public function bigIntColumn(
		string $name,
		?int $length = null,
		bool $isUnsigned = false,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		$type = $length === null ? 'BIGINT' : "BIGINT({$length})";
		return $this->column(
			name: $name,
			type: $type,
			nullable: $nullable,
			default: $default,
			comment: $comment,
			isUnsigned: $isUnsigned,
			after: $after,
			first: $first
		);
	}

	public function smallIntColumn(
		string $name,
		?int $length = null,
		bool $isUnsigned = false,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		$type = $length === null ? 'SMALLINT' : "SMALLINT({$length})";
		return $this->column(
			name: $name,
			type: $type,
			nullable: $nullable,
			default: $default,
			comment: $comment,
			isUnsigned: $isUnsigned,
			after: $after,
			first: $first
		);
	}

	public function tinyIntColumn(
		string $name,
		?int $length = null,
		bool $isUnsigned = false,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		$type = $length === null ? 'TINYINT' : "TINYINT({$length})";
		return $this->column(
			name: $name,
			type: $type,
			nullable: $nullable,
			default: $default,
			comment: $comment,
			isUnsigned: $isUnsigned,
			after: $after,
			first: $first
		);
	}

	public function varcharColumn(
		string $name,
		int $length,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		?string $charset = null,
		?string $collation = null,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: "VARCHAR({$length})",
			nullable: $nullable,
			default: $default,
			comment: $comment,
			charset: $charset,
			collation: $collation,
			after: $after,
			first: $first
		);
	}

	public function charColumn(
		string $name,
		int $length,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		?string $charset = null,
		?string $collation = null,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: "CHAR({$length})",
			nullable: $nullable,
			default: $default,
			comment: $comment,
			charset: $charset,
			collation: $collation,
			after: $after,
			first: $first
		);
	}

	public function textColumn(
		string $name,
		bool $nullable = false,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: 'TEXT',
			nullable: $nullable,
			comment: $comment,
			after: $after,
			first: $first
		);
	}

	public function decimalColumn(
		string $name,
		int $precision,
		int $scale,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		bool $isUnsigned = false,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: "DECIMAL({$precision},{$scale})",
			nullable: $nullable,
			default: $default,
			comment: $comment,
			isUnsigned: $isUnsigned,
			after: $after,
			first: $first
		);
	}

	public function floatColumn(
		string $name,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		bool $isUnsigned = false,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: 'FLOAT',
			nullable: $nullable,
			default: $default,
			comment: $comment,
			isUnsigned: $isUnsigned,
			after: $after,
			first: $first
		);
	}

	public function doubleColumn(
		string $name,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		bool $isUnsigned = false,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: 'DOUBLE',
			nullable: $nullable,
			default: $default,
			comment: $comment,
			isUnsigned: $isUnsigned,
			after: $after,
			first: $first
		);
	}

	public function booleanColumn(
		string $name,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: 'TINYINT(1)',
			nullable: $nullable,
			default: $default,
			comment: $comment,
			after: $after,
			first: $first
		);
	}

	public function dateColumn(
		string $name,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: 'DATE',
			nullable: $nullable,
			default: $default,
			comment: $comment,
			after: $after,
			first: $first
		);
	}

	public function dateTimeColumn(
		string $name,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?Expr $onUpdate = null,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: 'DATETIME',
			nullable: $nullable,
			default: $default,
			onUpdate: $onUpdate,
			comment: $comment,
			after: $after,
			first: $first
		);
	}

	public function timestampColumn(
		string $name,
		bool $nullable = false,
		bool|int|float|string|Expr|null $default = null,
		?Expr $onUpdate = null,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: 'TIMESTAMP',
			nullable: $nullable,
			default: $default,
			onUpdate: $onUpdate,
			comment: $comment,
			after: $after,
			first: $first
		);
	}

	public function jsonColumn(
		string $name,
		bool $nullable = false,
		?string $comment = null,
		?string $after = null,
		bool $first = false
	): static {
		return $this->column(
			name: $name,
			type: 'JSON',
			nullable: $nullable,
			comment: $comment,
			after: $after,
			first: $first
		);
	}
}
