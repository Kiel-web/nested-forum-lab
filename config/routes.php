<?php
declare(strict_types=1);

use App\Core\View;
use Bramus\Router\Router;

/** @var Router $router */

$router->setNamespace('\App\Controllers');


$router->get('/',                       'DiscussionController@index');
$router->get('/discussions/([^/]+)',    'DiscussionController@show'); 


$router->get('/register',               'AuthController@showRegister');
$router->post('/register',              'AuthController@register');
$router->get('/login',                  'AuthController@showLogin');
$router->post('/login',                 'AuthController@login');
$router->post('/logout',                'AuthController@logout');


$router->post('/discussions/([^/]+)/comments', 'CommentController@store');

$router->set404(function (): void {
    View::error(404, 'Page not found.');
});
