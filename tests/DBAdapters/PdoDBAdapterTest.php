<?php
namespace Kir\DB\Migrations\DBAdapters;

use PDO;

class PdoDBAdapterTest extends \PHPUnit_Framework_TestCase {
	public function testExecResult() {
		$pdo = new PDO('sqlite::memory:');
		$adapter = new PdoDBAdapter($pdo, 'test');
		$adapter->exec("
			CREATE TABLE IF NOT EXISTS messages (
				id INTEGER PRIMARY KEY,
				message TEXT
			)
		");
		foreach(range(1, 5) as $idx) {
			$response = $adapter->exec(sprintf('INSERT INTO messages (message) VALUES (%d)', $idx));
			$this->assertEquals($idx, $response->getLastInsertId());
		}
	}

	public function testQuery() {
		$pdo = new PDO('sqlite::memory:');
		$adapter = new PdoDBAdapter($pdo, 'test');
		$adapter->exec("
			CREATE TABLE IF NOT EXISTS messages (
				id INTEGER PRIMARY KEY,
				message TEXT
			)
		");
		foreach(range(1, 5) as $idx) {
			$adapter->exec(sprintf('INSERT INTO messages (message) VALUES (%d)', $idx));
		}
		$result = $adapter->query("SELECT COUNT(*) FROM messages")->getValue();
		$this->assertEquals(5, $result);
	}
}