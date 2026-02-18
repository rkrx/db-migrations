<?php
namespace Kir\DB\Migrations;

use Closure;
use Exception;
use Kir\DB\Migrations\Contracts\ContextProvider;
use Kir\DB\Migrations\Contracts\MigrationStep;
use Kir\DB\Migrations\Helpers\EntryName;
use Kir\DB\Migrations\Schema\EngineInfo;
use Kir\DB\Migrations\Schema\FeatureGate;
use Kir\DB\Migrations\Schema\FeatureMatrix;
use Kir\DB\Migrations\Schema\MigrationContext;
use Kir\DB\Migrations\Schema\SchemaInspectorFactory;
use Kir\DB\Migrations\Schema\SqlRenderer;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class MigrationManager {
	private string $migrationScriptPath;

	/**
	 * @param DBAdapter $db
	 * @param string $migrationScriptPath
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private DBAdapter $db,
		string $migrationScriptPath,
		private LoggerInterface $logger
	) {
		$migrationScriptPath = str_replace(DIRECTORY_SEPARATOR, '/', $migrationScriptPath);
		$this->migrationScriptPath = $migrationScriptPath;
	}

	/**
	 */
	public function migrate(): void {
		$this->logger->info("Starting migration");

		$paths = glob("{$this->migrationScriptPath}/*.php");
		if(!is_array($paths)) {
			throw new RuntimeException("Invalid migration script path: {$this->migrationScriptPath}");
		}

		$paths = array_filter($paths, static fn(string $path) => is_file($path));
		$paths = array_map(static fn($path) => realpath($path) ?: $path, $paths);
		sort($paths);
		$entries = $this->db->listEntries();
		$entries = array_fill_keys($entries, true);

		foreach($paths as $path) {
			$entry = $this->getEntry($path);
			if(!array_key_exists($entry, $entries)) {
				$this->logger->info(sprintf("Try to run upgrade file %s", basename($path)));
				$this->up($path);
				$this->db->addEntry($entry);
				$entries[$entry] = true;
				$this->logger->info("Done.");
			}
		}

		$this->logger->info("Migration done.");
	}

	/**
	 * @param string $path
	 * @throws Throwable
	 */
	public function up(string $path): void {
		$data = require $path;
		if(!is_array($data) || !array_key_exists('statements', $data) || !is_array($data['statements'])) {
			throw new RuntimeException("Invalid migration file: {$path}");
		}
		/** @var array<array-key, mixed> $statementData */
		$statementData = $data['statements'];
		$context = $this->buildContext();
		$statements = $this->normalizeStatements($statementData, $context);

		$rollback = [];
		foreach($statements as $statement) {
			try {
				if(!is_array($statement)) {
					$this->runQuery($statement);
					continue;
				}
				if(array_key_exists('down', $statement)) {
					$rollback[] = $statement['down'];
				}
				if(array_key_exists('up', $statement)) {
					$this->runQuery($statement['up']);
				}
			} catch (Exception $e) {
				printf("FAILURE: %s\n", $e->getMessage());
				$rollback = array_reverse($rollback);
				foreach($rollback as $rollbackStmt) {
					try {
						$this->runQuery($rollbackStmt);
					} catch(Throwable $e) {
					}
				}
				throw $e;
			}
		}
	}

	/**
	 * @param string $path
	 * @throws Throwable
	 */
	public function down(string $path): void {
		$data = require $path;
		if(!is_array($data) || !array_key_exists('statements', $data) || !is_array($data['statements'])) {
			throw new RuntimeException("Invalid migration file: {$path}");
		}
		/** @var array<array-key, mixed> $statementData */
		$statementData = $data['statements'];
		$context = $this->buildContext();
		$statements = array_reverse($this->normalizeStatements($statementData, $context));

		$rollback = [];
		foreach($statements as $statement) {
			try {
				if(!is_array($statement)) {
					$this->runQuery($statement);
					continue;
				}
				if(array_key_exists('down', $statement)) {
					$this->runQuery($statement['down']);
				}
			} catch (Exception $e) {
				$rollback = array_reverse($rollback);
				foreach($rollback as $rollbackStmt) {
					$this->runQuery($rollbackStmt);
				}
				throw $e;
			}
			if(array_key_exists('up', $statement)) {
				$rollback[] = $statement['up'];
			}
		}
	}

	/**
	 * @param mixed $statement
	 * @return void
	 * @throws Exception
	 */
	private function runQuery(mixed $statement): void {
		if(is_string($statement)) {
			$this->db->exec($statement);
			return;
		}
		if(is_array($statement)) {
			foreach($statement as $stmt) {
				$this->runQuery($stmt);
			}
			return;
		}
		if(is_callable($statement)) {
			$statement($this->db);
			return;
		}
		throw new RuntimeException("Invalid statement. Require a Closure to run.");
	}

	/**
	 * @param array<array-key, mixed> $statements
	 * @return array<int, mixed>
	 */
	private function normalizeStatements(array $statements, MigrationContext $context): array {
		$normalized = [];
		foreach($statements as $statement) {
			if($statement instanceof MigrationStep) {
				$expanded = $statement->statements($context);
				$normalized = array_merge($normalized, $this->normalizeStatements($expanded, $context));
				continue;
			}
			if(is_array($statement) && $this->isList($statement) && !$this->isStatementArray($statement)) {
				$normalized = array_merge($normalized, $this->normalizeStatements($statement, $context));
				continue;
			}
			$normalized[] = $statement;
		}
		return $normalized;
	}

	/**
	 * @param array<mixed> $statement
	 */
	private function isStatementArray(array $statement): bool {
		return array_key_exists('up', $statement) || array_key_exists('down', $statement);
	}

	/**
	 * @param array<array-key, mixed> $array
	 */
	private function isList(array $array): bool {
		$expected = 0;
		foreach($array as $key => $value) {
			if($key !== $expected) {
				return false;
			}
			$expected++;
		}
		return true;
	}

	private function buildContext(): MigrationContext {
		if($this->db instanceof ContextProvider) {
			return $this->db->buildContext($this->logger);
		}
		$engine = EngineInfo::detect($this->db);
		$features = new FeatureGate($engine, new FeatureMatrix());
		$inspector = SchemaInspectorFactory::create($this->db, $engine);
		$sql = new SqlRenderer($engine);
		return new MigrationContext(
			db: $this->db,
			logger: $this->logger,
			engine: $engine,
			features: $features,
			inspector: $inspector,
			sql: $sql
		);
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function getEntry(string $path): string {
		return EntryName::shorten(basename($path));
	}
}
