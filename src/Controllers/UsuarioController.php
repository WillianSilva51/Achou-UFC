<?php

namespace Controllers;

use Core\Request;
use Middlewares\AuthMiddleware;
use Models\Usuario;
use Exception;

class UsuarioController
{
    public function index(Request $request): void
    {
        header('Content-Type: application/json');
        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado. Apenas administradores podem listar usuários.']);
            return;
        }

        $query = $request->getQuery();
        
        $page  = isset($query['page']) ? (int) $query['page'] : 1;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 20;

        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;

        $offset = ($page - 1) * $limit;

        $filtros = [];
        
        if (!empty($query['role'])) {
            $filtros['role'] = htmlspecialchars(strip_tags($query['role']), ENT_QUOTES, 'UTF-8');
        }
        if (!empty($query['busca'])) {
            $filtros['busca'] = htmlspecialchars(strip_tags($query['busca']), ENT_QUOTES, 'UTF-8');
        }

        $usuarioModel = new Usuario();

        try {
            $total = $usuarioModel->countFiltered($filtros);
            $usuarios = $usuarioModel->findAll($limit, $offset, $filtros);
            
            http_response_code(200);
            echo json_encode([
                'sucesso'   => true,
                'paginacao' => [
                    'total_registros'   => $total,
                    'pagina_atual'      => $page,
                    'limite_por_pagina' => $limit,
                    'total_paginas'     => ceil($total / $limit)
                ],
                'filtros_aplicados' => $filtros,
                'data' => $usuarios,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao buscar usuários.']);
        }
    }

   public function update(Request $request, int $id): void
    {
        header('Content-Type: application/json');
        
        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado. Apenas administradores podem alterar dados de usuários.']);
            return;
        }

        $dados = $request->getBody();

        if (empty($dados['nome']) || empty($dados['email']) || empty($dados['role'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome, email e role são obrigatórios.']);
            return;
        }

        $nome  = htmlspecialchars(strip_tags($dados['nome']), ENT_QUOTES, 'UTF-8');
        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL); 
        $role  = strtolower(htmlspecialchars(strip_tags($dados['role']), ENT_QUOTES, 'UTF-8'));

        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de email inválido.']);
            return;
        }

        if (!in_array($role, ['admin', 'aluno'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Role inválida. Use "admin" ou "aluno".']);
            return;
        }

        $usuarioModel = new Usuario();

        try {
            $sucesso = $usuarioModel->update($id, $nome, $email, $role);

            if ($sucesso) {
                http_response_code(200);
                echo json_encode(['sucesso' => true, 'mensagem' => 'Usuário atualizado com sucesso.']);
            } else {
                throw new Exception("Falha ao atualizar ou nenhuma alteração foi feita.");
            }
        } catch (\PDOException $e) {
            if ($e->getCode() == 23505 || strpos($e->getMessage(), 'uq_usuario_email') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Este e-mail já está sendo utilizado por outro usuário.']);
                return;
            }
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao atualizar usuário.']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao processar a atualização.']);
        }
    }

    public function updatePassword(Request $request, int $id): void
    {
        header('Content-Type: application/json');
        
        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->sub != $id && $usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Você não tem permissão para alterar a senha deste usuário.']);
            return;
        }

        $dados = $request->getBody();

        if (empty($dados['senha_atual']) || empty($dados['nova_senha']) || strlen($dados['nova_senha']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'A senha atual e a nova senha (mínimo 8 caracteres) são obrigatórias.']);
            return;
        }

        $usuarioModel = new Usuario();

        try {
            $user = $usuarioModel->findById($id);
            
            if (!$user || !password_verify($dados['senha_atual'], $user['senha'])) {
                http_response_code(401);
                echo json_encode(['error' => 'A senha atual está incorreta.']);
                return;
            }

            $sucesso = $usuarioModel->updatePassword($id, $dados['nova_senha']);
            if ($sucesso) {
                http_response_code(200);
                echo json_encode(['sucesso' => true, 'mensagem' => 'Senha atualizada com sucesso.']);
            } else {
                throw new Exception("Falha ao atualizar a senha.");
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao atualizar a senha.']);
        }
    }
}