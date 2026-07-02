<?php

namespace Controllers;

use Exception;
use Models\Aluno;
use Models\Usuario;
use Core\Database;
use Core\Mailer;
use Core\RateLimiter;
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

        $dominio_valido = preg_match('/^[a-zA-Z0-9._%+-]+@(alu\.)?ufc\.br$/', $email);

        if (!$dominio_valido) {
            http_response_code(400); 
            echo json_encode(['error' => 'Apenas e-mails institucionais (@ufc.br ou @alu.ufc.br) são permitidos.']);
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

            $codigo = $usuarioModel->gerarCodigoVerificacao($usuarioId);
            if (!$this->enviarCodigoAtivacao($email, $nome, $codigo)) {
                throw new \RuntimeException('Não foi possível enviar o código de ativação. Verifique o email informado e tente novamente.');
            }

            $pdo->commit();


            http_response_code(201);
            echo json_encode([
                'sucesso'    => true,
                'mensagem'   => 'Cadastro criado. Enviamos um código para seu email institucional para ativar a conta.',
                'usuario_id' => $usuarioId,
                'requires_verification' => true,
                'email' => $email,
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

        if ($user['role'] === 'aluno' && empty($user['email_verificado_em'])) {
            $codigo = $usuarioModel->gerarCodigoVerificacao((int) $user['id']);
            if (!$this->enviarCodigoAtivacao($user['email'], $user['nome'], $codigo)) {
                http_response_code(500);
                echo json_encode(['error' => 'Sua conta precisa ser ativada, mas não foi possível enviar o código agora. Tente novamente em instantes.']);
                return;
            }

            http_response_code(403);
            echo json_encode([
                'error' => 'Sua conta ainda não está ativa. Enviamos um código para seu email institucional; informe o código para ativar a conta.',
                'requires_verification' => true,
                'email' => $user['email'],
            ]);
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

    public function verifyEmail(Request $request): void
    {
        header('Content-Type: application/json');
        $dados = $request->getBody();

        if (empty($dados['email']) || empty($dados['codigo'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e código são obrigatórios.']);
            return;
        }

        $email = filter_var($dados['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de email inválido.']);
            return;
        }

        RateLimiter::check($this->rateLimitKey($email), 'verify-email', 8, 300);

        $usuarioModel = new Usuario();
        $resultado = $usuarioModel->verificarCodigoEmail($email, (string) $dados['codigo']);

        if (!$resultado['ok']) {
            http_response_code(400);
            echo json_encode(['error' => $resultado['error'] ?? 'Código inválido ou expirado.']);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Conta ativada com sucesso. Agora você já pode fazer login.',
        ]);
    }

    public function resendVerification(Request $request): void
    {
        header('Content-Type: application/json');
        $dados = $request->getBody();

        $email = filter_var($dados['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Informe um email válido.']);
            return;
        }

        RateLimiter::check($this->rateLimitKey($email), 'resend-verification', 3, 300);

        $usuarioModel = new Usuario();
        $user = $usuarioModel->findByEmail($email);

        if (!$user || !empty($user['email_verificado_em'])) {
            http_response_code(200);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Se a conta precisar de ativação, um novo código será enviado.']);
            return;
        }

        $codigo = $usuarioModel->gerarCodigoVerificacao((int) $user['id']);
        if (!$this->enviarCodigoAtivacao($user['email'], $user['nome'], $codigo)) {
            http_response_code(500);
            echo json_encode(['error' => 'Não foi possível enviar o código agora. Tente novamente em instantes.']);
            return;
        }

        http_response_code(200);
        echo json_encode(['sucesso' => true, 'mensagem' => 'Novo código enviado para seu email institucional.']);
    }

    private function enviarCodigoAtivacao(string $email, string $nome, string $codigo): bool
    {
        $codigoSeguro = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
        $nomeSeguro = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');

        $html = "
            <p>Olá, {$nomeSeguro}.</p>
            <p>Use o código abaixo para ativar sua conta no Achou UFC:</p>
            <p style=\"font-size:24px;font-weight:bold;letter-spacing:4px;\">{$codigoSeguro}</p>
            <p>O código expira em 15 minutos. Se você não solicitou este cadastro, ignore este email.</p>
        ";

        return Mailer::enviar($email, $nome, 'Código de ativação - Achou UFC', $html);
    }

    private function rateLimitKey(string $email): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return $ip . '|' . strtolower($email);
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
