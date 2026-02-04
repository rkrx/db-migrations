<?php

use Kir\DB\Migrations\DBAdapter;

return [
	'statements' => [[
			'up' => function (DBAdapter $db) {
				$db->exec("
					CREATE TABLE IF NOT EXISTS test (
						column1 INT PRIMARY KEY,
						column2 INT NOT NULL,
						column3 INT DEFAULT 0
					)
				");
			},
			'down' => function (DBAdapter $db) {
				$db->exec("DROP TABLE IF EXISTS test");
			}
		],
	]
];
