<?php

namespace Controllers;

use Core\Request;
use Middlewares\AuthMiddleware;
use Models\Categoria;
use Exception;

class CategoriaController
{
    public function store(Request $request): void
    {
        header('Content-Type application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'Error' => 'Acesso negado, apenas administradores podem criar categorias',
            ]);
            return;
        }

        $dados = $request->getBody();

        if (empty($dados['nome'])) {
            http_response_code(400);
            echo json_encode([
                'Error' => 'O nome da categoria é obrigatorio',
            ]);
            return;
        }
        $nome = htmlspecialchars(strip_tags($dados['nome']));

        try {
            $id_categoria = new Categoria($nome);

            http_response_code(201);
            echo json_encode([
                'sucesso' => true,
                'message' => 'Categoaria criada com sucesso',
                'id_categoria' => $id_categoria,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'Error' => 'Erro interno' . $e->getMessage(),
            ]);
        }
    }

    public function index(Request $request): void
    {
        header('Content-Type application/json');

        $CategoriaModel = new Categoria();
        try {
            $categorias = CategoriaModel->findAll();

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'total' => count($categorias),
                'data' => $categorias,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['Error' => 'Erro ao listar categorias' . $e->getMessage()]);
        }
    }
}
