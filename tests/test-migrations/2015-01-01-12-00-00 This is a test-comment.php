<?php
use Kir\DB\Migrations\DBAdapter;

return [
	'statements' => [[
		'up' => function (DBAdapter $db) {
			$db->exec("
				CREATE TABLE IF NOT EXISTS test (
					`id` INTEGER PRIMARY KEY AUTOINCREMENT,
					PRIMARY KEY (`id`)
				);
			");
		},
		'down' => function (DBAdapter $db) {
			#$db->exec("");
		}
	]]
];