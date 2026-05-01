<?php
declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');
Router::post('/account/{contaId}/balance/withdraw', 'App\Infrastructure\Http\ContaController@sacar');
