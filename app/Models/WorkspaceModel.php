<?php

class WorkspaceModel
{
    private $baseDir;
    private $ignored = ['.', '..', 'app', 'public', 'config', 'assets', 'vendor', '.git'];

    public function __construct($baseDir)
    {
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
    }

    public function all()
    {
        $modules = [];
        $entries = scandir($this->baseDir);

        foreach ($entries as $entry) {
            if (in_array($entry, $this->ignored, true)) {
                continue;
            }

            $fullPath = $this->baseDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($fullPath)) {
                continue;
            }

            $this->ensureStub($fullPath);
            $files = $this->listUserFiles($fullPath);

            $modules[] = [
                'folder' => $entry,
                'title'  => str_replace(['_', '-'], ' ', $entry),
                'count'  => count($files),
                'url'    => rawurlencode($entry) . '/'
            ];
        }

        return $modules;
    }

    public function create($rawName)
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '_', str_replace(' ', '_', trim($rawName)));
        $clean = preg_replace('/_+/', '_', trim($clean, '_'));

        if ($clean === '' || in_array(strtolower($clean), $this->ignored, true)) {
            throw new RuntimeException('Nome de diretório inválido ou reservado.');
        }

        $target = $this->baseDir . DIRECTORY_SEPARATOR . $clean;
        if (file_exists($target)) {
            throw new RuntimeException("O diretório '{$clean}' já existe.");
        }

        if (!is_writable($this->baseDir)) {
            throw new RuntimeException('Diretório raiz sem permissão de escrita.');
        }

        if (!mkdir($target, 0775)) {
            throw new RuntimeException('Falha ao criar diretório no servidor.');
        }

        $this->ensureStub($target);
        return $clean;
    }

    public function delete($rawName)
    {
        $folder = basename(trim($rawName));
        if ($folder === '' || in_array(strtolower($folder), $this->ignored, true)) {
            throw new RuntimeException('Diretório protegido pelo sistema.');
        }

        $target = $this->baseDir . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($target)) {
            throw new RuntimeException('Diretório não encontrado.');
        }

        $userFiles = $this->listUserFiles($target);
        if (count($userFiles) > 0) {
            throw new RuntimeException("Diretório contém " . count($userFiles) . " arquivo(s). Remova-os antes.");
        }

        $stub = $target . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($stub) && !@unlink($stub)) {
            throw new RuntimeException('Não foi possível remover o arquivo index.php interno.');
        }

        if (!@rmdir($target)) {
            throw new RuntimeException('Falha ao remover diretório.');
        }
    }

    private function listUserFiles($dirPath)
    {
        $files = [];
        foreach (scandir($dirPath) as $item) {
            if ($item !== '.' && $item !== '..' && $item !== 'index.php') {
                $files[] = $item;
            }
        }
        return $files;
    }

    private function ensureStub($dirPath)
    {
        $stubFile = $dirPath . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($stubFile) && is_writable($dirPath)) {
            $code = "<?php\ndefine('MODULE_DIR', __DIR__);\nrequire_once __DIR__ . '/../config/module_template.php';\n";
            file_put_contents($stubFile, $code);
        }
    }
}