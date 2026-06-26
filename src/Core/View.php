<?php

namespace Core;

class View
{ /// nem tamo usando
    public static function render(string $view, array $data = []): string
    {
        $viewSafe = basename($view);

        $path = BASE_PATH . '/src/view/' . $viewSafe . '.html';
        if (!file_exists($path)) {
            http_response_code(404);
            return "Error: View '$viewSafe' not found";
        }
        $content = file_get_contents($path);
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }
}