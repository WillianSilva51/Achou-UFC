<?php

namespace Controllers;

use Exception;
use Models\Administracao;
use Models\Aluno;
use Models\Usuario;
use Core\Database;
use PDOException;
use Firebase\JWT\JWT;
use Core\Request;

class AuthController
{
    public function register(Request $request): void
    {
        header('Content-Type: application/json');

        $dados = $request->getBody();

        if (empty($dados['nome']) || empty($dados['email']) || empty($dados['senha'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos. Nome, email e senha são obrigatórios.']);
            return;
        }

        $nome  = htmlspecialchars(strip_tags($dados['nome']));
        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
        $senha = $dados['senha'];
        $role  = $dados['role'] ?? 'aluno';

        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de email inválido.']);
            return;
        }

        if (strlen($senha) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'A senha deve ter no mínimo 8 dígitos.']);
            return;
        }

        $usuarioModel = new Usuario();
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $usuarioId = $usuarioModel->create($nome, $email, $senha, $role);

            if ($role === 'aluno') {
                if (empty($dados['matricula'])) {
                    throw new Exception('A matrícula é obrigatória para cadastro de aluno.');
                }
                $alunoModel = new Aluno();
                $alunoModel->create($usuarioId, $dados['matricula']);
            } elseif ($role === 'admin') {
                if (empty($dados['siap'])) {
                    throw new Exception('O SIAPE é obrigatório para cadastro de administrador.');
                }
                $adminModel = new Administracao();
                $adminModel->create($usuarioId, $dados['siap']);
            } else {
                throw new Exception("Role inválida no sistema.");
            }
            
            $pdo->commit();

            http_response_code(201);
            echo json_encode([
                'sucesso'    => true,
                'mensagem'   => 'Usuário registrado com sucesso.',
                'usuario_id' => $usuarioId,
            ]);

        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'error' => 'Conflito de dados: Email, Matrícula ou SIAPE já cadastrados.',
                'debug' => $e->getMessage() 
            ]);
        }  catch (\PDOException $e) {
            $pdo->rollBack();
            http_response_code(409); // 409 Conflict
            
            $mensagem = 'Erro de conflito de dados no banco.';
            
            // Lendo exatamente o nome da constraint que você criou
            if (strpos($e->getMessage(), 'uq_usuario_email') !== false) {
                $mensagem = 'Este e-mail já está cadastrado no sistema.';
            } elseif (strpos($e->getMessage(), 'uq_aluno_matricula') !== false) {
                $mensagem = 'Esta matrícula já está associada a outro aluno.';
            } elseif (strpos($e->getMessage(), 'uq_admin_siap') !== false) {
                $mensagem = 'Este SIAPE já está cadastrado no sistema.';
            }

            echo json_encode(['error' => $mensagem]);
            error_log('Erro de BD no registro: ' . $e->getMessage());
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function login(Request $request): void
    {
        header('Content-Type: application/json');

        $dados = $request->getBody();

        if (empty($dados['email']) || empty($dados['senha'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e senha são obrigatórios.']);
            return;
        }

        $usuarioModel = new Usuario();
        $user = $usuarioModel->findByEmail($dados['email']);

        if (!$user || !password_verify($dados['senha'], $user['senha'])) {
            echo json_encode(['error' => 'Credenciais inválidas.']);
            return;
        }

        $payload = [
            'iss'  => 'achados_e_perdidos_ufc',
            'iat'  => time(),
            'exp'  => time() + (15 * 60), 
            'sub'  => $user['id'],
            'role' => $user['role'],
        ];

        $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

        http_response_code(200);
        echo json_encode([
            'sucesso' => true,
            'token'   => $jwt,
            'usuario' => [
                'id'   => $user['id'],
                'nome' => $user['nome'],
                'role' => $user['role'],
            ],
        ]);
    }
}