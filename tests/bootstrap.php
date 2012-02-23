<?php

spl_autoload_register(function ($class) {
    $namespace = 'Ivory\\StyleSheets\\';
    if (substr($class, 0, strlen($namespace)) == $namespace) {
        require_once __DIR__ . '/../IvoryStyleSheets/' . substr($class, strlen($namespace)) . '.php';
    }
});
