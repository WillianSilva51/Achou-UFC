<?php

namespace Controllers;

use Exception;
use Models\Administracao;
use Models\Aluno;
use Models\Usuario;
use Core\Database;
use PDO;
use PDOException;
use Firebase\JWT\JWT;
use Core\Request;

class AuthController
{
    public function register(): void
    {
        header('Content-Type: application/json');

        $json = file_get_contents('php://input'); // faço requisição pego json completo string
        $dados = json_decode($json, true); // true ta transformadno em array associativo

        if (!$dados || empty($dados['nome']) || empty($dados['email']) || empty($dados['senha'])) {
            http_response_code(400); // badrequest
            echo json_encode([
                'Error' => 'dados incompletos, nome, email, senha são obrigatorios',
            ], true);
            return;
        }

        $nome = htmlspecialchars(strip_tags($dados['nome']));
        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
        $senha = $dados['senha'];
        $role = $dados['role'] ?? 'aluno';

        if (empty($nome)) {
            http_response_code(400);
            echo json_encode([
                'Error' => 'Nome é um paramtro obrigatorio',
            ]);
            return;
        }

        if (empty($email)) {
            http_response_code(400);
            echo json_encode([
                'Error' => 'Email é um parametro obrigatorio',
            ]);
            return;
        }

        if (empty($senha) || strlen($senha) < 8) {
            http_response_code(400);
            echo json_encode([
                'Error' => 'Senha deve ter no minimo 8 digitos',
            ]);
            return;
        }
        $usuarioModel = new Usuario();
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $usuarioId = $usuarioModel->create($nome, $email, $senha, $role);

            if ($role === 'aluno') {
                if (empty($dados['matricula'])) {
                    throw new Exception('A matricula é obrigatoria a cada aluno');
                }
                $alunoModel = new Aluno();
                $alunoModel->create($usuarioId, $dados['matricula']);
            } elseif ($role === 'admin') {
                if (empty($dados['siap'])) {
                    throw new Exception('Siap é orbigatorio a cada Administrador');
                }
                $adminModel = new Administracao();
                $adminModel->create($usuarioId, $dados['siap']);
            } else {
                throw new Exception("Role invalida no sistema");
            }
            $pdo->commit();

            http_response_code(201);// created
            echo json_encode([
                'sucesso' => true,
                'mensage' => 'Usuario resgitrado com sucesso',
                'usuario_id' => $usuarioId,
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(400);

            echo json_encode([
                'error' => 'Conflito de dados, email ou siap ja cadastrados',
            ]);
            error_log('Error de BD no registro ' . $e->getMessage());
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(400);

            // Agora o Nginx/PHP vai te fofocar o erro exato do banco no seu curl
            echo json_encode([
                'error' => 'Erro no BD: ' . $e->getMessage(),
            ]);
        }
    }

    public function login(Request $request): void
    {

        $dados = $request->getBody();
        if (empty($dados['email']) || empty($dados['senha'])) {
            http_response_code(400); // badrequest
            echo json_encode([
                'error' => 'Erro ao realizar login ',
            ]);
            return;
        }
        $usuarioModel = new Usuario();
        $user = $usuarioModel->findbyEmail($dados['email']);

        if (!$user || !password_verify($dados['senha'], $user['senha'])) {
            http_response_code(400); // não autorizado
            echo json_encode(['error' => 'Credenciais invalidas']);
            return;
        }
        $payload = [
            'iss' => 'achados_e_perdidos_ufc',
            'iat' => time(),
            'exp' => time() + (15 * 60),
            'sub' => $user['id'],
            'role' => $user['role'],
        ];
        $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

        http_response_code(200);
        echo json_encode([
            'sucess' => true,
            'token' => $jwt,
            'usuario' => [
                'id' => $user['id'],
                'nome' => $user['nome'],
                'role' => $user['role'],
            ],
        ]);
    }
}
