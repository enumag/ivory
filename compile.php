<?php

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', TRUE);

function __autoload($class) {
    $namespace = 'Ivory\\StyleSheets\\';
    if (substr($class, 0, strlen($namespace)) == $namespace) {
        require_once 'IvoryStyleSheets/' . substr($class, strlen($namespace)) . '.php';
    }
}

if (isset($_POST['iss'])) {
    $input = $_POST['iss'];
}

$ivory = new \Ivory\StyleSheets\Compiler();
try {
    $output = $ivory->compileString($input);
    if (is_string($output))
        echo($output);
    else
        var_dump($output);
} catch(Exception $ex) {
    echo $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage();
}
