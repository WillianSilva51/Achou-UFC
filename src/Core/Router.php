<?php

namespace Core;

use Core\Request;

class Router
{
    protected array $routers = [];
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function get(string $path, $callback): void
    {
        $this->routers['GET'][$path] = $callback;
    }

    public function post(string $path, $callback): void
    {
        $this->routers['POST'][$path] = $callback;
    }

    // Adicionado o PUT
    public function put(string $path, $callback): void
    {
        $this->routers['PUT'][$path] = $callback;
    }

    public function delete(string $path, $callback): void
    {
        $this->routers['DELETE'][$path] = $callback;
    }

    public function resolve()
    {
        $path = $this->request->getUri();
        $method = $this->request->getMethod();

        if ($method === 'OPTIONS') {
            http_response_code(200);
            return;
        }

        $routes = $this->routers[$method] ?? [];

        foreach ($routes as $route => $callback) {
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?<$1>[a-zA-Z0-9_-]+)', $route);
            $pattern = "@^" . $pattern . "$@";

            if (preg_match($pattern, $path, $matches)) {
                
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[] = $value;
                    }
                }

                array_unshift($params, $this->request);

                if (is_callable($callback)) {
                    return call_user_func_array($callback, $params);
                }

                if (is_array($callback)) {
                    $controller = new $callback[0]();
                    return call_user_func_array([$controller, $callback[1]], $params);
                }
            }
        }

        http_response_code(404);
        echo json_encode([
            'error' => 'Rota não encontrada ou método incorreto',
        ]);
        return;
    }
}