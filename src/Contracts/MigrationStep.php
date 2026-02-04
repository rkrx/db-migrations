<?php
namespace Kir\DB\Migrations\Contracts;

use Closure;
use Kir\DB\Migrations\Schema\MigrationContext;

interface MigrationStep {
	/**
	 * @return array<array{up?: Closure|string|array, down?: Closure|string|array}>
	 */
	public function statements(MigrationContext $context): array;
}
