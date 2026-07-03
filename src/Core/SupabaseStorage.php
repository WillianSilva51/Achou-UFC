<?php

namespace Core;

use RuntimeException;

class SupabaseStorage
{
    public static function upload(string $conteudoBinario, string $nomeArquivo, string $mimeType): string
    {
        $baseUrl    = rtrim(self::env('SUPABASE_URL'), '/');
        $serviceKey = self::env('SUPABASE_SERVICE_ROLE_KEY') ?: self::env('SUPABASE_SECRET_KEY');
        $bucket     = self::env('SUPABASE_STORAGE_BUCKET') ?: 'fotos-itens';

        if (!$baseUrl || !$serviceKey) {
            throw new RuntimeException('Credenciais do Supabase Storage não configuradas no .env.');
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('SUPABASE_URL inválida no .env.');
        }

        self::validarChaveServidor($serviceKey);

        $caminho = 'itens/' . $nomeArquivo;
        $url     = "{$baseUrl}/storage/v1/object/{$bucket}/{$caminho}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $serviceKey,
                'apikey: ' . $serviceKey,
                'Content-Type: ' . $mimeType,
                'x-upsert: false',
            ],
            CURLOPT_POSTFIELDS => $conteudoBinario,
            CURLOPT_TIMEOUT    => 30,
        ]);

        $resposta = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            throw new RuntimeException('Falha ao contactar o Supabase Storage: ' . $erroCurl);
        }

        if ($status < 200 || $status >= 300) {
            error_log('SupabaseStorage::upload — resposta inesperada (' . $status . '): ' . $resposta);
            throw new RuntimeException(self::mensagemErroUpload($status, $resposta));
        }

        return "{$baseUrl}/storage/v1/object/public/{$bucket}/{$caminho}";
    }

    private static function env(string $key): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_string($value) ? trim($value) : '';
    }

    private static function validarChaveServidor(string $key): void
    {
        if (str_starts_with($key, 'sb_publish')) {
            throw new RuntimeException('Configure SUPABASE_SERVICE_ROLE_KEY com a chave service_role/sb_secret do Supabase. A chave publishable não pode enviar arquivos para o Storage.');
        }

        $partes = explode('.', $key);
        if (count($partes) >= 2) {
            $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')) ?: '', true);
            $role = is_array($payload) ? ($payload['role'] ?? '') : '';

            if ($role !== '' && $role !== 'service_role') {
                throw new RuntimeException('Configure SUPABASE_SERVICE_ROLE_KEY com a chave service_role do Supabase.');
            }
        }
    }

    private static function mensagemErroUpload(int $status, string $resposta): string
    {
        $payload = json_decode($resposta, true);
        $mensagem = is_array($payload) ? strtolower((string) ($payload['message'] ?? $payload['error'] ?? '')) : '';

        if ($status === 401 || $status === 403 || str_contains($mensagem, 'row-level security')) {
            return 'Supabase Storage recusou o upload. Verifique se SUPABASE_SERVICE_ROLE_KEY é a chave service_role/sb_secret e se o bucket permite escrita pelo servidor.';
        }

        return 'Falha ao enviar a imagem para o Supabase Storage.';
    }
}
