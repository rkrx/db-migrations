<?php
namespace Kir\DB\Migrations\DBAdapters;

use Kir\DB\Migrations\ExecResult;
use Kir\DB\Migrations\Helpers\Whitespace;
use Kir\DB\Migrations\QueryResult;
use PDO;
use PDOStatement;
use Kir\DB\Migrations\DBAdapter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PdoDBAdapter implements DBAdapter {
	/** @var PDO */
	private $db = null;
	/** @var PDOStatement[] */
	private $statements = array();
	/** @var string */
	private $tableName;
	/** @var LoggerInterface */
	private $logger;
	/** @var Whitespace */
	private $whitespace = null;

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
		$this->statements['has'] = $this->db->prepare("SELECT COUNT(*) FROM {$tableName} WHERE entry=:entry;");
		$this->statements['add'] = $this->db->prepare("INSERT INTO {$tableName} (entry) VALUES (:entry);");
		if($logger === null) {
			$logger = new NullLogger();
		}
		$this->logger = $logger;
	}

	/**
	 * @param string $entry
	 * @return string
	 */
	public function hasEntry($entry) {
		$stmt = $this->statements['has'];
		$stmt->bindValue('entry', $entry);
		$stmt->execute();
		$result = $stmt->fetchColumn(0);
		$stmt->closeCursor();
		return $result > 0;
	}

	/**
	 * @param string $entry
	 * @return $this
	 */
	public function addEntry($entry) {
		if(!$this->hasEntry($entry)) {
			$stmt = $this->statements['add'];
			$stmt->bindValue('entry', $entry);
			$stmt->execute();
			$stmt->closeCursor();
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
		foreach($args as $key => $value) {
			$key = ltrim($key, ':');
			$stmt->bindValue($key, $value);
		}
		$stmt->execute();
		$stmt->closeCursor();

		$lastInsertId = $this->db->lastInsertId();
		$rowCount = $stmt->rowCount();
		$result = new ExecResult($lastInsertId, $rowCount);

		$this->db->exec("/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;");
		$this->db->exec("/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;");

		return $result;
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
}