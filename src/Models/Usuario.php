<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Usuario extends BaseModel
{
    protected string $table = 'usuario';
    private const EMAIL_CODE_TTL_MINUTES = 15;
    private const EMAIL_CODE_MAX_ATTEMPTS = 5;
    private const TOKEN_TYPE_ACTIVATION = 'ativacao_conta';
    private const DUMMY_HASH = '$2y$12$usdummyhashparaevitartimingXXXXXXXXXXXXXXXXXXXXXXXXX';


    public function __construct()
    {
        parent::__construct();
    }

    public function verificarCredenciais(string $email, string $senha): ?array
    {
        $query = "SELECT u.id,
                         u.nome,
                         u.email,
                         u.senha,
                         u.role,
                         u.email_verificado_em,
                         a.matricula,
                         ad.siap
                  FROM {$this->table} u
                  LEFT JOIN aluno a ON a.aluno_id = u.id
                  LEFT JOIN administracao ad ON ad.id = u.id
                  WHERE u.email = :email
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $hashValidacao = $user ? $user['senha'] : self::DUMMY_HASH;
        $ok = password_verify($senha, $hashValidacao);
        
        if (!$user || !$ok) {
            return null;
        }

        unset($user['senha']);
        return $user;
    }

    public function findByIdComSenha(int $id): ?array
    {
        $query = "SELECT id, nome, email, senha, role
                  FROM {$this->table}
                  WHERE id = :id
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    public function create(string $nome, string $email, string $senha, string $role = 'aluno'): int
    {
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $emailVerificado = $role === 'admin' ? 'NOW()' : 'NULL';

        $sql = "INSERT INTO {$this->table} (nome, email, senha, role, email_verificado_em)
                VALUES (:nome, :email, :senha, :role, {$emailVerificado})
                RETURNING id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nome' => $nome,
            'email' => $email,
            'senha' => $hash,
            'role' => $role,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function updatePendingRegistration(int $id, string $nome, string $email, string $senha): void
    {
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->db->prepare("UPDATE {$this->table}
                                    SET nome = :nome,
                                        email = :email,
                                        senha = :senha,
                                        role = 'aluno',
                                        email_verificado_em = NULL
                                    WHERE id = :id
                                      AND email_verificado_em IS NULL");
        $stmt->execute([
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'senha' => $hash,
        ]);
    }

    public function upsertAlunoMatricula(int $usuarioId, string $matricula): void
    {
        $stmt = $this->db->prepare("INSERT INTO aluno (aluno_id, matricula)
                                    VALUES (:aluno_id, :matricula)
                                    ON CONFLICT (aluno_id) DO UPDATE
                                    SET matricula = EXCLUDED.matricula");
        $stmt->execute([
            'aluno_id' => $usuarioId,
            'matricula' => $matricula,
        ]);
    }

    public function matriculaEmUsoPorContaVerificada(string $matricula): bool
    {
        $stmt = $this->db->prepare("SELECT 1
                                    FROM aluno a
                                    INNER JOIN usuario u ON u.id = a.aluno_id
                                    WHERE a.matricula = :matricula
                                      AND u.email_verificado_em IS NOT NULL
                                    LIMIT 1");
        $stmt->execute(['matricula' => $matricula]);
        return (bool) $stmt->fetchColumn();
    }

    public function removerContasNaoVerificadasPorMatricula(string $matricula, ?int $excetoUsuarioId = null): void
    {
        $sql = "DELETE FROM {$this->table} u
                USING aluno a
                WHERE a.aluno_id = u.id
                  AND a.matricula = :matricula
                  AND u.email_verificado_em IS NULL";

        $params = ['matricula' => $matricula];

        if ($excetoUsuarioId !== null) {
            $sql .= " AND u.id <> :exceto_usuario_id";
            $params['exceto_usuario_id'] = $excetoUsuarioId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function gerarCodigoVerificacao(int $id): string
    {
        $codigo = (string) random_int(100000, 999999);
        $hash = $this->hashCodigo($codigo);

        $this->invalidarTokensAtivos($id, self::TOKEN_TYPE_ACTIVATION);

        $sql = "INSERT INTO token_autenticacao (usuario_id, token_hash, tipo, expira_em)
                VALUES (:usuario_id, :token_hash, :tipo, NOW() + (:ttl || ' minutes')::interval)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'usuario_id' => $id,
            'token_hash' => $hash,
            'tipo' => self::TOKEN_TYPE_ACTIVATION,
            'ttl' => self::EMAIL_CODE_TTL_MINUTES,
        ]);

        return $codigo;
    }

    public function possuiCodigoAtivacaoValido(int $id): bool
    {
        $token = $this->findTokenAtivo($id, self::TOKEN_TYPE_ACTIVATION);
        if (!$token) {
            return false;
        }

        return (int) $token['tentativas'] < self::EMAIL_CODE_MAX_ATTEMPTS
            && strtotime($token['expira_em']) >= time();
    }

    public function verificarCodigoEmail(string $email, string $codigo): array
    {
        $codigo = preg_replace('/\D+/', '', $codigo);
        if (!preg_match('/^\d{6}$/', $codigo)) {
            return ['ok' => false, 'error' => 'Código inválido. Informe os 6 dígitos recebidos por email.'];
        }

        $user = $this->findByEmail($email);
        if (!$user) {
            return ['ok' => false, 'error' => 'Código inválido ou expirado.'];
        }

        if (!empty($user['email_verificado_em'])) {
            return ['ok' => true, 'already_verified' => true];
        }

        $token = $this->findTokenAtivo((int) $user['id'], self::TOKEN_TYPE_ACTIVATION);
        if (!$token) {
            return ['ok' => false, 'error' => 'Solicite um novo código de ativação.'];
        }

        if ((int) $token['tentativas'] >= self::EMAIL_CODE_MAX_ATTEMPTS) {
            return ['ok' => false, 'error' => 'Muitas tentativas. Solicite um novo código de ativação.'];
        }

        if (strtotime($token['expira_em']) < time()) {
            return ['ok' => false, 'error' => 'Código expirado. Solicite um novo código de ativação.'];
        }

        if (!hash_equals($token['token_hash'], $this->hashCodigo($codigo))) {
            $this->incrementarTentativaToken((int) $token['id']);
            return ['ok' => false, 'error' => 'Código inválido ou expirado.'];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE token_autenticacao
                                        SET usado_em = NOW()
                                        WHERE id = :id");
            $stmt->execute(['id' => (int) $token['id']]);

            $stmt = $this->db->prepare("UPDATE {$this->table}
                                        SET email_verificado_em = NOW()
                                        WHERE id = :id");
            $stmt->execute(['id' => (int) $user['id']]);

            $this->db->commit();
            return ['ok' => true, 'usuario_id' => (int) $user['id']];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('Usuario::verificarCodigoEmail — ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Não foi possível ativar a conta agora. Tente novamente.'];
        }
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT id, nome, email, role, email_verificado_em
                                    FROM {$this->table}
                                    WHERE email = :email
                                    LIMIT 1");
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    private function findTokenAtivo(int $usuarioId, string $tipo): ?array
    {
        $stmt = $this->db->prepare("SELECT id, token_hash, tentativas, expira_em
                                    FROM token_autenticacao
                                    WHERE usuario_id = :usuario_id
                                      AND tipo = :tipo
                                      AND usado_em IS NULL
                                    ORDER BY criado_em DESC
                                    LIMIT 1");
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    private function invalidarTokensAtivos(int $usuarioId, string $tipo): void
    {
        $stmt = $this->db->prepare("UPDATE token_autenticacao
                                    SET usado_em = NOW()
                                    WHERE usuario_id = :usuario_id
                                      AND tipo = :tipo
                                      AND usado_em IS NULL");
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
        ]);
    }

    private function incrementarTentativaToken(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE token_autenticacao
                                    SET tentativas = tentativas + 1
                                    WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private function hashCodigo(string $codigo): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (strlen($secret) < 32) {
            throw new \RuntimeException('Configuração de autenticação inválida no servidor.');
        }

        return hash_hmac('sha256', $codigo, $secret);
    }

    public function update(int $id, string $nome, string $email, string $role): bool
    {
        $sql = "UPDATE {$this->table}
                SET nome = :nome, email = :email, role = :role
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'role' => $role,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updatePassword(int $id, string $nova_senha): bool
    {
        $hash = password_hash($nova_senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $sql = "UPDATE {$this->table} SET senha = :senha WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'senha' => $hash]);
    }

    private function buildWhereClause(array $filtros): array
    {
        $where = [];
        $binds = [];

        if (!empty($filtros['role'])) {
            $where[] = "role = :role";
            $binds[':role'] = $filtros['role'];
        }

        if (!empty($filtros['busca'])) {
            $where[] = "(nome ILIKE :busca OR email ILIKE :busca)";
            $binds[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $sqlWhere = count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '';

        return ['sql' => $sqlWhere, 'binds' => $binds];
    }

    public function countFiltered(array $filtros = []): int
    {
        $whereData = $this->buildWhereClause($filtros);
        $sql = "SELECT COUNT(id) FROM {$this->table}" . $whereData['sql'];
        $stmt = $this->db->prepare($sql);
        $stmt->execute($whereData['binds']);
        return (int) $stmt->fetchColumn();
    }

    public function findAll(int $limit = 20, int $offset = 0, array $filtros = []): array
    {
        $whereData = $this->buildWhereClause($filtros);

        $sql = "SELECT id, nome, email, role
                FROM {$this->table}"
            . $whereData['sql']
            . " ORDER BY nome ASC
               LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($whereData['binds'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
