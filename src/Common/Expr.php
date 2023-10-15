<?php

namespace Kir\DB\Migrations\Common;

use JetBrains\PhpStorm\Immutable;

#[Immutable]
class Expr {
	public function __construct(public ?string $expr) {}

	public static function NOTHING(): Expr {
		return new self(null);
	}

	public static function CURRENT_TIMESTAMP(): Expr {
		return new self('CURRENT_TIMESTAMP()');
	}
}