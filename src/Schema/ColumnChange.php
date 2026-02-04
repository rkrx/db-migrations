<?php
namespace Kir\DB\Migrations\Schema;

use Kir\DB\Migrations\Common\Expr;

final class ColumnChange {
	public const UNSET = '__unset__';

	public function __construct(
		public string $name,
		public ?string $dataType = null,
		public ?bool $nullable = null,
		public ?bool $unsigned = null,
		public ?bool $autoIncrement = null,
		public ?string $comment = null,
		public bool $commentSet = false,
		public ?string $charset = null,
		public bool $charsetSet = false,
		public ?string $collation = null,
		public bool $collationSet = false,
		public bool|int|float|string|Expr|null $defaultValue = null,
		public bool $defaultSet = false,
		public ?Expr $onUpdate = null,
		public bool $onUpdateSet = false,
		public ?string $after = null,
		public bool $first = false
	) {}
}
