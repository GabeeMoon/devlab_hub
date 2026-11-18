<?php

class FileModel
{
    private $dir;
    private $assetsDir;
    private $allowedExts = array('php', 'html', 'css', 'js', 'sql');
    private $mediaMap = array(
        'jpg'  => 'image',
        'jpeg' => 'image',
        'png'  => 'image',
        'gif'  => 'image',
        'webp' => 'image',
        'svg'  => 'image',
        'mp4'  => 'video',
        'webm' => 'video',
        'ogg'  => 'video',
        'mp3'  => 'audio',
        'wav'  => 'audio'
    );

    public function __construct($moduleDir = '')
    {
        $this->dir = rtrim((string) $moduleDir, DIRECTORY_SEPARATOR);
        $this->assetsDir = $this->dir . DIRECTORY_SEPARATOR . '_assets';
    }

    public function all()
    {
        if ($this->dir === '' || !is_dir($this->dir)) {
            return array();
        }

        $files = array();
        $entries = @scandir($this->dir);
        if (!is_array($entries)) {
            return array();
        }

        foreach ($entries as $item) {
            if ($item === '.' || $item === '..' || $item === 'index.php' || $item === '_assets') {
                continue;
            }

            $path = $this->dir . DIRECTORY_SEPARATOR . $item;
            if (!is_file($path)) {
                continue;
            }

            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedExts, true)) {
                continue;
            }

