<?php

namespace Kir\DB\Migrations\Common;

use JetBrains\PhpStorm\Immutable;

#[Immutable]
class ColumnDef {
	public function __construct(
		public string $name,
		public string $dataType,
		public bool $nullable = false,
		public bool|int|float|string|Expr|null $defaultValue = null,
		public bool|int|float|string|Expr|null $onUpdateValue = null,
		public ?string $comment = null,
		public ?string $charset = null,
		public ?string $collation = null
	) {}
}
