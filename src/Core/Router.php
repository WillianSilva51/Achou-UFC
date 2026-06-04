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
    /**
     * @param mixed $callback
     */
    public function get(string $path, $callback): void
    {
        $this->routers['GET'][$path] = $callback;
    }
    /**
     * @param mixed $callback
     */
    public function post(string $path, $callback): void
    {
        $this->routers['POST'][$path] = $callback;
    }
    /**
     * @return mixed
     */
    public function resolve()
    {
        $path = $this->request->getUri();
        $method = $this->request->getMethod();

        $callback = $this->routers[$method][$path] ?? false; // se nãoa achar recebe falso

        if ($callback === false) {
            http_response_code(404);
            echo json_encode([
                'Error' => 'Rota não encontrada, ou metodo incorreto',
            ]);
            return;
        }
        // posso executar diretamente? é uma funçao?
        if (is_callable($callback)) {
            return call_user_func($callback);
        }

        if (is_array($callback)) {
            $controller = new $callback[0](); // rlx mais tarde faz sentido instancair classe assim
            return call_user_func([$controller, $callback[1]], $this->request);
        }
    }
}

