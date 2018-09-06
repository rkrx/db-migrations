<?php
namespace Kir\DB\Migrations\Helpers;

class Whitespace {
	/** @var int */
	private $tabWidth;
	/** @var string */
	private $lineBreak;

	/**
	 * @param int $tabWidth
	 * @param string $lineBreak
	 */
	public function __construct($tabWidth = 4, $lineBreak = "\n") {
		$this->tabWidth = $tabWidth;
		$this->lineBreak = $lineBreak;
	}

	/**
	 * @param string $data
	 * @return string
	 */
	public function stripMargin($data) {
		$lines = preg_split("/\\r?\\n/", $data);
		$tabs = str_repeat(' ', $this->tabWidth);
		$lines = $this->stripHead($lines);
		$lines = array_reverse($lines);
		$lines = $this->stripHead($lines);
		$lines = array_reverse($lines);
		$lines = $this->stripLeft($lines, $tabs);
		$lines = $this->stripRight($lines);
		return join($this->lineBreak, $lines);
	}

	/**
	 * @param array $lines
	 * @return array
	 */
	private function stripHead($lines) {
		while(count($lines) > 0 && !strlen(trim($lines[0]))) {
			array_shift($lines);
			$lines = array_values($lines);
		}
		return $lines;
	}

	/**
	 * @param array $lines
	 * @param string $tabs
	 * @return array
	 */
	private function stripLeft($lines, $tabs) {
		$indentation = null;

		foreach($lines as &$line) {
			preg_match('/^([\\t ]*)/', $line, $matches);
			$whitespace = strtr($matches[1], ["\t" => $tabs]);
			$line = $whitespace . ltrim($line);
			if($indentation === null) {
				$indentation = strlen($whitespace);
			}
			$indentation = min(strlen($whitespace), $indentation);
		}

		foreach($lines as &$line) {
			$line = substr($line, $indentation);
		}

		return $lines;
	}

	/**
	 * @param array $lines
	 * @return array
	 */
	private function stripRight($lines) {
		return array_map('rtrim', $lines);
	}
}