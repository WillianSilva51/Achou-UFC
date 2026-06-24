<?php

namespace Controllers;

use Exception;
use Models\Aluno;
use Models\Usuario;
use Core\Database;
use PDOException;
use Firebase\JWT\JWT;
use Core\Request;
use Middlewares\AuthMiddleware;

class AuthController
{
    public function register(Request $request): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        \Core\RateLimiter::check($ip, 'register', 3, 300);

        header('Content-Type: application/json');

        $dados = $request->getBody();

        if (strlen($dados['senha']) < 8 || strlen($dados['senha']) > 72) {
            http_response_code(400);
            echo json_encode(['error' => 'A senha deve ter entre 8 e 72 caracteres.']);
            return;
        }

        if (empty($dados['nome']) || empty($dados['email']) || empty($dados['senha'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados incompletos. Nome, email e senha são obrigatórios.']);
            return;
        }

        $nome  = htmlspecialchars(strip_tags($dados['nome']), ENT_QUOTES, 'UTF-8');
        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
        $senha = $dados['senha']; 

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

        $role = 'aluno'; 
        
        $usuarioModel = new Usuario();
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $usuarioId = $usuarioModel->create($nome, $email, $senha, $role);

            if (empty($dados['matricula'])) {
                throw new Exception('A matrícula é obrigatória para cadastro de aluno.');
            }
            
            $matricula = htmlspecialchars(strip_tags($dados['matricula']), ENT_QUOTES, 'UTF-8');
            
            $alunoModel = new Aluno();
            $alunoModel->create($usuarioId, $matricula);
            
            $pdo->commit();

            http_response_code(201);
            echo json_encode([
                'sucesso'    => true,
                'mensagem'   => 'Usuário registrado com sucesso.',
                'usuario_id' => $usuarioId,
            ]);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            http_response_code(409); 
            
            echo json_encode(['error' => 'Os dados informados (e-mail ou matrícula) já estão em uso no sistema.']);
            
            error_log('Erro de BD no registro (Conflito): ' . $e->getMessage());
            
        } catch (\Exception $e)  {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['error' => 'Nao encontrado']);
        }
    }

    public function login(Request $request): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        \Core\RateLimiter::check($ip, 'login', 5, 300);

        header('Content-Type: application/json');
        $dados = $request->getBody();
        
        if (empty($dados['email']) || empty($dados['senha'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e senha são obrigatórios.']);
            return;
        }

        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
        $senha = $dados['senha'];

        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de email inválido.']);
            return;
        }

        $usuarioModel = new Usuario();
        $user = $usuarioModel->findByEmail($email);

        if (!$user || !password_verify($senha, $user['senha'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciais inválidas.']);
            return;
        }

        $tempoExpiracao = isset($_ENV['JWT_EXPIRATION']) ? (int)$_ENV['JWT_EXPIRATION'] : 1200;
        
        $payload = [
            'iss'  => 'achados_e_perdidos_ufc',
            'iat'  => time(),
            'exp'  => time() + $tempoExpiracao,
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

    public function logout(Request $request): void
    {
        header('Content-Type: application/json');
        
        AuthMiddleware::handle();

        http_response_code(200);
        echo json_encode([
            'sucesso' => true, 
            'mensagem' => 'Logout efetuado com sucesso. O token deve ser removido do armazenamento do cliente.'
        ]);
    }
    
}