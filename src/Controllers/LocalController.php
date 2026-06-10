<?php

namespace Controllers;

use Core\Request;
use Middlewares\AuthMiddleware;
use Models\Local;
use Exception;

class LocalController
{
    public function createLocal(Request $request): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado, apenas administradores podem acessar']);
            return;
        }
        $dados = $request->getBody();

        if (empty($dados['nome_local'])) {
            http_response_code(400);
            echo json_encode(['error' => 'nome do local é obrigatorio']);
            return;
        }

        $nome_local = htmlspecialchars(strip_tags($dados['nome_local']));

        $descricao = !empty($dados['descricao']) ? htmlspecialchars(strip_tags($dados['descricao'])) : '';
        $localModel = new Local();

        try {
            $id_local = $localModel->create($nome_local, $descricao);
            http_response_code(201);

            echo json_encode([
                'sucesso' => true,
                'message' => 'Local cadastro com sucesso',
                'id_local' => $id_local,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['erro' => 'Erro interno ao salvar o local ']);
        }
    }
    public function listLocal(Request $request): void
    {
        header('Content-Type: application/json');

        $localModel = new Local();

        try {
            $locais = $localModel->findAll();

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'total' => count($locais),
                'data' => $locais,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao buscar locais: ' . $e->getMessage()]);
        }
    }
}
