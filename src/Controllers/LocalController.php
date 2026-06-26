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
            echo json_encode(['error' => 'Acesso negado, apenas administradores podem acessar.']);
            return;
        }
        $dados = $request->getBody();

        $nome_local = !empty($dados['nome_local']) ? htmlspecialchars(strip_tags($dados['nome_local']), ENT_QUOTES, 'UTF-8') : '';
        $descricao  = !empty($dados['descricao']) ? htmlspecialchars(strip_tags($dados['descricao']), ENT_QUOTES, 'UTF-8') : '';

        if (empty($nome_local)) {
            http_response_code(400);
            echo json_encode(['error' => 'O nome do local é obrigatório.']);
            return;
        }

        $localModel = new Local();

        try {
            $id_local = $localModel->create($nome_local, $descricao);
            
            http_response_code(201);
            echo json_encode([
                'sucesso' => true,
                'message' => 'Local cadastrado com sucesso',
                'id_local' => $id_local,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() == 23505 || strpos($e->getMessage(), 'uq_local_nome') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Já existe um local cadastrado com este nome.']);
                return;
            }
            http_response_code(500);
            echo json_encode(['error' => 'Erro no banco de dados ao salvar o local.']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao salvar o local.']);
        }
    }    

    public function listLocal(Request $request): void
    {
        header('Content-Type: application/json');

        // 🛡️ FECHANDO A ROTA DE LISTAGEM
        AuthMiddleware::handle();

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
            echo json_encode(['error' => 'Erro interno ao buscar locais.']);
        }
    }
    
    public function show(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        AuthMiddleware::handle();

        $localModel = new Local();

        try {
            $local = $localModel->findById($id);

            if (!$local) {
                http_response_code(404);
                echo json_encode(['error' => 'Local não encontrado']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'data' => $local,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao buscar local']);
        }
    }

    public function update(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado, apenas administradores podem atualizar locais.']);
            return;
        }

        $dados = $request->getBody();

        $nome_local = !empty($dados['nome_local']) ? htmlspecialchars(strip_tags($dados['nome_local']), ENT_QUOTES, 'UTF-8') : '';
        $descricao  = !empty($dados['descricao']) ? htmlspecialchars(strip_tags($dados['descricao']), ENT_QUOTES, 'UTF-8') : '';

        if (empty($nome_local)) {
            http_response_code(400);
            echo json_encode(['error' => 'O nome do local é obrigatório.']);
            return;
        }

        $localModel = new Local();
        
        try {
            $sucesso = $localModel->update($id, $nome_local, $descricao);

            if ($sucesso) {
                http_response_code(200);
                echo json_encode(['sucesso' => true, 'mensagem' => 'Local atualizado com sucesso.']);
            } else {
                throw new Exception("Falha ao atualizar ou nenhuma alteração foi feita.");
            }
        } catch (\PDOException $e) {
            if ($e->getCode() == 23505 || strpos($e->getMessage(), 'uq_local_nome') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Já existe outro local cadastrado com este nome.']);
                return;
            }
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao atualizar local.']);
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
            echo json_encode(['error' => 'Acesso negado, apenas administradores podem deletar locais']);
            return;
        }

        $localModel = new Local();

        try {
            if (!$localModel->findById($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Local não encontrado para exclusão']);
                return;
            }

            $localModel->softDelete($id);

            http_response_code(200);
            echo json_encode(['sucesso' => true, 'message' => 'Local deletado com sucesso']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao deletar o local']);
        }
    }
}