<?php
namespace Kir\DB\Migrations\Schema;

use Kir\DB\Migrations\DBAdapter;

final class SchemaInspectorFactory {
	public static function create(DBAdapter $db, EngineInfo $engine): SchemaInspector {
		return match($engine->engine) {
			'mariadb', 'mysql' => new MySqlSchemaInspector($db),
			'sqlite' => new SqliteSchemaInspector($db),
			default => new UnknownSchemaInspector(),
		};
	}
}
