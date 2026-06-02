<?php

namespace Core; // diferencia dos outros Cores de outras libs
use Core\Request; // serve pra mim nunca ter que digitar
// caminh completo da classe como $db = new Core\Database();




// route ainda muito limitado, modificar dps

class Router{
    protected array $routers = [];
    protected Request $request;

    public function __construct(Request $request) {
        $this->request = $request;
    }

    public function get(string $path, $callback) : void{
        $this->routers['GET'][$path] = $callback;
    }


    public function post(string $path, $callback) : void {
        $this->routers['POST'][$path] = $callback;
    }


    public function resolve(){
        $path = $this->request->getUri();
        $method = $this->request->getMethod();

        $callback = $this->routers[$method][$path] ?? false; // se nãoa achar recebe falso


        if($callback === false){
            http_response_code(404);
            return "<h4> caminho n existe <h4>";
        }

        // posso executar diretamente? é uma funçao?
        if (is_callable($callback)) {
            return call_user_func($callback);
        }

        // pergunta se isso é um array
        // Futuramente, aqui chamaremos os nossos Controllers (Secure e Vulnerable)
        if (is_array($callback)) {
            $controller = new $callback[0](); // rlx mais tarde faz sentido instancair classe assim
            return call_user_func([$controller, $callback[1]]);
        }
    }

}


?>