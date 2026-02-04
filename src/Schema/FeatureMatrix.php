<?php
namespace Kir\DB\Migrations\Schema;

final class FeatureMatrix {
	public function supports(EngineInfo $engine, string $feature): bool {
		if($engine->engine === 'mariadb') {
			if($engine->version === null || !$engine->version->matchesMajorMinor(10, 6)) {
				return false;
			}
			return true;
		}

		if($engine->engine === 'sqlite') {
			return $this->supportsSqlite($engine->version, $feature);
		}

		return false;
	}

	private function supportsSqlite(?Version $version, string $feature): bool {
		return match($feature) {
			Feature::CREATE_TABLE,
			Feature::DROP_TABLE,
			Feature::ALTER_ADD_COLUMN,
			Feature::ALTER_ADD_INDEX,
			Feature::ALTER_DROP_INDEX => $version !== null,
			Feature::ALTER_DROP_COLUMN => $version !== null && $version->isAtLeast(3, 35, 0),
			default => false,
		};
	}
}
