<?php
namespace Kir\DB\Migrations\DBAdapters;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Kir\DB\Migrations\Contracts\ContextProvider;
use Kir\DB\Migrations\DBAdapter;
use Kir\DB\Migrations\ExecResult;
use Kir\DB\Migrations\Helpers\EntryName;
use Kir\DB\Migrations\Helpers\TableObj;
use Kir\DB\Migrations\Helpers\Whitespace;
use Kir\DB\Migrations\QueryResult;
use Kir\DB\Migrations\Schema\DbalSchemaInspector;
use Kir\DB\Migrations\Schema\DbalSqlRenderer;
use Kir\DB\Migrations\Schema\EngineInfo;
use Kir\DB\Migrations\Schema\FeatureGate;
use Kir\DB\Migrations\Schema\FeatureMatrix;
use Kir\DB\Migrations\Schema\MigrationContext;
use Kir\DB\Migrations\Schema\Version;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class DoctrineDbalAdapter implements DBAdapter, ContextProvider {
	/** @var array<string, string> */
	private array $statements = [];
	private Whitespace $whitespace;
	private LoggerInterface $logger;
	/** @var AbstractSchemaManager<AbstractPlatform> */
	private AbstractSchemaManager $schemaManager;

	public function __construct(
		private Connection $connection,
		private string $tableName = 'migrations',
		?LoggerInterface $logger = null
	) {
		$this->schemaManager = $this->connection->createSchemaManager();
		$this->whitespace = new Whitespace();
		$this->logger = $logger ?? new NullLogger();
		$this->createMigrationsStore();
		$this->prepareStatements();
		$this->fixEntries();
	}

	public function addEntry(string $entry): self {
		$entry = EntryName::shorten($entry);
		$this->exec($this->statements['add'], ['entry' => $entry]);
		return $this;
	}

	public function listEntries(): array {
		$rows = $this->query($this->statements['list'])->getRows();
		$entries = [];
		foreach($rows as $row) {
			$entry = $this->extractEntry($row);
			if($entry !== null) {
				$entries[] = $entry;
			}
		}
		return $entries;
	}

	public function getDatabase(): ?string {
		$platform = $this->connection->getDatabasePlatform()->getName();
		try {
			$result = match($platform) {
				'postgresql' => $this->query('SELECT current_database()')->getValue(),
				'mysql', 'mariadb' => $this->query('SELECT DATABASE()')->getValue(),
				default => null,
			};
			return is_string($result) ? $result : null;
		} catch(Throwable $e) {
			return null;
		}
	}

	public function table(string $tableName): TableObj {
		$inspector = new DbalSchemaInspector($this->connection, $this->schemaManager);
		return new TableObj($inspector, $tableName);
	}

	public function query(string $query, array $args = []): QueryResult {
		$query = $this->whitespace->stripMargin($query);
		$this->logger->info("\n{$query}");
		$params = $this->normalizeParams($args);
		$rows = $this->connection->executeQuery($query, $params)->fetchAllAssociative();
		return new QueryResult($this->normalizeRows($rows));
	}

	public function exec(string $query, array $args = []): ExecResult {
		$query = $this->whitespace->stripMargin($query);
		$this->logger->info("\n{$query}");
		$params = $this->normalizeParams($args);
		$rows = $this->connection->executeStatement($query, $params);
		$lastInsertId = null;
		try {
			$lastInsertId = $this->connection->lastInsertId();
		} catch(Throwable $e) {
			$lastInsertId = null;
		}
		if($lastInsertId !== null && $lastInsertId !== false) {
			$lastInsertId = (string) $lastInsertId;
		} else {
			$lastInsertId = null;
		}
		return new ExecResult($lastInsertId, (int) $rows);
	}

	public function buildContext(LoggerInterface $logger): MigrationContext {
		$engine = $this->detectEngine();
		$features = new FeatureGate($engine, new FeatureMatrix());
		$inspector = new DbalSchemaInspector($this->connection, $this->schemaManager);
		$sql = new DbalSqlRenderer($this->schemaManager, $this->connection->getDatabasePlatform());
		return new MigrationContext(
			db: $this,
			logger: $logger,
			engine: $engine,
			features: $features,
			inspector: $inspector,
			sql: $sql
		);
	}

	private function detectEngine(): EngineInfo {
		$platform = $this->connection->getDatabasePlatform()->getName();
		$engine = match($platform) {
			'postgresql' => 'postgres',
			'mariadb' => 'mariadb',
			'mysql' => 'mysql',
			'sqlite' => 'sqlite',
			'mssql', 'sqlserver' => 'sqlserver',
			'oracle', 'oci8' => 'oracle',
			default => $platform,
		};
		$rawVersion = null;
		$version = null;

		try {
			$rawVersion = $this->connection->executeQuery('SELECT VERSION()')->fetchOne();
			if(is_string($rawVersion)) {
				$version = Version::fromString($rawVersion);
				if(str_contains(strtolower($rawVersion), 'mariadb')) {
					$engine = 'mariadb';
				}
			}
		} catch(Throwable $e) {
		}

		if($engine === 'sqlite') {
			$sqliteVersion = $this->query('SELECT sqlite_version()')->getValue();
			if(is_string($sqliteVersion)) {
				$rawVersion = $sqliteVersion;
				$version = Version::fromString($sqliteVersion);
			}
		}

		if(!is_string($rawVersion)) {
			$rawVersion = null;
		}

		return new EngineInfo($engine, $version, $rawVersion);
	}

	private function prepareStatements(): void {
		$table = $this->tableName;
		$lengthFn = $this->lengthFunction();
		$this->statements['fix'] = "SELECT entry FROM {$table} WHERE {$lengthFn}(entry)!=19;";
		$this->statements['fix_delete'] = "DELETE FROM {$table} WHERE entry=:entry";
		$this->statements['fix_update'] = "UPDATE {$table} SET entry=:new_entry WHERE entry=:old_entry";
		$this->statements['list'] = "SELECT entry FROM {$table};";
		$this->statements['add'] = $this->buildInsertIgnore();
	}

	private function buildInsertIgnore(): string {
		$table = $this->tableName;
		$platform = $this->connection->getDatabasePlatform()->getName();
		return match($platform) {
			'mysql', 'mariadb' => "INSERT IGNORE INTO {$table} (entry) VALUES (:entry);",
			'sqlite' => "INSERT OR IGNORE INTO {$table} (entry) VALUES (:entry);",
			'postgresql' => "INSERT INTO {$table} (entry) VALUES (:entry) ON CONFLICT (entry) DO NOTHING;",
			'mssql', 'sqlserver' => "IF NOT EXISTS (SELECT 1 FROM {$table} WHERE entry = :entry) INSERT INTO {$table} (entry) VALUES (:entry);",
			'oracle', 'oci8' => "MERGE INTO {$table} t USING (SELECT :entry AS entry FROM dual) s ON (t.entry = s.entry) WHEN NOT MATCHED THEN INSERT (entry) VALUES (s.entry)",
			default => "INSERT INTO {$table} (entry) VALUES (:entry);",
		};
	}

	private function lengthFunction(): string {
		$platform = $this->connection->getDatabasePlatform()->getName();
		return match($platform) {
			'mssql', 'sqlserver' => 'LEN',
			default => 'LENGTH',
		};
	}

	private function createMigrationsStore(): void {
		if($this->schemaManager->tablesExist([$this->tableName])) {
			return;
		}
		$table = new Table($this->tableName);
		$options = ['length' => 255, 'notnull' => true];
		if($this->isMySqlPlatform()) {
			$options['platformOptions'] = ['collation' => 'latin1_bin'];
		}
		$table->addColumn('entry', 'string', $options);
		$table->setPrimaryKey(['entry']);
		if($this->isMySqlPlatform()) {
			$table->addOption('engine', 'InnoDB');
			$table->addOption('collation', 'latin1_bin');
			$table->addOption('charset', 'latin1');
		}
		$sqls = $this->connection->getDatabasePlatform()->getCreateTableSQL($table);
		foreach($sqls as $sql) {
			$this->connection->executeStatement($sql);
		}
	}

	private function fixEntries(): void {
		$entries = $this->query($this->statements['fix'])->getRows();
		foreach($entries as $entry) {
			$oldEntry = $this->extractEntry($entry);
			if($oldEntry === null) {
				continue;
			}
			$newEntry = EntryName::shorten($oldEntry);
			$this->exec($this->statements['fix_delete'], ['entry' => $newEntry]);
			$this->exec($this->statements['fix_update'], ['old_entry' => $oldEntry, 'new_entry' => $newEntry]);
		}
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function extractEntry(array $row): ?string {
		foreach($row as $key => $value) {
			if(strtolower($key) === 'entry' && is_string($value)) {
				return $value;
			}
		}
		return null;
	}

	/**
	 * @param array<string, null|scalar> $args
	 * @return array<string, null|scalar>
	 */
	private function normalizeParams(array $args): array {
		$normalized = [];
		foreach($args as $key => $value) {
			$normalized[ltrim((string) $key, ':')] = $value;
		}
		return $normalized;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<int, array<string, bool|int|float|string|null>>
	 */
	private function normalizeRows(array $rows): array {
		$normalized = [];
		foreach($rows as $row) {
			$clean = [];
			foreach($row as $key => $value) {
				if(is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
					$clean[$key] = $value;
					continue;
				}
				if(is_object($value) && method_exists($value, '__toString')) {
					$clean[$key] = (string) $value;
					continue;
				}
				$clean[$key] = null;
			}
			$normalized[] = $clean;
		}
		return $normalized;
	}

	private function isMySqlPlatform(): bool {
		return $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
	}
}
