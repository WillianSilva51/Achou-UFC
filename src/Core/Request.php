<?php

namespace Core;
class Request {

    private array $server;
    private array $get;
    private array $post;

    public function __construct(array $server, array $get, array $post) {
        $this->server = $server;
        $this->get = $get;
        $this->post = $post;
    }

    public function getMethod(): string {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function getUri(): string {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = strtok($uri, '?');

        $base = dirname($this->server['SCRIPT_NAME']);

        if ($base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = rtrim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    public function getQuery(): array {
        return $this->get;
    }

    public function getBody(): array {
        return json_decode(file_get_contents("php://input"), true) ?? $this->post;
    }
}

?>