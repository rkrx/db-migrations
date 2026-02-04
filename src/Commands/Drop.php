<?php
namespace Kir\DB\Migrations\Commands;

final class Drop {
	public static function table(string $name): DropTableBuilder {
		return new DropTableBuilder($name);
	}

	public static function fromTable(string $name): AlterTableDropBuilder {
		return new AlterTableDropBuilder($name);
	}
}
