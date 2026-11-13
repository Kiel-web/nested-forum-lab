<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use Bramus\Router\Router;

$config = require $root . '/config/config.php';

Session::start();
Database::configure($config['db']);
View::configure($root . '/app/Views');
$GLOBALS['forum_config'] = $config['forum'];

$router = new Router();
require $root . '/config/routes.php';
$router->run();
