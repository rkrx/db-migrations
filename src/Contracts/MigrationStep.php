<?php
namespace Kir\DB\Migrations\Contracts;

use Closure;
use Kir\DB\Migrations\Schema\MigrationContext;

interface MigrationStep {
	/**
	 * @return array<array{up?: Closure|string|array<int, string>, down?: Closure|string|array<int, string>}>
	 */
	public function statements(MigrationContext $context): array;
}
