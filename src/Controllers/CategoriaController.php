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
        header('Content-Type: application/json');

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
            $id_categoria = new Categoria();
            $id_categoria = $id_categoria->create($nome);   
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
        header('Content-Type: application/json');

        $categoriaModel = new Categoria();
        try {
            $categorias = $categoriaModel->findAll();

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
    public function show(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $categoriaModel = new Categoria();

        try {
            $categoria = $categoriaModel->findById($id);

            if (!$categoria) {
                http_response_code(404);
                echo json_encode(['error' => 'Categoria não encontrada']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'data' => $categoria,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao buscar categoria']);
        }
    }

    public function update(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado, apenas administradores podem atualizar categorias']);
            return;
        }

        $dados = $request->getBody();

        if (empty($dados['nome'])) {
            http_response_code(400);
            echo json_encode(['error' => 'O nome da categoria é obrigatório']);
            return;
        }

        $nome = htmlspecialchars(strip_tags($dados['nome']));
        $categoriaModel = new Categoria();

        try {
            $sucesso = $categoriaModel->update($id, $nome);

            if ($sucesso) {
                http_response_code(200);
                echo json_encode(['sucesso' => true, 'mensagem' => 'Categoria atualizada com sucesso.']);
            } else {
                throw new Exception("Falha ao atualizar ou nenhuma alteração foi feita.");
            }
        } catch (\PDOException $e) {
            if ($e->getCode() == 23505 || strpos($e->getMessage(), 'uq_categoria_nome') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Já existe outra categoria com este nome.']);
                return;
            }
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao atualizar categoria.']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    public function destroy(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado, apenas administradores podem deletar categorias']);
            return;
        }

        $categoriaModel = new Categoria();

        try {
            if (!$categoriaModel->findById($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Categoria não encontrada para exclusão']);
                return;
            }

            $categoriaModel->softDelete($id);

            http_response_code(200);
            echo json_encode(['sucesso' => true, 'message' => 'Categoria deletada com sucesso']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao deletar a categoria']);
        }
    }
}
