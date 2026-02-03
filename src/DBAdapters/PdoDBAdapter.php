<?php
namespace Kir\DB\Migrations\DBAdapters;

use JetBrains\PhpStorm\Language;
use Kir\DB\Migrations\Commands\EnsureColumn;
use Kir\DB\Migrations\Common\Command;
use Kir\DB\Migrations\Common\Expr;
use Kir\DB\Migrations\DBAdapter;
use Kir\DB\Migrations\ExecResult;
use Kir\DB\Migrations\Helpers\EntryName;
use Kir\DB\Migrations\Helpers\TableObj;
use Kir\DB\Migrations\Helpers\Whitespace;
use Kir\DB\Migrations\QueryResult;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class PdoDBAdapter implements DBAdapter {
	/** @var array<string, string> */
	private array $statements = [];
	private Whitespace $whitespace;

	/**
	 * @param PDO $db
	 * @param string $tableName
	 * @param ?LoggerInterface $logger
	 */
	public function __construct(
		private PDO $db,
		private string $tableName = 'migrations',
		private ?LoggerInterface $logger = null
	) {
		$this->createMigrationsStore();
		$this->whitespace = new Whitespace();
		$this->statements['fix'] = "SELECT entry FROM {$tableName} WHERE LENGTH(entry)!=19;";
		$this->statements['fix_delete'] = "DELETE FROM {$tableName} WHERE entry=:entry";
		$this->statements['fix_update'] = "UPDATE {$tableName} SET entry=:new_entry WHERE entry=:old_entry";
		$this->statements['list'] = "SELECT entry FROM {$tableName};";
		$driverName = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
		$driverName = is_string($driverName) ? strtolower($driverName) : '';
		$insertPrefix = $driverName === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
		$this->statements['add'] = "{$insertPrefix} INTO {$tableName} (entry) VALUES (:entry);";
		if($logger === null) {
			$logger = new NullLogger();
		}
		$this->logger = $logger;
		$this->fixEntries();
	}

	/**
	 * @param string $entry
	 * @return $this
	 */
	public function addEntry(string $entry): self {
		$entry = EntryName::shorten($entry);
		$this->execStmt($this->statements['add'], ['entry' => $entry], fn($x) => $x);
		return $this;
	}

	/**
	 * @return array<string>
	 */
	public function listEntries(): array {
		return $this->execStmt($this->statements['list'], [], function (PDOStatement $stmt) {
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$entries = [];
			foreach($rows as $row) {
				if(!array_key_exists('entry', $row)) {
					continue;
				}
				$entry = $row['entry'];
				if(is_string($entry)) {
					$entries[] = $entry;
				}
			}
			return $entries;
		});
	}

	/**
	 * @return string|null
	 */
	public function getDatabase(): ?string {
		$database = $this->query('SELECT DATABASE()')->getValue();
		if(is_string($database)) {
			return $database;
		}
		return null;
	}

	/**
	 * @param string $tableName
	 * @param string|null $database
	 * @return TableObj
	 */
	public function table(string $tableName, ?string $database = null): TableObj {
		$database = $database ?? $this->getDatabase();
		if($database === null) {
			throw new RuntimeException("No database selected");
		}
		return new TableObj($this, $database, $tableName);
	}

	/**
	 * @param string $query
	 * @param array<string, null|scalar> $args
	 * @return QueryResult
	 */
	public function query(
		#[Language('MySQL')]
		string $query,
		array $args = array()
	): QueryResult {
		$query = $this->whitespace->stripMargin($query);
		$this->logger?->info("\n{$query}");

		$stmt = $this->db->prepare($query);
		foreach($args as $key => $value) {
			$key = ltrim($key, ':');
			$stmt->bindValue($key, $value);
		}
		$stmt->execute();
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stmt->closeCursor();

		return new QueryResult($data);
	}

	/**
	 * @param string $query
	 * @param array<string, null|scalar> $args
	 * @return ExecResult
	 */
	public function exec(
		#[Language('MySQL')]
		string $query,
		array $args = []
	): ExecResult {
		$query = $this->whitespace->stripMargin($query);
		$this->logger?->info("\n{$query}");

		$this->db->exec("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;");
		$this->db->exec("/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;");

		$stmt = $this->db->prepare($query);
		try {
			foreach($args as $key => $value) {
				$key = ltrim($key, ':');
				$stmt->bindValue($key, $value);
			}
			$stmt->execute();

			$lastInsertId = $this->db->lastInsertId();
			$lastInsertId = $lastInsertId !== false ? $lastInsertId : null;

			$rowCount = $stmt->rowCount();
			$result = new ExecResult($lastInsertId, $rowCount);

			$this->db->exec("/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;");
			$this->db->exec("/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;");

			return $result;
		} finally {
			if($stmt instanceof PDOStatement) {
				$stmt->closeCursor();
			}
		}
	}
	
	/**
	 * @template T
	 * @param string $query
	 * @param array<string, null|scalar> $args
	 * @param callable(PDOStatement):T $callback
	 * @return T
	 */
	private function execStmt(
		#[Language('MySQL')]
		string $query,
		array $args,
		$callback
	) {
		$stmt = $this->db->prepare($query);
		if($stmt === false) {
			throw new RuntimeException("Failed to prepare query: {$query}");
		}
		try {
			foreach($args as $key => $value) {
				$stmt->bindValue($key, $value);
			}
			$stmt->execute();
			return $callback($stmt);
		} finally {
			if($stmt instanceof PDOStatement) {
				$stmt->closeCursor();
			}
		}
	}

	/**
	 * @return void
	 */
	private function createMigrationsStore(): void {
		$driverName = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
		$driverName = is_string($driverName) ? $driverName : '';
		$this->db->exec(
			match (strtolower($driverName) === 'mysql') {
				true => "
					CREATE TABLE IF NOT EXISTS {$this->tableName} (
						`entry` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'latin1_bin',
						PRIMARY KEY (`entry`)
					) COLLATE='latin1_bin' ENGINE=InnoDB;
				",
				default => "
					CREATE TABLE IF NOT EXISTS {$this->tableName} (
						`entry` VARCHAR(255) NOT NULL DEFAULT '',
						PRIMARY KEY (`entry`)
					);
				"
			}
		);
	}

	/**
	 * Fix wrong entries
	 */
	private function fixEntries(): void {
		$entries = $this->execStmt($this->statements['fix'], [], fn (PDOStatement $stmt) => $stmt->fetchAll(PDO::FETCH_ASSOC));
		foreach($entries as $entry) {
			$oldEntry = $entry['entry'];
			$newEntry = EntryName::shorten($oldEntry);

			// Remove possibly existing $newEntry before renaming the wrong one...
			$this->execStmt($this->statements['fix_delete'], ['entry' => $newEntry], fn($x) => $x);

			// Rename the old name to the short-one
			$this->execStmt($this->statements['fix_update'], ['old_entry' => $oldEntry, 'new_entry' => $newEntry], fn($x) => $x);
		}
	}
}
