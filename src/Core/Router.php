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

    public function put(string $path, $callback): void
    {
        $this->routers['PUT'][$path] = $callback;
    }

    public function delete(string $path, $callback): void
    {
        $this->routers['DELETE'][$path] = $callback;
    }

    private function buildPattern(string $route): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?<$1>[a-zA-Z0-9_-]+)', $route);
        return "@^" . $pattern . "$@";
    }

    public function resolve()
    {
        $path   = $this->request->getUri();
        $method = $this->request->getMethod();

        if ($method === 'OPTIONS') {
            http_response_code(200);
            return;
        }

        $routes = $this->routers[$method] ?? [];

        foreach ($routes as $route => $callback) {
            $pattern = $this->buildPattern($route);

            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            $params = [$this->request];
            foreach ($matches as $key => $value) {
                if (!is_string($key)) continue;

                if (!ctype_digit((string) $value) || (int) $value <= 0) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Recurso não encontrado.']);
                    return;
                }

                $params[] = (int) $value;
            }

            if (is_callable($callback)) {
                return call_user_func_array($callback, $params);
            }

            if (is_array($callback)) {
                $controller = new $callback[0]();
                return call_user_func_array([$controller, $callback[1]], $params);
            }
        }

        foreach ($this->routers as $metodo => $rotasDoMetodo) {
            if ($metodo === $method) continue;

            foreach ($rotasDoMetodo as $route => $_) {
                if (!preg_match($this->buildPattern($route), $path)) continue;

                $permitidos = [];
                foreach ($this->routers as $m => $rts) {
                    foreach ($rts as $rt => $_cb) {
                        if (preg_match($this->buildPattern($rt), $path)) {
                            $permitidos[] = $m;
                        }
                    }
                }

                http_response_code(405);
                header('Allow: ' . implode(', ', array_unique($permitidos)));
                echo json_encode(['error' => 'Método não permitido.']);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Rota não encontrada.']);
    }
}