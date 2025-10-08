<?php
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Middleware para errores
$app->addErrorMiddleware(true, true, true);

// Cargar las rutas
(require __DIR__ . '/../src/routes/userRoutes.php')($app);

// Ejecutar la app
$app->run();
