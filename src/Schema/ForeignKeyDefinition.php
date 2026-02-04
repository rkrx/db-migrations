<?php
namespace Kir\DB\Migrations\Schema;

class ForeignKeyDefinition {
	/**
	 * @param string[] $localColumns
	 * @param string[] $foreignColumns
	 */
	public function __construct(
		public string $name,
		public array $localColumns,
		public string $foreignTable,
		public array $foreignColumns,
		public ?string $onUpdate = null,
		public ?string $onDelete = null
	) {}
}
