<?php

class ModuleController
{
    private $files;
    private $moduleDir;

    public function __construct(FileModel $files, $moduleDir = '')
    {
        $this->files = $files;
        $this->moduleDir = (string) $moduleDir;
    }

    public function index()
    {
        $folder = basename($this->moduleDir);
        Response::view('module', array(
            'folder'     => $folder,
            'title'      => str_replace(array('_', '-'), ' ', $folder),
            'phpVersion' => PHP_VERSION,
            'files'      => $this->files->all()
        ));
    }

    public function handleAction($action = '')
    {
        try {
            $act = trim((string) $action);

            if ($act === 'view_source') {
                $file = $this->param('file', $this->param('filename', ''));
                $data = $this->files->read($file);
                Response::json(array(
                    'success'  => true,
                    'filename' => $data['filename'],
                    'ext'      => $data['ext'],
                    'code'     => $data['code']
                ));
            }

            if ($act === 'save_source') {
                $file = $this->param('file', $this->param('filename', ''));
                $code = isset($_POST['code']) ? $_POST['code'] : '';
                $meta = $this->files->save($file, $code);
                Response::json(array(
                    'success' => true,
                    'message' => "Salvo às {$meta['time']} ({$meta['bytes']} B)"
                ));
            }

            if ($act === 'create_file') {
                $name = $this->param('filename', $this->param('file', ''));
                $tpl  = $this->param('template', 'oop_basic');
                $created = $this->files->create($name, $tpl);
                Response::json(array(
                    'success'  => true,
                    'filename' => $created,
                    'message'  => "Arquivo '{$created}' criado."
                ));
            }

            if ($act === 'delete_file') {
                $file = $this->param('file', $this->param('filename', ''));
                $this->files->delete($file);
                Response::json(array(
                    'success' => true,
                    'message' => 'Arquivo excluído.'
                ));
            }

            if ($act === 'list_media') {
                Response::json(array(
                    'success' => true,
                    'data'    => $this->files->listMedia()
                ));
            }

            if ($act === 'upload_media') {
                if (!isset($_FILES['media'])) {
                    throw new InvalidArgumentException('Nenhum arquivo enviado.');
                }
                $media = $this->files->uploadMedia($_FILES['media']);
                Response::json(array(
                    'success' => true,
                    'media'   => $media,
                    'message' => "Mídia '{$media['name']}' anexada."
                ));
            }

            if ($act === 'delete_media') {
                $name = $this->param('name', $this->param('file', ''));
                $this->files->deleteMedia($name);
                Response::json(array(
                    'success' => true,
                    'message' => 'Mídia removida.'
                ));
            }

            Response::json(array('success' => false, 'message' => 'Ação inválida: ' . $act), 400);
        } catch (Exception $e) {
            Response::json(array('success' => false, 'message' => $e->getMessage()), 400);
        }
    }

    private function param($key, $default = '')
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }
        return $default;
    }
}