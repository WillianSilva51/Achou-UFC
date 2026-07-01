<?php

namespace Controllers;

use Exception;
use Models\Aluno;
use Models\Usuario;
use Core\Database;
use PDOException;
use Firebase\JWT\JWT;
use Core\Request;
use Core\Recaptcha;
use Middlewares\AuthMiddleware;

class AuthController
{
    public function register(Request $request): void
    {
        header('Content-Type: application/json');

        $dados = $request->getBody();

        $recaptchaToken = $dados['recaptcha_token'] ?? '';
        if (empty($recaptchaToken)) {
            http_response_code(400);
            echo json_encode(['error' => 'Verificação de segurança (reCAPTCHA) não realizada.']);
            return;
        }

        try {
            $recaptchaValido = Recaptcha::verify($recaptchaToken);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno de configuração de segurança.']);
            error_log('AuthController::register — ' . $e->getMessage());
            return;
        }

        if (!$recaptchaValido) {
            http_response_code(403);
            echo json_encode(['error' => 'Falha na verificação de segurança. Por favor, tente novamente.']);
            return;
        }

        if (empty($dados['nome']) || empty($dados['email']) || empty($dados['senha']) || empty($dados['role'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome, email, senha e role são obrigatórios.']);
            return;
        }

        $role = strtolower(trim($dados['role']));
        if (!in_array($role, ['admin', 'aluno'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Role inválida. Utilize apenas admin ou aluno.']);
            return;
        }

        if ($role === 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Cadastro público de administradores não é permitido.']);
            return;
        }

        $matricula = null;

        if ($role === 'aluno') {
            if (empty($dados['matricula'])) {
                http_response_code(400);
                echo json_encode(['error' => 'A matrícula é obrigatória para alunos.']);
                return;
            }
            $matricula = trim(preg_replace('/\s+/', '', $dados['matricula']));
            if (!preg_match('/^\d{6,12}$/', $matricula)) {
                http_response_code(400);
                echo json_encode(['error' => 'Formato de matrícula inválido. Use apenas números (6 a 12 dígitos).']);
                return;
            }
        }

        if (strlen($dados['senha']) < 8 || strlen($dados['senha']) > 72) {
            http_response_code(400);
            echo json_encode(['error' => 'A senha deve ter entre 8 e 72 caracteres.']);
            return;
        }

        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de email inválido.']);
            return;
        }

        $nome  = htmlspecialchars(strip_tags($dados['nome']), ENT_QUOTES, 'UTF-8');
        $senha = $dados['senha'];

        if (mb_strlen($nome) < 3 || mb_strlen($nome) > 150) {
            http_response_code(400);
            echo json_encode(['error' => 'O nome deve ter entre 3 e 150 caracteres.']);
            return;
        }

        $usuarioModel = new \Models\Usuario();
        $pdo = \Core\Database::getConnection();

        try {
            $pdo->beginTransaction();

            $usuarioId = $usuarioModel->create($nome, $email, $senha, $role);

            if ($role === 'aluno') {
                $alunoModel = new \Models\Aluno();
                $alunoModel->create($usuarioId, $matricula);
            }

            $pdo->commit();


            http_response_code(201);
            echo json_encode([
                'sucesso'    => true,
                'mensagem'   => 'Usuário registrado com sucesso.',
                'usuario_id' => $usuarioId,
            ]);

        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() == 23505 || strpos($e->getMessage(), 'uq_') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Os dados informados (e-mail ou matrícula) já estão em uso no sistema.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro interno ao registrar usuário.']);
            }
            error_log('Erro de BD no registro: ' . $e->getMessage()); 
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro no registro de usuário: ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function login(Request $request): void
    {
        header('Content-Type: application/json');

        $dados = $request->getBody();

        $recaptchaToken = $dados['recaptcha_token'] ?? '';
        if (empty($recaptchaToken)) {
            http_response_code(400);
            echo json_encode(['error' => 'Verificação de segurança (reCAPTCHA) não realizada.']);
            return;
        }

        try {
            $recaptchaValido = Recaptcha::verify($recaptchaToken);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno de configuração de segurança.']);
            error_log('AuthController::login — ' . $e->getMessage());
            return;
        }

        if (!$recaptchaValido) {
            http_response_code(403);
            echo json_encode(['error' => 'Falha na verificação de segurança. Por favor, tente novamente.']);
            return;
        }
        // ── fim reCAPTCHA ──────────────────────────────────────────────────────

        if (empty($dados['email']) || empty($dados['senha'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e senha são obrigatórios.']);
            return;
        }

        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
        $senha = $dados['senha'];

        if (!$email) {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciais inválidas.']);
            return;
        }

        $usuarioModel = new Usuario();

        $user = $usuarioModel->verificarCredenciais($email, $senha);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciais inválidas.']);
            return;
        }

        $tempoExpiracao = isset($_ENV['JWT_EXPIRATION']) ? (int) $_ENV['JWT_EXPIRATION'] : 1200;

        $payload = [
            'iss'  => 'achados_e_perdidos_ufc',
            'jti'  => bin2hex(random_bytes(16)),
            'iat'  => time(),
            'exp'  => time() + $tempoExpiracao,
            'sub'  => $user['id'],
            'role' => $user['role'],
        ];

        $jwtSecret = $_ENV['JWT_SECRET'] ?? '';
        if (strlen($jwtSecret) < 32) {
            error_log('AuthController::login — JWT_SECRET deve ter pelo menos 32 caracteres.');
            http_response_code(500);
            echo json_encode(['error' => 'Configuração de autenticação inválida no servidor.']);
            return;
        }

        try {
            $jwt = JWT::encode($payload, $jwtSecret, 'HS256');
        } catch (\Throwable $e) {
            error_log('AuthController::login — erro ao gerar JWT: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao iniciar sessão.']);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'sucesso' => true,
            'token'   => $jwt,
            'usuario' => [
                'id'        => $user['id'],
                'nome'      => $user['nome'],
                'email'     => $user['email'],
                'role'      => $user['role'],
                'matricula' => $user['matricula'] ?? null,
                'siap'      => $user['siap'] ?? null,
            ],
        ]);
    }

    public function logout(Request $request): void
    {
        header('Content-Type: application/json');
        $payload = AuthMiddleware::handle();

        http_response_code(200);
        echo json_encode([
            'sucesso'  => true,
            'mensagem' => 'Logout efetuado com sucesso. Remova o token do armazenamento do cliente.',
        ]);
    }
}
