<?php

/**
 * Pop - Emoji Reaction Widget
 * @author Sven Eisenschmidt
 * @license MIT
 */

$pharFile = $argv[1] ?? 'pop.phar';
$srcDir = dirname(__DIR__);

if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile, 0, 'pop.phar');
$phar->startBuffering();

$directories = ['src', 'config', 'vendor'];

foreach ($directories as $dir) {
    $path = $srcDir . '/' . $dir;
    if (is_dir($path)) {
        $phar->buildFromDirectory($srcDir, '#^' . preg_quote($srcDir . '/' . $dir, '#') . '#');
    }
}

// Add public/index.php as the entry point
$phar->addFile($srcDir . '/public/index.php', 'public/index.php');

// Add bin/console
if (file_exists($srcDir . '/bin/console')) {
    $phar->addFile($srcDir . '/bin/console', 'bin/console');
}

$stub = <<<'STUB'
<?php
Phar::mapPhar('pop.phar');
require 'phar://pop.phar/public/index.php';
__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();

echo "Created $pharFile\n";
