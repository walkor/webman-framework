<?php

use Webman\App;
use Webman\Route;

$failures = [];
$base = sys_get_temp_dir() . '/webman_coroutine_load_test_' . getmypid();
if (is_dir($base)) {
    exec('rm -rf ' . escapeshellarg($base));
}
mkdir($base . '/app/controller', 0777, true);
mkdir($base . '/config', 0777, true);
define('BASE_PATH', $base);

require __DIR__ . '/../vendor/autoload.php';

file_put_contents($base . '/app/controller/BaseController.php', <<<'PHP'
<?php
namespace app\controller;
class BaseController
{
}

PHP
);
file_put_contents($base . '/app/controller/FooController.php', <<<'PHP'
<?php
namespace app\controller;
class FooController extends BaseController
{
    public function index()
    {
        return 'ok';
    }
}

PHP
);

$appPath = $base . '/app';
$appProperty = new ReflectionProperty(App::class, 'appPath');
$appProperty->setValue(null, $appPath);

$autoloadEntries = 0;
$autoloads = 0;
spl_autoload_register(function ($class) use ($base, &$autoloadEntries, &$autoloads) {
    if (!str_starts_with($class, 'app\\')) {
        return;
    }
    $file = $base . '/' . str_replace('\\', '/', $class) . '.php';
    if (!is_file($file)) {
        return;
    }
    if (Fiber::getCurrent() !== null) {
        $autoloads++;
        if ($autoloadEntries === 0) {
            $autoloadEntries++;
            Fiber::suspend();
        }
    }
    include $file;
});

Route::load([$base . '/config']);

$preloaded = class_exists('app\controller\FooController', false)
    && class_exists('app\controller\BaseController', false);
echo 'preloaded at worker start: ' . var_export($preloaded, true) . "\n";

$getController = new ReflectionMethod(App::class, 'getController');

$results = [];
$fibers = [];
for ($i = 0; $i < 2; $i++) {
    $fibers[$i] = new Fiber(function () use ($getController, &$results, $i) {
        $results[$i] = $getController->invoke(null, 'app\controller\FooController');
    });
}
$fibers[0]->start();
$fibers[1]->start();
foreach ($fibers as $fiber) {
    while ($fiber->isSuspended()) {
        $fiber->resume();
    }
}

foreach ($results as $i => $result) {
    echo 'fiber ' . $i . ' getController: ' . var_export($result, true) . "\n";
}
echo 'autoloader entered at request time: ' . var_export($autoloads > 0, true) . "\n";

register_shutdown_function(function () use ($base) {
    if (is_dir($base)) {
        exec('rm -rf ' . escapeshellarg($base));
    }
});

if (!$preloaded) {
    $failures[] = 'controllers were not loaded during Route::load at worker start';
}
if ($results[0] === false || $results[1] === false) {
    $failures[] = 'concurrent first resolution dropped a request: getController returned false';
}
if ($results[0] !== 'app\controller\FooController' || $results[1] !== 'app\controller\FooController') {
    $failures[] = 'both fibers must resolve the controller class name';
}
if ($autoloads > 0) {
    $failures[] = 'controller class loading must not happen at request time';
}

if ($failures) {
    foreach ($failures as $failure) {
        echo 'FAIL: ' . $failure . "\n";
    }
    exit(1);
}
echo "PASS\n";
