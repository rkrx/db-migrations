<?php
namespace Kir\DB\Migrations\Schema;

final class FeatureGate {
	public function __construct(
		private EngineInfo $engine,
		private FeatureMatrix $matrix
	) {}

	public function supports(string $feature): bool {
		return $this->matrix->supports($this->engine, $feature);
	}

	/**
	 * @param string[] $features
	 * @return string[]
	 */
	public function unsupported(array $features): array {
		$unsupported = [];
		foreach($features as $feature) {
			if(!$this->supports($feature)) {
				$unsupported[] = $feature;
			}
		}
		return $unsupported;
	}

	public function describeEngine(): string {
		$version = $this->engine->version !== null ? $this->engine->version->raw : $this->engine->rawVersion;
		$version = $version ?? 'unknown';
		return sprintf('%s %s', $this->engine->engine, $version);
	}
}
