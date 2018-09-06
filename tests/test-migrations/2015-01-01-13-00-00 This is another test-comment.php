<?php
use Kir\DB\Migrations\DBAdapter;

return [
	'statements' => [[
		'up' => function (DBAdapter $db) {
			$db->exec("INSERT INTO test (column1, column2, column3) VALUES (1, 2, 3)");
		},
		'down' => function (DBAdapter $db) {
			#$db->exec("");
		}
	]]
];
