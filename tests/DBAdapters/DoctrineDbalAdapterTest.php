<?php
namespace Kir\DB\Migrations\DBAdapters;

use Doctrine\DBAL\DriverManager;
use Kir\DB\Migrations\Commands\Add;
use Kir\DB\Migrations\Commands\Drop;
use Kir\DB\Migrations\DBAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DoctrineDbalAdapterTest extends TestCase {
	protected function setUp(): void {
		if(!class_exists(DriverManager::class)) {
			$this->markTestSkipped('Doctrine DBAL is not installed.');
		}
	}

	public function testExecResult(): void {
		$adapter = $this->createAdapter();
		$adapter->exec("\n\t\t\tCREATE TABLE IF NOT EXISTS messages (\n\t\t\t\tid INTEGER PRIMARY KEY AUTOINCREMENT,\n\t\t\t\tmessage TEXT\n\t\t\t)\n\t\t");
		foreach(range(1, 5) as $idx) {
			$response = $adapter->exec('INSERT INTO messages (message) VALUES (:message)', ['message' => (string) $idx]);
			$this->assertNotNull($response->getLastInsertId());
		}
	}

	public function testQuery(): void {
		$adapter = $this->createAdapter();
		$adapter->exec("\n\t\t\tCREATE TABLE IF NOT EXISTS messages (\n\t\t\t\tid INTEGER PRIMARY KEY AUTOINCREMENT,\n\t\t\t\tmessage TEXT\n\t\t\t)\n\t\t");
		foreach(range(1, 5) as $idx) {
			$adapter->exec('INSERT INTO messages (message) VALUES (:message)', ['message' => (string) $idx]);
		}
		$result = $adapter->query('SELECT COUNT(*) FROM messages')->getValue();
		$this->assertEquals(5, $result);
	}

	public function testMigrationEntries(): void {
		$adapter = $this->createAdapter('test_migrations');
		$this->assertSame([], $adapter->listEntries());
		$adapter->addEntry('2015-01-01-12-00-00 test');
		$adapter->addEntry('2015-01-01-12-00-00 test');
		$entries = $adapter->listEntries();
		$this->assertCount(1, $entries);
		$this->assertSame('2015-01-01-12-00-00', $entries[0]);
	}

	public function testFluentCreateTable(): void {
		$adapter = $this->createAdapter();
		$context = $adapter->buildContext(new NullLogger());
		$statements = Add::table('dbal_table')
			->intColumn(name: 'id', nullable: false)
			->primaryKey('id')
			->statements($context);

		$this->runStatements($statements, $adapter, 'up');
		$this->assertTrue($context->inspector->tableExists('dbal_table'));
	}

	/**
	 * @dataProvider externalDsnProvider
	 */
	public function testFluentCreateTableExternal(string $dsn, string $label): void {
		if($dsn === '') {
			$this->markTestSkipped('External DSN not configured.');
		}
		$adapter = $this->createAdapterFromDsn($dsn, "migrations_{$label}");
		$context = $adapter->buildContext(new NullLogger());
		$tableName = 'dbal_it_' . preg_replace('/[^a-z0-9_]+/i', '_', $label) . '_' . substr(md5($dsn . microtime()), 0, 6);

		$statements = Add::table($tableName)
			->intColumn(name: 'id', nullable: false)
			->varcharColumn(name: 'message', length: 255, nullable: false)
			->primaryKey('id')
			->autoIncrement('id')
			->statements($context);

		$this->runStatements($statements, $adapter, 'up');
		$this->assertTrue($context->inspector->tableExists($tableName));

		$adapter->exec("INSERT INTO {$tableName} (message) VALUES (:message)", ['message' => 'hello']);
		$count = $adapter->query("SELECT COUNT(*) FROM {$tableName}")->getValue();
		$this->assertEquals(1, $count);

		$drop = Drop::table($tableName)->statements($context);
		$this->runStatements($drop, $adapter, 'up');
	}

	private function createAdapter(string $tableName = 'migrations'): DoctrineDbalAdapter {
		$connection = DriverManager::getConnection([
			'url' => 'sqlite:///:memory:'
		]);
		return new DoctrineDbalAdapter($connection, $tableName);
	}

	private function createAdapterFromDsn(string $dsn, string $tableName = 'migrations'): DoctrineDbalAdapter {
		$connection = DriverManager::getConnection([
			'url' => $dsn
		]);
		return new DoctrineDbalAdapter($connection, $tableName);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function externalDsnProvider(): array {
		$map = [
			'mysql' => getenv('TEST_DB_MYSQL_DSN') ?: '',
			'mariadb' => getenv('TEST_DB_MARIADB_DSN') ?: '',
			'postgres' => getenv('TEST_DB_PG_DSN') ?: '',
		];
		$data = [];
		foreach($map as $label => $dsn) {
			if($dsn !== '') {
				$data[$label] = [$dsn, $label];
			}
		}
		if($data === []) {
			return ['none' => ['', 'none']];
		}
		return $data;
	}

	/**
	 * @param array<array-key, mixed> $statements
	 * @param 'up'|'down' $direction
	 */
	private function runStatements(array $statements, DBAdapter $adapter, string $direction): void {
		foreach($statements as $statement) {
			if(is_array($statement) && (array_key_exists('up', $statement) || array_key_exists('down', $statement))) {
				$this->runStatement($statement[$direction] ?? null, $adapter);
				continue;
			}
			$this->runStatement($statement, $adapter);
		}
	}

	private function runStatement(mixed $statement, DBAdapter $adapter): void {
		if($statement === null) {
			return;
		}
		if(is_string($statement)) {
			$adapter->exec($statement);
			return;
		}
		if(is_array($statement)) {
			foreach($statement as $stmt) {
				$this->runStatement($stmt, $adapter);
			}
			return;
		}
		if(is_callable($statement)) {
			$statement($adapter);
			return;
		}
		throw new \RuntimeException('Invalid statement type');
	}
}
