<?php

namespace Core;

class View
{
    public static function render(string $view, array $data = []): string
    {
        $path = BASE_PATH . '/src/view/' . $view . '.html';
        if (!file_exists($path)) {
            http_response_code(404);
            return "Error: View '$view' not found";
        }
        $content = file_get_contents($path);
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        } return $content;
    }
}

