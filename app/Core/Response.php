<?php

class Response
{
    public static function json($payload, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    public static function view($template, $data = [])
    {
        extract($data);
        require __DIR__ . '/../Views/' . $template . '.php';
        exit;
    }
}