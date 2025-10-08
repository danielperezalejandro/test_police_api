<?php
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../controllers/UserController.php';

return function (App $app) {

    $controller = new UserController();

    // 🔹 GET /users
    $app->get('/users', [$controller, 'getUsers']);

    // 🔹 POST /users (registrar usuario)
    $app->post('/users', [$controller, 'createUser']);

    // 🔹 GET /ping (ruta de prueba)
    $app->get('/ping', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode(["message" => "pong"]));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
