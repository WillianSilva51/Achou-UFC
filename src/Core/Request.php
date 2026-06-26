<?php

namespace Core;

class Request
{
    private array $server;
    private array $get;
    private array $post;

    public function __construct(array $server, array $get, array $post)
    {
        $this->server = $server;
        $this->get    = $get;
        $this->post   = $post;
    }

    public function getMethod(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function getUri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = strtok($uri, '?');

        $base = dirname($this->server['SCRIPT_NAME']);

        if ($base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = rtrim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    public function getQuery(): array
    {
        return $this->get;
    }

 
    public function getBody(): array
    {
        $method = $this->getMethod();

        if (in_array($method, ['GET', 'HEAD', 'DELETE', 'OPTIONS'], true)) {
            return [];
        }

        $contentType = $this->server['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');

            if ($raw === '' || $raw === false) {
                return [];
            }

            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'error'  => 'O corpo da requisição contém JSON inválido.',
                    'detail' => json_last_error_msg(),
                ]);
                exit;
            }

            return is_array($decoded) ? $decoded : [];
        }

        return $this->post;
    }
}
