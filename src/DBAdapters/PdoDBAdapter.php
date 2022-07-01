<?php
namespace Kir\DB\Migrations\DBAdapters;

use Kir\DB\Migrations\ExecResult;
use Kir\DB\Migrations\Helpers\EntryName;
use Kir\DB\Migrations\Helpers\Whitespace;
use Kir\DB\Migrations\QueryResult;
use PDO;
use PDOStatement;
use Kir\DB\Migrations\DBAdapter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PdoDBAdapter implements DBAdapter {
	private PDO $db;
	/** @var PDOStatement[] */
	private array $statements = [];
	private string $tableName;
	private LoggerInterface $logger;
	private Whitespace $whitespace;

	/**
	 * @param PDO $db
	 * @param string $tableName
	 * @param LoggerInterface $logger
	 */
	public function __construct(PDO $db, $tableName='migrations', LoggerInterface $logger = null) {
		$this->db = $db;
		$this->tableName = $tableName;
		$this->createMigrationsStore();
		$this->whitespace = new Whitespace();
		$this->statements['fix'] = "SELECT entry FROM {$tableName} WHERE LENGTH(entry)!=19;";
		$this->statements['fix_delete'] = "DELETE FROM {$tableName} WHERE entry=:entry";
		$this->statements['fix_update'] = "UPDATE {$tableName} SET entry=:new_entry WHERE entry=:old_entry";
		$this->statements['has'] = "SELECT COUNT(*) FROM {$tableName} WHERE entry=:entry;";
		$this->statements['add'] = "INSERT INTO {$tableName} (entry) VALUES (:entry);";
		if($logger === null) {
			$logger = new NullLogger();
		}
		$this->logger = $logger;
		$this->fixEntries();
	}

	/**
	 * @param string $entry
	 * @return string
	 */
	public function hasEntry($entry) {
		$entry = EntryName::shorten($entry);
		
		return $this->execStmt($this->statements['has'], ['entry' => $entry], function (PDOStatement $stmt) {
			$count = intval($stmt->fetchColumn(0));
			return $count > 0;
		});
	}

	/**
	 * @param string $entry
	 * @return $this
	 */
	public function addEntry($entry) {
		$entry = EntryName::shorten($entry);
		if(!$this->hasEntry($entry)) {
			$this->execStmt($this->statements['add'], ['entry' => $entry]);
		}
		return $this;
	}

	/**
	 * @param string $tableName
	 * @return TableDef
	 */
	public function table($tableName) {
		return new TableDef($this, $tableName);
	}

	/**
	 * @param string $query
	 * @param array $args
	 * @return QueryResult
	 */
	public function query($query, array $args = array()) {
		$query = $this->whitespace->stripMargin($query);
		$this->logger->info("\n{$query}");

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
	 * @param array $args
	 * @return ExecResult
	 */
	public function exec($query, array $args = array()) {
		$query = $this->whitespace->stripMargin($query);
		$this->logger->info("\n{$query}");

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
	 * @param string $query
	 * @param array $args
	 * @param callable|null $callback
	 * @return mixed
	 */
	private function execStmt($query, array $args, $callback = null) {
		$stmt = $this->db->prepare($query);
		try {
			foreach($args as $key => $value) {
				$stmt->bindValue($key, $value);
			}
			$stmt->execute();
			if($callback) {
				return call_user_func($callback, $stmt);
			}
			return null;
		} finally {
			if($stmt instanceof PDOStatement) {
				$stmt->closeCursor();
			}
		}
	}

	/**
	 * @return $this
	 */
	private function createMigrationsStore() {
		$driverName = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
		switch(strtolower($driverName)) {
			case 'mysql':
				$this->db->exec("
					CREATE TABLE IF NOT EXISTS {$this->tableName} (
						`entry` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'latin1_bin',
						PRIMARY KEY (`entry`)
					) COLLATE='latin1_bin' ENGINE=InnoDB;
				");
				break;
			default:
				$this->db->exec("
					CREATE TABLE IF NOT EXISTS {$this->tableName} (
						`entry` VARCHAR(255) NOT NULL DEFAULT '',
						PRIMARY KEY (`entry`)
					);
				");
		}
	}
	
	/**
	 * Fix wrong entries
	 */
	private function fixEntries() {
		$entries = $this->execStmt($this->statements['fix'], [], function (PDOStatement $stmt) {
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		});
		foreach($entries as $entry) {
			$oldEntry = $entry['entry'];
			$newEntry = EntryName::shorten($oldEntry);
			
			// Remove possibly existing $newEntry before renaming the wrong one...
			$this->execStmt($this->statements['fix_delete'], ['entry' => $newEntry]);
			
			// Rename the old name to the short-one
			$this->execStmt($this->statements['fix_update'], ['old_entry' => $oldEntry, 'new_entry' => $newEntry]);
		}
	}
}