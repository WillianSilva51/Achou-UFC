<?php

namespace Core;

use RuntimeException;

class SupabaseStorage
{
    public static function upload(string $conteudoBinario, string $nomeArquivo, string $mimeType): string
    {
        $baseUrl    = rtrim($_ENV['SUPABASE_URL'] ?? '', '/');
        $serviceKey = $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? '';
        $bucket     = $_ENV['SUPABASE_STORAGE_BUCKET'] ?? 'fotos-itens';

        if (!$baseUrl || !$serviceKey) {
            throw new RuntimeException('Credenciais do Supabase Storage não configuradas no .env.');
        }

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
                'x-upsert: true',
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
            throw new RuntimeException('Falha ao enviar a imagem para o Supabase Storage.');
        }

        return "{$baseUrl}/storage/v1/object/public/{$bucket}/{$caminho}";
    }
}