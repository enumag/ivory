<?php

spl_autoload_register(function ($class) {
	$namespace = 'Ivory\\';
	if (substr($class, 0, strlen($namespace)) == $namespace) {
		require_once __DIR__ . '/../Ivory/' . substr($class, strlen($namespace)) . '.php';
	}
});
