<?php
namespace Kir\DB\Migrations;

use Kir\DB\Migrations\DBAdapters\PdoDBAdapter;
use PDO;
use Psr\Log\NullLogger;

class MigrationManagerTest extends \PHPUnit_Framework_TestCase {
	public function test() {
		$pdo = new PDO('sqlite::memory:');
		$adapter = new PdoDBAdapter($pdo, 'test');
		$mm = new MigrationManager($adapter, __DIR__.'/test-migrations', new NullLogger);
		$mm->migrate();
		$this->assertEquals(true, $adapter->hasEntry('2015-01-01-12-00-00.php'));
	}
}
