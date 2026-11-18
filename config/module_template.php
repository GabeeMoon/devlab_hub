<?php

if (!defined('MODULE_DIR')) {
    http_response_code(403);
    exit('Acesso direto não permitido.');
}

require_once __DIR__ . '/../app/Core/Response.php';
require_once __DIR__ . '/../app/Models/FileModel.php';
require_once __DIR__ . '/../app/Controllers/ModuleController.php';

$controller = new ModuleController(new FileModel(MODULE_DIR), MODULE_DIR);

if (isset($_REQUEST['ide_action']) && $_REQUEST['ide_action'] !== '') {
    $controller->handleAction($_REQUEST['ide_action']);
}

$controller->index();