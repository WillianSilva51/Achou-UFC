<?php

namespace Core;


class Recaptcha
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Verifica o token reCAPTCHA recebido do cliente.
     *
     * @param string $token    Token enviado pelo frontend (campo "g-recaptcha-response" ou "recaptcha_token")
     * @param float  $minScore Score mínimo aceitável (somente reCAPTCHA v3; v2 ignora este parâmetro)
     *
     * @return bool  true = humano verificado, false = bot ou token inválido
     *
     * @throws \RuntimeException se a secret key não estiver configurada no .env
     */
    public static function verify(string $token, ?string $expectedAction = null, ?float $minScore = null): bool
    {
        $secretKey = $_ENV['RECAPTCHA_SECRET_KEY'] ?? '';

        if (empty($secretKey)) {
            // Bypass para desenvolvimento local: se a chave não estiver configurada,
            // loga um aviso e retorna true sem bloquear apenas em ambiente local.
            // Em produção, preencha RECAPTCHA_SECRET_KEY no .env.
            if (self::isLocalRequest()) {
                error_log('Recaptcha: RECAPTCHA_SECRET_KEY ausente - bypass ativado somente para desenvolvimento local.');
                return true;
            }

            error_log('Recaptcha: RECAPTCHA_SECRET_KEY ausente em ambiente nao local.');
            throw new \RuntimeException('RECAPTCHA_SECRET_KEY nao configurada.');
        }

        if (empty(trim($token))) {
            return false;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => $secretKey,
                'response' => $token,
                'remoteip' => $ip,
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $responseRaw = curl_exec($ch);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($responseRaw === false || !empty($curlError)) {
            error_log('Recaptcha: falha na chamada cURL - ' . $curlError);
            return false;
        }

        $response = json_decode($responseRaw, true);
        if (!is_array($response)) {
            error_log('Recaptcha: resposta invalida recebida do Google.');
            return false;
        }

        if (!isset($response['success']) || $response['success'] !== true) {
            $errorCodes = $response['error-codes'] ?? [];
            error_log('Recaptcha: verificacao falhou - ' . implode(', ', $errorCodes));
            return false;
        }

        if (!self::hostnameAllowed($response['hostname'] ?? '')) {
            error_log('Recaptcha: hostname nao permitido - ' . ($response['hostname'] ?? 'ausente'));
            return false;
        }

        if ($expectedAction !== null) {
            $action = $response['action'] ?? '';
            if ($action !== $expectedAction) {
                error_log("Recaptcha: action inesperada ({$action}); esperado {$expectedAction}");
                return false;
            }
        }

        // reCAPTCHA v3 retorna um "score" entre 0.0 (bot) e 1.0 (humano)
        // reCAPTCHA v2 não retorna score; a verificação de success já é suficiente
        if (isset($response['score'])) {
            $score = (float) $response['score'];
            $minimumScore = $minScore ?? self::minimumScore();
            if ($score < $minimumScore) {
                error_log("Recaptcha: score baixo ({$score}) para IP {$ip}");
                return false;
            }
        }

        return true;
    }

    private static function minimumScore(): float
    {
        $configured = $_ENV['RECAPTCHA_MIN_SCORE'] ?? '0.5';
        $score = filter_var($configured, FILTER_VALIDATE_FLOAT);

        if ($score === false || $score < 0 || $score > 1) {
            return 0.5;
        }

        return (float) $score;
    }

    private static function hostnameAllowed(string $hostname): bool
    {
        $hostname = strtolower(trim($hostname));
        if ($hostname === '') {
            return false;
        }

        $allowedHosts = array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', $_ENV['RECAPTCHA_ALLOWED_HOSTS'] ?? '')
        ));

        if (empty($allowedHosts)) {
            $allowedHosts = array_filter([
                strtolower($_SERVER['HTTP_HOST'] ?? ''),
                'localhost',
                '127.0.0.1',
            ]);
        }

        foreach ($allowedHosts as $allowedHost) {
            $allowedHost = preg_replace('/:\d+$/', '', $allowedHost);
            if ($hostname === $allowedHost) {
                return true;
            }
        }

        return false;
    }

    private static function isLocalRequest(): bool
    {
        $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        return str_starts_with($host, 'localhost')
            || str_starts_with($host, '127.0.0.1')
            || $host === '[::1]'
            || in_array($remoteAddress, ['127.0.0.1', '::1'], true);
    }
}
