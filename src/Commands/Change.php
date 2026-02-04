<?php
namespace Kir\DB\Migrations\Commands;

final class Change {
	public static function table(string $name): AlterTableChangeBuilder {
		return new AlterTableChangeBuilder($name);
	}
}
