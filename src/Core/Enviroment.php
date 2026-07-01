<?php

namespace Core;

use Exception;

class Environment
{
    /**
     * Carrega as variáveis do arquivo .env para o ambiente global do PHP
     */
    public static function load(string $dir): void
    {
        $path = $dir . '/.env';

        // Se o .env não existir, o sistema quebra intencionalmente avisando o desenvolvedor
        if (!file_exists($path)) {
            throw new Exception("Arquivo .env não encontrado. Crie um baseado no .env.example.");
        }

        // Lê o arquivo pulando linhas vazias
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignora linhas que são comentários
            if (strpos($line, '#') === 0) {
                continue;
            }

            // Verifica se a linha possui o caractere '=' separando chave e valor
            if (strpos($line, '=') !== false) {
                // Divide apenas no primeiro '=', preservando '=' que possam estar na senha do banco
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);

                if (getenv($key) !== false || array_key_exists($key, $_ENV)) {
                    continue;
                }

                // Remove aspas duplas ou simples que envolvam o valor
                $value = trim($value, '"\'');

                // putenv + $_ENV são suficientes; $_SERVER não recebe segredos
                putenv(sprintf('%s=%s', $key, $value));
                $_ENV[$key] = $value;
            }
        }
    }
}
