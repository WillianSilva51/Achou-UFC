<?php

namespace Models;

use Models\BaseModel;
use PDO;

class Usuario extends BaseModel
{
    protected string $table = 'usuario';
    private const EMAIL_CODE_TTL_MINUTES = 15;
    private const EMAIL_CODE_MAX_ATTEMPTS = 5;

    public function __construct()
    {
        parent::__construct();
        $this->ensureEmailVerificationSchema();
    }

    private function ensureEmailVerificationSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS email_verificado_em TIMESTAMPTZ");
        $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS codigo_verificacao_hash VARCHAR(128)");
        $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS codigo_verificacao_expira_em TIMESTAMPTZ");
        $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS codigo_verificacao_tentativas INT NOT NULL DEFAULT 0");
        $this->db->exec("UPDATE {$this->table}
                         SET email_verificado_em = COALESCE(email_verificado_em, criado_em)
                         WHERE email_verificado_em IS NULL
                           AND codigo_verificacao_hash IS NULL");
        $checked = true;
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

        if (!$user || !password_verify($senha, $user['senha'])) {
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
            'nome'  => $nome,
            'email' => $email,
            'senha' => $hash,
            'role'  => $role,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function gerarCodigoVerificacao(int $id): string
    {
        $codigo = (string) random_int(100000, 999999);
        $hash = $this->hashCodigo($codigo);

        $sql = "UPDATE {$this->table}
                SET codigo_verificacao_hash = :hash,
                    codigo_verificacao_expira_em = NOW() + (:ttl || ' minutes')::interval,
                    codigo_verificacao_tentativas = 0
                WHERE id = :id AND email_verificado_em IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'hash' => $hash,
            'ttl' => self::EMAIL_CODE_TTL_MINUTES,
        ]);

        return $codigo;
    }

    public function verificarCodigoEmail(string $email, string $codigo): array
    {
        $codigo = preg_replace('/\D+/', '', $codigo);
        if (!preg_match('/^\d{6}$/', $codigo)) {
            return ['ok' => false, 'error' => 'Código inválido. Informe os 6 dígitos recebidos por email.'];
        }

        $stmt = $this->db->prepare("SELECT id, email_verificado_em, codigo_verificacao_hash,
                                           codigo_verificacao_expira_em, codigo_verificacao_tentativas
                                    FROM {$this->table}
                                    WHERE email = :email
                                    LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['ok' => false, 'error' => 'Código inválido ou expirado.'];
        }

        if (!empty($user['email_verificado_em'])) {
            return ['ok' => true, 'already_verified' => true];
        }

        if (empty($user['codigo_verificacao_hash']) || empty($user['codigo_verificacao_expira_em'])) {
            return ['ok' => false, 'error' => 'Solicite um novo código de ativação.'];
        }

        if ((int) $user['codigo_verificacao_tentativas'] >= self::EMAIL_CODE_MAX_ATTEMPTS) {
            return ['ok' => false, 'error' => 'Muitas tentativas. Solicite um novo código de ativação.'];
        }

        if (strtotime($user['codigo_verificacao_expira_em']) < time()) {
            return ['ok' => false, 'error' => 'Código expirado. Solicite um novo código de ativação.'];
        }

        $hashInformado = $this->hashCodigo($codigo);
        if (!hash_equals($user['codigo_verificacao_hash'], $hashInformado)) {
            $this->incrementarTentativaCodigo((int) $user['id']);
            return ['ok' => false, 'error' => 'Código inválido ou expirado.'];
        }

        $sql = "UPDATE {$this->table}
                SET email_verificado_em = NOW(),
                    codigo_verificacao_hash = NULL,
                    codigo_verificacao_expira_em = NULL,
                    codigo_verificacao_tentativas = 0
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => (int) $user['id']]);

        return ['ok' => true];
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

    private function incrementarTentativaCodigo(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
                                    SET codigo_verificacao_tentativas = codigo_verificacao_tentativas + 1
                                    WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private function hashCodigo(string $codigo): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        return hash_hmac('sha256', $codigo, $secret);
    }

    public function update(int $id, string $nome, string $email, string $role): bool
    {
        $sql = "UPDATE {$this->table}
                SET nome = :nome, email = :email, role = :role
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'    => $id,
            'nome'  => $nome,
            'email' => $email,
            'role'  => $role,
        ]);
        return $stmt->rowCount() > 0;
    }


    public function updatePassword(int $id, string $nova_senha): bool
    {
        $hash = password_hash($nova_senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $sql  = "UPDATE {$this->table} SET senha = :senha WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'senha' => $hash]);
    }

    private function buildWhereClause(array $filtros): array
    {
        $where = [];
        $binds = [];

        if (!empty($filtros['role'])) {
            $where[]         = "role = :role";
            $binds[':role']  = $filtros['role'];
        }

        if (!empty($filtros['busca'])) {
            $where[]          = "(nome ILIKE :busca OR email ILIKE :busca)";
            $binds[':busca']  = '%' . $filtros['busca'] . '%';
        }

        $sqlWhere = count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '';

        return ['sql' => $sqlWhere, 'binds' => $binds];
    }

    public function countFiltered(array $filtros = []): int
    {
        $whereData = $this->buildWhereClause($filtros);
        $sql       = "SELECT COUNT(id) FROM {$this->table}" . $whereData['sql'];
        $stmt      = $this->db->prepare($sql);
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

        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
