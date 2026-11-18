<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Response.php';
require_once __DIR__ . '/app/Models/WorkspaceModel.php';
require_once __DIR__ . '/app/Models/SchemaModel.php';
require_once __DIR__ . '/app/Controllers/HubController.php';
require_once __DIR__ . '/app/Controllers/DatabaseController.php';

$route = isset($_GET['route']) ? $_GET['route'] : 'home';
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($route === 'hub') {
    $controller = new HubController(new WorkspaceModel(__DIR__));
    $controller->handleAction($action);
}

if ($route === 'db') {
    try {
        $controller = new DatabaseController(new SchemaModel(Database::connection()));
        $controller->handle($action);
    } catch (Exception $e) {
        Response::json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

$hub = new HubController(new WorkspaceModel(__DIR__));
$hub->index();