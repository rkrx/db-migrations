<?php
namespace Kir\DB\Migrations;

use Kir\DB\Migrations\DBAdapters\PdoDBAdapter;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MigrationManagerTest extends TestCase {
	public function testHasEntry() {
		$pdo = new PDO('sqlite::memory:');
		$adapter = new PdoDBAdapter($pdo, 'test_migrations');
		$mm = new MigrationManager($adapter, __DIR__.'/test-migrations', new NullLogger);
		$mm->migrate();
		$this->assertEquals(true, $adapter->hasEntry('2015-01-01-12-00-00'));
	}
	
	public function testHasEntryWithFix() {
		$pdo = new PDO('sqlite::memory:');
		
		// Create migration table and some entries
		$pdo->exec("
			CREATE TABLE test_migrations (
				`entry` VARCHAR(255) NOT NULL DEFAULT '',
				PRIMARY KEY (`entry`)
			);
		");
		$pdo->exec("INSERT INTO test_migrations (entry) VALUES ('2015-01-01-10-00-00.php')");
		$pdo->exec("INSERT INTO test_migrations (entry) VALUES ('2015-01-01-11-00-00.php')");
		$pdo->exec("INSERT INTO test_migrations (entry) VALUES ('2015-01-01-11-00-00 with a comment.php')");
		
		$adapter = new PdoDBAdapter($pdo, 'test_migrations');
		
		$adapter->addEntry('2015-01-01-09-00-00.php');
		$adapter->addEntry('2015-01-01-13-00-00.php');
		
		$mm = new MigrationManager($adapter, __DIR__.'/test-migrations', new NullLogger);
		$mm->migrate();
		
		self::assertTrue($adapter->hasEntry('2015-01-01-09-00-00'));
		self::assertTrue($adapter->hasEntry('2015-01-01-10-00-00'));
		self::assertTrue($adapter->hasEntry('2015-01-01-11-00-00'));
		self::assertTrue($adapter->hasEntry('2015-01-01-12-00-00'));
		
		$this->assertEquals(0, $adapter->query('SELECT COUNT(*) FROM test')->getValue());
	}
}
