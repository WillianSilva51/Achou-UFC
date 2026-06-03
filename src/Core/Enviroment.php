<?php

namespace Core;

class Enviroment
{
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        $linhas = [];
        $linhas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($linha as $linhas) {
            $linha = trim($linha);

            if (str_starts_with($linha, '#')) {
                continue;
            }
        }
    }
}
