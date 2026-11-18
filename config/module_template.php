<?php

if (!defined('MODULE_DIR')) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../app/Core/Response.php';
require_once __DIR__ . '/../app/Models/FileModel.php';
require_once __DIR__ . '/../app/Controllers/ModuleController.php';

$controller = new ModuleController(new FileModel(MODULE_DIR), MODULE_DIR);

if (isset($_GET['ide_action'])) {
    $controller->handleAction($_GET['ide_action']);
}

$controller->index();