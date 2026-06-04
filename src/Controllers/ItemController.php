<?php

namespace Controllers;

use Core\Request;
use Middlewares\AuthMiddleware;
use Models\Categoria;
use Exception;

class ItemController
{
    public function criarCategoria(Request $request): void
    {

        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas admins podem gerar categorias']);
            return;
        }
        $dados = $request->getBody();

        if (empty($dados['nome'])) {
            http_response_code(400);
            echo json_encode(['error' => 'nome da categoria é obrigatorio']);
            return;
        }
        $nome = htmlspecialchars(strip_tags($dados['nome']));
        $categoriaModel = new Categoria();
        try {
            $id_categoria = $categoriaModel->create($nome);
            http_response_code(201);
            echo json_encode([
                'sucess' => true,
                'message' => 'Categoria criada com sucesso',
                'id_categoria' => $id_categoria,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno banco de dados' . $e->getMessage()]);
        }
    }
}
