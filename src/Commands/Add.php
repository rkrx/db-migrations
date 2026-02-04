<?php
namespace Kir\DB\Migrations\Commands;

final class Add {
	public static function table(string $name): CreateTableBuilder {
		return new CreateTableBuilder($name);
	}

	public static function toTable(string $name): AlterTableAddBuilder {
		return new AlterTableAddBuilder($name);
	}
}
