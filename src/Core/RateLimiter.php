<?php

namespace Core;

/**
 * CORREÇÃO ALTA-01: Rate Limiter baseado em banco de dados PostgreSQL.
 *
 * O código original usava arquivos em sys_get_temp_dir(). Problemas:
 *   1. Não funciona em múltiplos servidores (cada um tem seu /tmp próprio)
 *   2. /tmp é limpo pelo SO, zerando contadores
 *   3. Race conditions em sistemas com muitos processos PHP simultâneos
 *   4. Bypass fácil com IPs rotativos (sem proteção por email/usuário)
 *
 * Solução: tabela rate_limit no PostgreSQL com INSERT ... ON CONFLICT (upsert).
 * O banco garante atomicidade nativa sem necessidade de lock de arquivo.
 *
 * SQL para criar a tabela (execute uma vez no banco):
 * ─────────────────────────────────────────────────
 * CREATE TABLE IF NOT EXISTS public.rate_limit (
 *     chave       VARCHAR(64)  NOT NULL,
 *     tentativas  INT          NOT NULL DEFAULT 1,
 *     expira_em   TIMESTAMPTZ  NOT NULL,
 *     CONSTRAINT rate_limit_pkey PRIMARY KEY (chave)
 * );
 * CREATE INDEX IF NOT EXISTS idx_rate_limit_expira ON public.rate_limit (expira_em);
 * ─────────────────────────────────────────────────
 *
 * Para limpeza automática de entradas expiradas, crie um job ou pg_cron:
 *   DELETE FROM rate_limit WHERE expira_em < NOW();
 */
class RateLimiter
{
    /**
     * Verifica e incrementa o contador de tentativas para a chave ip+action.
     * Se o limite for excedido, responde com HTTP 429 e encerra a execução.
     *
     * @param string $ip           IP do cliente
     * @param string $action       Ação sendo limitada (ex: 'login', 'register')
     * @param int    $maxAttempts  Máximo de tentativas permitidas na janela
     * @param int    $timeoutSecs  Janela de tempo em segundos
     */
    public static function check(string $ip, string $action, int $maxAttempts = 5, int $timeoutSecs = 60): void
    {
        try {
            $pdo  = Database::getConnection();
            $chave = hash('sha256', $ip . '|' . $action); // chave determinística e segura

            /*
             * Upsert atômico no PostgreSQL:
             *   - Se não existe: insere com tentativas = 1 e expira_em = agora + janela
             *   - Se existe E ainda não expirou: incrementa tentativas
             *   - Se existe MAS já expirou: reinicia (tentativas = 1, nova janela)
             * Retorna o número atual de tentativas para a decisão de bloqueio.
             */
            $sql = "INSERT INTO rate_limit (chave, tentativas, expira_em)
                    VALUES (:chave, 1, NOW() + (:secs || ' seconds')::INTERVAL)
                    ON CONFLICT (chave) DO UPDATE
                        SET tentativas = CASE
                                WHEN rate_limit.expira_em < NOW()
                                    THEN 1                          -- janela expirou: reinicia
                                ELSE rate_limit.tentativas + 1      -- dentro da janela: incrementa
                            END,
                            expira_em = CASE
                                WHEN rate_limit.expira_em < NOW()
                                    THEN NOW() + (:secs2 || ' seconds')::INTERVAL
                                ELSE rate_limit.expira_em           -- mantém a janela original
                            END
                    RETURNING tentativas";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'chave'  => $chave,
                'secs'   => $timeoutSecs,
                'secs2'  => $timeoutSecs,
            ]);

            $tentativas = (int) $stmt->fetchColumn();

            if ($tentativas > $maxAttempts) {
                header('Content-Type: application/json');
                http_response_code(429);
                // Não revela quantas tentativas restam para não facilitar timing attacks
                echo json_encode([
                    'error'       => "Muitas tentativas. Aguarde {$timeoutSecs} segundos e tente novamente.",
                    'retry_after' => $timeoutSecs,
                ]);
                exit;
            }

        } catch (\PDOException $e) {
            // Se o banco estiver fora, loga mas não bloqueia o usuário legítimo
            // (fail-open é preferível a derrubar o serviço por falha de rate limit)
            error_log('RateLimiter: falha ao acessar banco — ' . $e->getMessage());
        }
    }

    /**
     * Reseta manualmente o contador de uma combinação ip+action.
     * Útil para testes automatizados ou reset administrativo.
     */
    public static function reset(string $ip, string $action): void
    {
        try {
            $pdo   = Database::getConnection();
            $chave = hash('sha256', $ip . '|' . $action);
            $stmt  = $pdo->prepare("DELETE FROM rate_limit WHERE chave = :chave");
            $stmt->execute(['chave' => $chave]);
        } catch (\PDOException $e) {
            error_log('RateLimiter::reset falhou — ' . $e->getMessage());
        }
    }
}
