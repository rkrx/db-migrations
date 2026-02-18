<?php
namespace Kir\DB\Migrations;

use Kir\DB\Migrations\Commands\Add;
use Kir\DB\Migrations\Commands\Drop;
use Kir\DB\Migrations\DBAdapters\PdoDBAdapter;
use Kir\DB\Migrations\Schema\EngineInfo;
use Kir\DB\Migrations\Schema\Feature;
use Kir\DB\Migrations\Schema\FeatureGate;
use Kir\DB\Migrations\Schema\FeatureMatrix;
use Kir\DB\Migrations\Schema\MigrationContext;
use Kir\DB\Migrations\Schema\SchemaInspectorFactory;
use Kir\DB\Migrations\Schema\SqlRenderer;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class FluentMigrationTest extends TestCase {
	public function testAddDropColumnRoundTrip(): void {
		$logger = new TestLogger();
		$adapter = $this->createAdapter($logger);
		$context = $this->createContext($adapter, $logger);

		if(!$context->features->supports(Feature::ALTER_DROP_COLUMN)) {
			$this->markTestSkipped('SQLite version does not support DROP COLUMN.');
		}

		$createStatements = Add::table('test_table')
			->intColumn(name: 'a', nullable: false)
			->statements($context);
		$this->runStatements($createStatements, $adapter, 'up');
		self::assertNotNull($context->inspector->getColumn('test_table', 'a'));

		$addStatements = Add::toTable('test_table')
			->intColumn(name: 'b', nullable: true)
			->statements($context);
		$this->runStatements($addStatements, $adapter, 'up');
		self::assertNotNull($context->inspector->getColumn('test_table', 'b'));

		$dropStatements = Drop::fromTable('test_table')
			->column('b')
			->statements($context);
		$this->runStatements($dropStatements, $adapter, 'up');
		self::assertNull($context->inspector->getColumn('test_table', 'b'));

		$this->runStatements($dropStatements, $adapter, 'down');
		self::assertNotNull($context->inspector->getColumn('test_table', 'b'));
	}

	public function testUnsupportedFeatureSkipsUp(): void {
		$logger = new TestLogger();
		$adapter = $this->createAdapter($logger);
		$context = $this->createContext($adapter, $logger);

		$statements = Add::table('skip_table')
			->intColumn(name: 'a', nullable: false, comment: 'Not supported in SQLite')
			->statements($context);
		$this->runStatements($statements, $adapter, 'up');

		self::assertFalse($context->inspector->tableExists('skip_table'));
		self::assertTrue($logger->hasWarningContaining('Skip UP'));
	}

	public function testDropTableDownRestoresSchema(): void {
		$logger = new TestLogger();
		$adapter = $this->createAdapter($logger);
		$context = $this->createContext($adapter, $logger);

		$createStatements = Add::table('restore_table')
			->intColumn(name: 'a', nullable: false)
			->statements($context);
		$this->runStatements($createStatements, $adapter, 'up');
		self::assertTrue($context->inspector->tableExists('restore_table'));

		$dropStatements = Drop::table('restore_table')->statements($context);
		$this->runStatements($dropStatements, $adapter, 'up');
		self::assertFalse($context->inspector->tableExists('restore_table'));

		$this->runStatements($dropStatements, $adapter, 'down');
		self::assertTrue($context->inspector->tableExists('restore_table'));
	}

	private function createAdapter(TestLogger $logger): PdoDBAdapter {
		$pdo = new PDO('sqlite::memory:');
		return new PdoDBAdapter($pdo, 'test_migrations', $logger);
	}

	private function createContext(DBAdapter $adapter, TestLogger $logger): MigrationContext {
		$engine = EngineInfo::detect($adapter);
		$features = new FeatureGate($engine, new FeatureMatrix());
		$inspector = SchemaInspectorFactory::create($adapter, $engine);
		$sql = new SqlRenderer($engine);
		return new MigrationContext(
			db: $adapter,
			logger: $logger,
			engine: $engine,
			features: $features,
			inspector: $inspector,
			sql: $sql
		);
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

final class TestLogger extends AbstractLogger {
	/** @var array<int, array{level: string, message: string}> */
	public array $records = [];

	/**
	 * @param string $level
	 * @param string|\Stringable $message
	 */
	public function log($level, $message, array $context = []): void {
		$this->records[] = ['level' => (string) $level, 'message' => (string) $message];
	}

	public function hasWarningContaining(string $needle): bool {
		foreach($this->records as $record) {
			if($record['level'] === 'warning' && str_contains($record['message'], $needle)) {
				return true;
			}
		}
		return false;
	}
}
