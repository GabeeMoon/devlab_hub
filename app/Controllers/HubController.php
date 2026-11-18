<?php

class HubController
{
    private $workspace;

    public function __construct(WorkspaceModel $workspace)
    {
        $this->workspace = $workspace;
    }

    public function index()
    {
        require_once __DIR__ . '/../../config/db.php';
        $modules = $this->workspace->all();

        $totalFiles = 0;
        foreach ($modules as $mod) {
            $totalFiles += $mod['count'];
        }

        Response::view('layout', [
            'phpVersion' => PHP_VERSION,
            'dbName'     => defined('DB_NAME') ? DB_NAME : 'meu_sistema',
            'dbHost'     => defined('DB_HOST') ? DB_HOST : '127.0.0.1',
            'modules'    => $modules,
            'totalFiles' => $totalFiles
        ]);
    }

    public function handleAction($action)
    {
        try {
            if ($action === 'create_module') {
                $name = isset($_POST['folder_name']) ? $_POST['folder_name'] : '';
                $created = $this->workspace->create($name);
                Response::json(['success' => true, 'message' => "Diretório '{$created}' criado."]);
            }

            if ($action === 'delete_module') {
                $name = isset($_POST['folder_name']) ? $_POST['folder_name'] : '';
                $this->workspace->delete($name);
                Response::json(['success' => true, 'message' => 'Diretório removido.']);
            }

            Response::json(['success' => false, 'message' => 'Ação desconhecida.'], 400);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}