            $mtime = filemtime($path);
            $files[] = array(
                'filename' => $item,
                'title'    => str_replace(array('_', '-'), ' ', pathinfo($item, PATHINFO_FILENAME)),
                'ext'      => strtoupper($ext),
                'size'     => number_format(filesize($path) / 1024, 1, ',', '.') . ' KB',
                'modified' => date('d/m H:i', $mtime),
                'mtime'    => $mtime,
                'desc'     => $this->extractDescription($path)
            );
        }

        usort($files, function ($a, $b) {
            if ($a['mtime'] === $b['mtime']) {
                return 0;
            }
            return ($a['mtime'] > $b['mtime']) ? -1 : 1;
        });

        return $files;
    }

    public function read($filename = '')
    {
        $path = $this->resolveSafePath($filename);
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo não encontrado.');
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o arquivo.');
        }

        return array(
            'filename' => basename($path),
            'ext'      => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'code'     => $content
        );
    }

    public function save($filename = '', $code = '')
    {
        $path = $this->resolveSafePath($filename);
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo não encontrado.');
        }
        if (!is_writable($path)) {
            throw new RuntimeException('Arquivo sem permissão de escrita.');
        }

        $bytes = @file_put_contents($path, (string) $code);
        if ($bytes === false) {
            throw new RuntimeException('Falha ao gravar arquivo no disco.');
        }

        return array(
            'bytes' => $bytes,
            'time'  => date('H:i:s')
        );
    }

    public function create($rawName = '', $template = 'oop_basic')
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', trim((string) $rawName));
        if ($clean === '') {
            throw new InvalidArgumentException('Informe um nome válido para o arquivo.');
        }

        if (pathinfo($clean, PATHINFO_EXTENSION) === '') {
            $clean .= '.php';
        }

        $path = $this->resolveSafePath($clean);
        if (file_exists($path)) {
            throw new RuntimeException("O arquivo '{$clean}' já existe.");
        }
        if (!is_writable($this->dir)) {
            throw new RuntimeException('Diretório sem permissão de escrita.');
        }

        $content = $this->stubContent($clean, (string) $template);
        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException('Erro ao criar arquivo.');
        }

        return $clean;
    }

    public function delete($filename = '')
    {
        $path = $this->resolveSafePath($filename);
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo não encontrado.');
        }
        if (!@unlink($path)) {
            throw new RuntimeException('Sem permissão para excluir o arquivo.');
        }
    }

    public function listMedia()
    {
        if (!is_dir($this->assetsDir)) {
            return array();
        }

        $entries = @scandir($this->assetsDir);
        if (!is_array($entries)) {
            return array();
        }

        $items = array();
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $this->assetsDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($full)) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!isset($this->mediaMap[$ext])) {
                continue;
            }

            $items[] = array(
                'name' => $entry,
                'url'  => '_assets/' . rawurlencode($entry),
                'raw'  => '_assets/' . $entry,
                'kind' => $this->mediaMap[$ext],
                'ext'  => strtoupper($ext),
                'size' => number_format(filesize($full) / 1024, 1, ',', '.') . ' KB'
            );
        }

        return $items;
    }

    public function uploadMedia($file = array())
    {
        if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $code = isset($file['error']) ? $file['error'] : 'ausente';
            throw new RuntimeException('Falha no upload do arquivo (código ' . $code . ').');
        }

        $origName = basename((string) $file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!isset($this->mediaMap[$ext])) {
            throw new InvalidArgumentException('Formato não suportado. Use PNG, JPG, GIF, WEBP, SVG, MP4, WEBM, MP3, WAV ou OGG.');
        }

        if (!is_dir($this->assetsDir)) {
            if (!is_writable($this->dir) || !@mkdir($this->assetsDir, 0775, true)) {
                throw new RuntimeException('Sem permissão para criar diretório _assets.');
            }
        }

        $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $base = preg_replace('/_+/', '_', trim($base, '_'));
        if ($base === '') {
            $base = 'media_' . time();
        }

        $finalName = $base . '.' . $ext;
        $target = $this->assetsDir . DIRECTORY_SEPARATOR . $finalName;

        if (file_exists($target)) {
            $finalName = $base . '_' . substr(md5(uniqid('', true)), 0, 5) . '.' . $ext;
            $target = $this->assetsDir . DIRECTORY_SEPARATOR . $finalName;
        }

        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Não foi possível salvar a mídia em _assets.');
        }

        return array(
            'name' => $finalName,
            'url'  => '_assets/' . rawurlencode($finalName),
            'raw'  => '_assets/' . $finalName,
            'kind' => $this->mediaMap[$ext],
            'ext'  => strtoupper($ext)
        );
    }

    public function deleteMedia($filename = '')
    {
        $base = basename(trim((string) $filename));
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        if ($base === '' || !isset($this->mediaMap[$ext])) {
            throw new InvalidArgumentException('Arquivo de mídia inválido.');
        }

        $target = $this->assetsDir . DIRECTORY_SEPARATOR . $base;
        if (!is_file($target)) {
            throw new RuntimeException('Mídia não encontrada.');
        }

        if (!@unlink($target)) {
            throw new RuntimeException('Não foi possível remover a mídia.');
        }

        if (is_dir($this->assetsDir)) {
            $left = @scandir($this->assetsDir);
            if (is_array($left) && count($left) <= 2) {
                @rmdir($this->assetsDir);
            }
        }
    }

    private function resolveSafePath($filename)
    {
        $base = basename(trim((string) $filename));
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        if ($base === '' || $base === 'index.php' || !in_array($ext, $this->allowedExts, true)) {
            throw new InvalidArgumentException('Arquivo inválido ou protegido.');
        }

        return $this->dir . DIRECTORY_SEPARATOR . $base;
    }

    private function extractDescription($path)
    {
        $handle = @fopen($path, 'r');
        if (!$handle) {
            return '';
        }

        $desc = '';
        $lineNum = 0;
        while (($line = fgets($handle)) !== false && $lineNum < 8) {
            $lineNum++;
            if (preg_match('/^\s*(?:\/\/|#|\*)\s*Desc:\s*(.+)$/i', $line, $m)) {
                $desc = trim($m[1]);
                break;
            }
        }
        fclose($handle);
        return $desc;
    }

    private function stubContent($filename, $template)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext === 'html') {
            return "<!DOCTYPE html>\n<html lang=\"pt-BR\">\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{$filename}</title>\n</head>\n<body>\n    <h1>{$filename}</h1>\n</body>\n</html>\n";
        }
        if ($ext === 'css') {
            return "/* Desc: Estilos de {$filename} */\n:root {\n    --primary: #38bdf8;\n}\n\nbody {\n    font-family: sans-serif;\n}\n";
        }
        if ($ext === 'js') {
            return "// Desc: Script {$filename}\ndocument.addEventListener('DOMContentLoaded', function () {\n    console.log('{$filename} carregado');\n});\n";
        }
        if ($ext === 'sql') {
            return "-- Desc: Consultas SQL ({$filename})\nSELECT NOW() AS data_atual, VERSION() AS versao_mysql;\n";
        }

        if ($template === 'oop_db') {
            return "<?php\n// Desc: POO + PDO MySQL\nerror_reporting(E_ALL);\nini_set('display_errors', 1);\n\nrequire_once __DIR__ . '/../config/db.php';\n\n\$pdo = getDbConnection();\n\$stmt = \$pdo->query('SELECT NOW() AS server_time, VERSION() AS version');\nheader('Content-Type: application/json; charset=utf-8');\necho json_encode(\$stmt->fetch(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);\n";
        }

        if ($template === 'oop_basic') {
            $className = preg_replace('/[^a-zA-Z0-9]/', '', ucwords(pathinfo($filename, PATHINFO_FILENAME), '_-'));
            if ($className === '' || is_numeric($className[0])) {
                $className = 'Experimento';
            }
            return "<?php\n// Desc: Classe {$className}\nerror_reporting(E_ALL);\nini_set('display_errors', 1);\n\nclass {$className}\n{\n    public function run()\n    {\n        return '{$filename} executado com sucesso.';\n    }\n}\n\n\$app = new {$className}();\necho \$app->run();\n";
        }

        return "<?php\nerror_reporting(E_ALL);\nini_set('display_errors', 1);\n\n";
    }
}