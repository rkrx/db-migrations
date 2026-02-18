<?php
$autoload = __DIR__ . '/../vendor/autoload.php';
if(file_exists($autoload)) {
	require $autoload;
}

$envFile = __DIR__ . '/../.env.local';
if(file_exists($envFile)) {
	$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if($lines !== false) {
		foreach($lines as $line) {
			$line = trim($line);
			if($line === '' || str_starts_with($line, '#')) {
				continue;
			}
			$pos = strpos($line, '=');
			if($pos === false) {
				continue;
			}
			$key = trim(substr($line, 0, $pos));
			$value = trim(substr($line, $pos + 1));
			$value = trim($value, "\"'");
			if($key === '') {
				continue;
			}
			if(getenv($key) === false) {
				putenv("{$key}={$value}");
				$_ENV[$key] = $value;
				$_SERVER[$key] = $value;
			}
		}
	}
}
