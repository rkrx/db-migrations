<?php
namespace Kir\DB\Migrations\Schema;

class IndexDefinition {
	/**
	 * @param string[] $columns
	 */
	public function __construct(
		public string $name,
		public array $columns,
		public bool $unique = false,
		public bool $primary = false,
		public ?string $type = null
	) {}
}
