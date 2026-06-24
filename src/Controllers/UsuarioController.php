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

        $usuarioModel = new Usuario();

        try {
            $usuarios = $usuarioModel->findAll();
            
            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'total'   => count($usuarios),
                'data'    => $usuarios,
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

        $nome  = htmlspecialchars(strip_tags($dados['nome']));
        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
        $role  = strtolower(htmlspecialchars(strip_tags($dados['role'])));

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
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao atualizar usuário.']);
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

        if (empty($dados['nova_senha']) || strlen($dados['nova_senha']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'A nova senha é obrigatória e deve ter no mínimo 8 caracteres.']);
            return;
        }

        $nova_senha = $dados['nova_senha'];
        $usuarioModel = new Usuario();

        try {
            $sucesso = $usuarioModel->updatePassword($id, $nova_senha);

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