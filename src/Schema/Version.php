<?php
namespace Kir\DB\Migrations\Schema;

final class Version {
	public function __construct(
		public int $major,
		public int $minor,
		public int $patch,
		public ?string $raw = null
	) {}

	public static function fromString(string $raw): ?self {
		if(!preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', $raw, $matches)) {
			return null;
		}
		$major = (int) $matches[1];
		$minor = (int) $matches[2];
		$patch = array_key_exists(3, $matches) ? (int) $matches[3] : 0;
		return new self($major, $minor, $patch, $raw);
	}

	public function isAtLeast(int $major, int $minor, int $patch = 0): bool {
		if($this->major !== $major) {
			return $this->major > $major;
		}
		if($this->minor !== $minor) {
			return $this->minor > $minor;
		}
		return $this->patch >= $patch;
	}

	public function matchesMajorMinor(int $major, int $minor): bool {
		return $this->major === $major && $this->minor === $minor;
	}
}
