<?php
namespace Kir\DB\Migrations\Schema;

use Kir\DB\Migrations\DBAdapter;
use Throwable;

final class EngineInfo {
	public function __construct(
		public string $engine,
		public ?Version $version,
		public ?string $rawVersion = null
	) {}

	public static function detect(DBAdapter $db): self {
		$engine = 'unknown';
		$version = null;
		$rawVersion = null;

		try {
			$sqliteVersion = $db->query('SELECT sqlite_version()')->getValue();
			if(is_string($sqliteVersion)) {
				$engine = 'sqlite';
				$rawVersion = $sqliteVersion;
				$version = Version::fromString($sqliteVersion);
				return new self($engine, $version, $rawVersion);
			}
		} catch(Throwable $e) {
		}

		try {
			$versionValue = $db->query('SELECT VERSION()')->getValue();
			$commentValue = null;
			try {
				$commentValue = $db->query('SELECT @@version_comment')->getValue();
			} catch(Throwable $e) {
			}
			if(is_string($versionValue)) {
				$rawVersion = $versionValue;
				$isMariaDb = str_contains(strtolower($versionValue), 'mariadb');
				if(is_string($commentValue) && str_contains(strtolower($commentValue), 'mariadb')) {
					$isMariaDb = true;
				}
				$engine = $isMariaDb ? 'mariadb' : 'mysql';
				$version = Version::fromString($versionValue);
				return new self($engine, $version, $rawVersion);
			}
		} catch(Throwable $e) {
		}

		return new self($engine, $version, $rawVersion);
	}
}
