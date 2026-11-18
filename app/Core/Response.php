<?php

class Response
{
    public static function view($view, array$data = array())
    {
        $file = __DIR__ . '/../Views/' . basename($view) . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('View não encontrada: ' . htmlspecialchars($view));
        }

        extract($data, EXTR_SKIP);
        require $file;
        exit;
    }

    public static function json($data = array(),$status = 200)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}