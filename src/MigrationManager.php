<?php
namespace Kir\DB\Migrations;

use Closure;
use Exception;
use Psr\Log\LoggerInterface;

class MigrationManager {
	/** @var DBAdapter */
	private $db;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param DBAdapter $db
	 * @param string $migrationScriptPath
	 * @param LoggerInterface $logger
	 */
	public function __construct(DBAdapter $db, $migrationScriptPath, LoggerInterface $logger) {
		$this->db = $db;
		$this->logger = $logger;
		$migrationScriptPath = str_replace(DIRECTORY_SEPARATOR, '/', $migrationScriptPath);
		$this->migrationScriptPath = $migrationScriptPath;
	}

	/**
	 * @return $this
	 */
	public function migrate() {
		$this->logger->info("Starting migration");

		$files = scandir($this->migrationScriptPath);
		sort($files);

		foreach($files as $file) {
			$path = $this->concatPaths($this->migrationScriptPath, $file);
			if(is_file($path)) {
				$entry = $this->getEntry($path);
				$shortEntry = $entry;
				if(preg_match('/^([\\d\\-]+).*(\\.php)$/', $entry, $matches)) {
					$shortEntry = sprintf('%s%s', $matches[1], $matches[2]);
				}
				if(!$this->db->hasEntry($shortEntry)) {
					$this->logger->info("Try to run upgrade file {$file}");
					$this->up($file);
					$this->db->addEntry($shortEntry);
					$this->logger->info("Done.");
				}
			}
		}

		$this->logger->info("Migration done.");
	}

	/**
	 * @param string $file
	 * @throws \Exception
	 */
	public function up($file) {
		$path = $this->concatPaths($this->migrationScriptPath, $file);
		$data = require $path;
		$statements = $data['statements'];

		$rollback = array();
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
					} catch(\Exception $e) {
					}
				}
				throw $e;
			}
		}
	}

	/**
	 * @param string $file
	 * @throws \Exception
	 */
	public function down($file) {
		$path = $this->concatPaths($this->migrationScriptPath, $file);
		$data = require $path;
		$statements = array_reverse($data['statements']);

		$rollback = array();
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
	 * @param Closure $statement
	 * @throws Exception
	 */
	private function runQuery(Closure $statement) {
		if(!is_callable($statement)) {
			throw new Exception("Invalid statement. Require a Closure to run.");
		}
		call_user_func($statement, $this->db);
	}

	/**
	 * @param string $path1
	 * @param string $path2
	 * @return string
	 */
	private function concatPaths($path1, $path2) {
		$path1 = rtrim($path1, '/');
		$path2 = ltrim($path2, '/');
		return $path1 . '/' . $path2;
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function getEntry($path) {
		return basename($path);
	}
}