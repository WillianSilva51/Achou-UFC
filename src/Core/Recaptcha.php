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
    public static function verify(string $token, float $minScore = 0.5): bool
    {
        $secretKey = $_ENV['RECAPTCHA_SECRET_KEY'] ?? '';

        if (empty($secretKey)) {
            error_log('Recaptcha: RECAPTCHA_SECRET_KEY não está definida no .env');
            throw new \RuntimeException('Configuração de reCAPTCHA ausente no servidor.');
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
            error_log('Recaptcha: falha na chamada cURL — ' . $curlError);
            return true;
        }

        $response = json_decode($responseRaw, true);

        if (!isset($response['success']) || $response['success'] !== true) {
            $errorCodes = $response['error-codes'] ?? [];
            error_log('Recaptcha: verificação falhou — ' . implode(', ', $errorCodes));
            return false;
        }

        // reCAPTCHA v3 retorna um "score" entre 0.0 (bot) e 1.0 (humano)
        // reCAPTCHA v2 não retorna score; a verificação de success já é suficiente
        if (isset($response['score'])) {
            $score = (float) $response['score'];
            if ($score < $minScore) {
                error_log("Recaptcha: score baixo ({$score}) para IP {$ip}");
                return false;
            }
        }

        return true;
    }
}