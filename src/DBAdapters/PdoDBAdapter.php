<?php
namespace Kir\DB\Migrations\DBAdapters;

use Kir\DB\Migrations\ExecResult;
use PDO;
use PDOStatement;
use Kir\DB\Migrations\DBAdapter;

class PdoDBAdapter implements DBAdapter {
	/** @var PDO */
	private $db = null;
	/** @var PDOStatement[] */
	private $statements = array();
	/** @var string */
	private $tableName;

	/**
	 * @param PDO $db
	 * @param string $tableName
	 */
	public function __construct(PDO $db, $tableName='migrations') {
		$this->db = $db;
		$this->tableName = $tableName;
		$this->statements['has'] = $this->db->prepare("SELECT COUNT(*) FROM {$tableName} WHERE entry=:entry;");
		$this->statements['add'] = $this->db->prepare("INSERT IGNORE INTO {$tableName} SET entry=:entry;");
	}

	/**
	 * @return $this
	 */
	public function createMigrationsStore() {
		$this->db->exec("
			CREATE TABLE IF NOT EXISTS {$this->tableName} (
				`entry` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'latin1_bin',
				PRIMARY KEY (`entry`)
			) COLLATE='latin1_bin' ENGINE=InnoDB;
		");
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
		$stmt = $this->statements['add'];
		$stmt->bindValue('entry', $entry);
		$stmt->execute();
		$stmt->closeCursor();
		return $this;
	}

	/**
	 * @param string $query
	 * @param array $args
	 * @return ExecResult
	 */
	public function exec($query, array $args = array()) {
		echo "\n{$query}\n\n";

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
}