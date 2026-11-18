<?php

class ModuleController
{
    private $files;
    private $moduleDir;

    public function __construct(FileModel $files, $moduleDir)
    {
        $this->files = $files;
        $this->moduleDir = $moduleDir;
    }

    public function index()
    {
        $folder = basename($this->moduleDir);
        Response::view('module', [
            'folder'     => $folder,
            'title'      => str_replace(['_', '-'], ' ', $folder),
            'phpVersion' => PHP_VERSION,
            'files'      => $this->files->all()
        ]);
    }

    public function handleAction($action)
    {
        try {
            if ($action === 'view_source') {
                $file = isset($_GET['file']) ? $_GET['file'] : '';
                $data = $this->files->read($file);
                Response::json([
                    'success'  => true,
                    'filename' => $data['filename'],
                    'ext'      => $data['ext'],
                    'code'     => $data['code']
                ]);
            }

            if ($action === 'save_source') {
                $file = isset($_POST['file']) ? $_POST['file'] : '';
                $code = isset($_POST['code']) ? $_POST['code'] : '';
                $meta = $this->files->save($file, $code);
                Response::json([
                    'success' => true,
                    'message' => "Salvo às {$meta['time']} ({$meta['bytes']} B)"
                ]);
            }

            if ($action === 'create_file') {
                $name = isset($_POST['filename']) ? $_POST['filename'] : '';
                $tpl = isset($_POST['template']) ? $_POST['template'] : 'oop_basic';
                $created = $this->files->create($name, $tpl);
                Response::json([
                    'success'  => true,
                    'filename' => $created,
                    'message'  => "Arquivo '{$created}' criado."
                ]);
            }

            if ($action === 'delete_file') {
                $file = isset($_POST['file']) ? $_POST['file'] : '';
                $this->files->delete($file);
                Response::json([
                    'success' => true,
                    'message' => 'Arquivo excluído.'
                ]);
            }

            if ($action === 'list_media') {
                Response::json([
                    'success' => true,
                    'data'    => $this->files->listMedia()
                ]);
            }

            if ($action === 'upload_media') {
                if (!isset($_FILES['media'])) {
                    throw new InvalidArgumentException('Nenhum arquivo enviado.');
                }
                $media = $this->files->uploadMedia($_FILES['media']);
                Response::json([
                    'success' => true,
                    'media'   => $media,
                    'message' => "Mídia '{$media['name']}' anexada."
                ]);
            }

            if ($action === 'delete_media') {
                $name = isset($_POST['name']) ? $_POST['name'] : '';
                $this->files->deleteMedia($name);
                Response::json([
                    'success' => true,
                    'message' => 'Mídia removida.'
                ]);
            }

            Response::json(['success' => false, 'message' => 'Ação inválida.'], 400);
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}