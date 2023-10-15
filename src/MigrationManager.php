<?php
namespace Kir\DB\Migrations;

use Closure;
use Exception;
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

		foreach($paths as $path) {
			$entry = $this->getEntry($path);
			if(!$this->db->hasEntry($entry)) {
				$this->logger->info(sprintf("Try to run upgrade file %s", basename($path)));
				$this->up($path);
				$this->db->addEntry($entry);
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
		$statements = $data['statements'];

		$rollback = [];
		foreach($statements as $statement) {
			try {
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
		$statements = array_reverse($data['statements']);

		$rollback = [];
		foreach($statements as $statement) {
			try {
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
	 * @param Closure|string|array<string, string>|null $statement
	 * @return void
	 * @throws Exception
	 */
	private function runQuery(Closure|string|array|null $statement): void {
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
	 * @param string $path
	 * @return string
	 */
	private function getEntry(string $path): string {
		return basename($path);
	}
}
