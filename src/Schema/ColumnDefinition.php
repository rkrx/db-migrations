<?php
namespace Kir\DB\Migrations\Schema;

use Kir\DB\Migrations\Common\Expr;

class ColumnDefinition {
	public function __construct(
		public string $name,
		public string $dataType,
		public bool $nullable = false,
		public bool|int|float|string|Expr|null $defaultValue = null,
		public ?Expr $onUpdateValue = null,
		public ?string $comment = null,
		public ?string $charset = null,
		public ?string $collation = null,
		public ?bool $unsigned = null,
		public bool $autoIncrement = false,
		public ?string $after = null,
		public bool $first = false
	) {}
}
