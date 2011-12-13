<?php

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', TRUE);

function __autoload($class) {
    $namespace = 'Ivory\\StyleSheets\\';
    if (substr($class, 0, strlen($namespace)) == $namespace) {
        require_once 'IvoryStyleSheets/' . substr($class, strlen($namespace)) . '.php';
    }
}

$ivory = new \Ivory\StyleSheets\Compiler();
$ivory->setDefaultUnit('px');
try {
    if (isset($_POST['iss'])) {
        $ivory->addIncludePath(__DIR__ . '/examples');
        $output = $ivory->compileString($_POST['iss']);
    } else {
        $output = $ivory->compileFile(__DIR__ . '/examples/' . $_GET['input'] . '.iss');
    }
    if (is_string($output))
        echo($output);
    else
        var_dump($output);
} catch(\Ivory\StyleSheets\Exception $e) {
    echo $e->getMessage() . ' (' . ($e->getFile() ? $e->getFile() . ':' : 'řádek ') . $e->getLine() . ')';
}
