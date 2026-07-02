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

        $page  = isset($query['page'])  ? (int) $query['page']  : 1;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 20;

        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;

        $offset = ($page - 1) * $limit;

        $filtros = [];

        if (!empty($query['role'])) {
            $roleQuery = strtolower(htmlspecialchars(strip_tags($query['role']), ENT_QUOTES, 'UTF-8'));
            if (in_array($roleQuery, ['admin', 'aluno'], true)) {
                $filtros['role'] = $roleQuery;
            }
        }
        if (!empty($query['busca'])) {
            $filtros['busca'] = htmlspecialchars(strip_tags($query['busca']), ENT_QUOTES, 'UTF-8');
        }

        $usuarioModel = new Usuario();

        try {
            $total    = $usuarioModel->countFiltered($filtros);
            $usuarios = $usuarioModel->findAll($limit, $offset, $filtros);

            http_response_code(200);
            echo json_encode([
                'sucesso'   => true,
                'paginacao' => [
                    'total_registros'   => $total,
                    'pagina_atual'      => $page,
                    'limite_por_pagina' => $limit,
                    'total_paginas'     => (int) ceil($total / $limit),
                ],
                'filtros_aplicados' => $filtros,
                'data'              => $usuarios,
            ]);
        } catch (Exception $e) {
            error_log('Erro ao listar usuários: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao buscar usuários.']);
        }
    }

    public function update(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();
        $isSelf = (int) $usuarioLogado->sub === $id;

        if ($usuarioLogado->role !== 'admin' && !$isSelf) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado. Você só pode alterar os seus próprios dados.']);
            return;
        }

        $dados = $request->getBody();

        if (empty($dados['nome']) || empty($dados['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome e email são obrigatórios.']);
            return;
        }

        $usuarioModel = new Usuario();

        $existe = $usuarioModel->findById($id);
        if (!$existe) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuário não encontrado.']);
            return;
        }

        $nome  = htmlspecialchars(strip_tags($dados['nome']), ENT_QUOTES, 'UTF-8');
        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
        $role  = !empty($dados['role'])
            ? strtolower(htmlspecialchars(strip_tags($dados['role']), ENT_QUOTES, 'UTF-8'))
            : $existe['role'];

        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de email inválido.']);
            return;
        }

        if (!in_array($role, ['admin', 'aluno'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Role inválida. Use "admin" ou "aluno".']);
            return;
        }

        if ($isSelf && $role !== $existe['role']) {
            http_response_code(403);
            echo json_encode(['error' => 'Você não tem permissão para alterar o seu próprio nível de acesso.']);
            return;
        }

        if (mb_strlen($nome) < 3 || mb_strlen($nome) > 150) {
            http_response_code(400);
            echo json_encode(['error' => 'O nome deve ter entre 3 e 150 caracteres.']);
            return;
        }

        $dominio_valido = preg_match('/^[a-zA-Z0-9._%+-]+@(alu\.)?ufc\.br$/', $email);
        
        if (!$dominio_valido) {
            http_response_code(400);
            echo json_encode(['error' => 'Apenas e-mails institucionais (@ufc.br ou @alu.ufc.br) são permitidos.']);
            return;
        }

        try {
            $sucesso = $usuarioModel->update($id, $nome, $email, $role);
            if ($sucesso) {
                http_response_code(200);
                echo json_encode([
                    'sucesso' => true,
                    'mensagem' => 'Usuário atualizado com sucesso.',
                    'usuario' => [
                        'id' => $id,
                        'nome' => $nome,
                        'email' => $email,
                        'role' => $role,
                    ],
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    'sucesso' => true,
                    'mensagem' => 'Nenhuma alteração foi necessária.',
                    'usuario' => [
                        'id' => $id,
                        'nome' => $nome,
                        'email' => $email,
                        'role' => $role,
                    ],
                ]);
            }
        } catch (\PDOException $e) {
            if ($e->getCode() == 23505 || str_contains($e->getMessage(), 'uq_usuario_email')) {
                http_response_code(409);
                echo json_encode(['error' => 'Este e-mail já está sendo utilizado por outro usuário.']);
                return;
            }
            error_log('Erro de BD ao atualizar usuário: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao atualizar usuário.']);
        } catch (Exception $e) {
            error_log('Erro ao atualizar usuário: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao processar a atualização.']);
        }
    }

    public function updatePassword(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();
        $dados         = $request->getBody();

        if (empty($dados['nova_senha'])) {
            http_response_code(400);
            echo json_encode(['error' => 'O campo nova_senha é obrigatório.']);
            return;
        }

        if (strlen($dados['nova_senha']) < 8 || strlen($dados['nova_senha']) > 72) {
            http_response_code(400);
            echo json_encode(['error' => 'A nova senha deve ter entre 8 e 72 caracteres.']);
            return;
        }

        $usuarioModel = new Usuario();

        try {
            $userAlvo = $usuarioModel->findByIdComSenha($id);
            if (!$userAlvo) {
                http_response_code(404);
                echo json_encode(['error' => 'Usuário não encontrado.']);
                return;
            }

            if ((int) $usuarioLogado->sub === $id) {
                if (empty($dados['senha_atual'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'A senha atual é obrigatória para alterar sua própria senha.']);
                    return;
                }
                if (!password_verify($dados['senha_atual'], $userAlvo['senha'])) {
                    http_response_code(401);
                    echo json_encode(['error' => 'A senha atual está incorreta.']);
                    return;
                }
            } else {
                if ($usuarioLogado->role !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Você não tem permissão para alterar a senha de outro usuário.']);
                    return;
                }

                if ($userAlvo['role'] === 'admin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Um administrador não pode resetar a senha de outro administrador.']);
                    return;
                }

                if (empty($dados['senha_admin'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Confirme sua senha de administrador para resetar a senha de outro usuário.']);
                    return;
                }

                $adminUser = $usuarioModel->findByIdComSenha((int) $usuarioLogado->sub);
                if (!$adminUser || !password_verify($dados['senha_admin'], $adminUser['senha'])) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Senha do administrador incorreta. Acesso negado.']);
                    return;
                }
            }

            $sucesso = $usuarioModel->updatePassword($id, $dados['nova_senha']);
            if ($sucesso) {
                http_response_code(200);
                echo json_encode(['sucesso' => true, 'mensagem' => 'Senha atualizada com sucesso.']);
            } else {
                throw new Exception('Falha ao atualizar a senha no banco.');
            }
        } catch (Exception $e) {
            error_log('Erro no updatePassword: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao atualizar a senha.']);
        }
    }
}
