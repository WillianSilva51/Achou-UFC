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
                'error' => 'Acesso negado, apenas administradores podem criar categorias',
            ]);
            return;
        }

        $dados = $request->getBody();

        $nome = !empty($dados['nome']) ? htmlspecialchars(strip_tags($dados['nome']), ENT_QUOTES, 'UTF-8') : '';
        
        if (empty($nome)) {
            http_response_code(400);
            echo json_encode([
                'error' => 'O nome da categoria é obrigatório',
            ]);
            return;
        }

        $categoriaModel = new Categoria();

        try {
            $id_categoria = $categoriaModel->create($nome);
            http_response_code(201);
            echo json_encode([
                'sucesso' => true,
                'message' => 'Categoria criada com sucesso',
                'id_categoria' => $id_categoria,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() == 23505 || strpos($e->getMessage(), 'uq_categoria_nome') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Já existe uma categoria cadastrada com este nome.']);
                return;
            }
            http_response_code(500);
            echo json_encode(['error' => 'Erro no banco de dados ao criar categoria.']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Erro interno ao processar a criação da categoria.',
            ]);
        }
    }

    public function index(Request $request): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::handle();
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
            echo json_encode(['error' => 'Erro interno ao listar categorias.']);
        }
    }

    public function show(Request $request, int $id): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::handle();

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

        $nome = !empty($dados['nome']) ? htmlspecialchars(strip_tags(trim($dados['nome'])), ENT_QUOTES, 'UTF-8') : '';

        if (empty($nome)) {
            http_response_code(400);
            echo json_encode(['error' => 'O nome da categoria é obrigatório']);
            return;
        }

        $categoriaModel = new Categoria();

        try {
            if (!$categoriaModel->findById($id)) {
            http_response_code(404);
            echo json_encode(['error' => 'Categoria não encontrada.']);
            return;
        }

            $sucesso = $categoriaModel->update($id, $nome);
            http_response_code(200);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Categoria atualizada com sucesso.']);


            /*if ($sucesso) {
                http_response_code(200);
                echo json_encode(['sucesso' => true, 'mensagem' => 'Categoria atualizada com sucesso.']);
            } else {
                throw new Exception("Falha ao atualizar ou nenhuma alteração foi feita.");
            }*/
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
            echo json_encode(['error' => 'Erro interno ao processar a atualização.']);
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

            $categoriaModel->delete($id);

            http_response_code(200);
            echo json_encode(['sucesso' => true, 'message' => 'Categoria deletada com sucesso']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao deletar a categoria']);
        }
    }
}