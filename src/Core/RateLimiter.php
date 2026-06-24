<?php

namespace Core;

class RateLimiter
{
    public static function check(string $ip, string $action, int $maxAttempts = 5, int $timeoutSecs = 60): void
    {
        $file = sys_get_temp_dir() . '/rate_limit_' . md5($ip . $action) . '.json';
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            
            if (time() - $data['time'] > $timeoutSecs) {
                $data = ['attempts' => 1, 'time' => time()];
            } else {
                $data['attempts']++;
                if ($data['attempts'] > $maxAttempts) {
                    header('Content-Type: application/json');
                    http_response_code(429); 
                    echo json_encode(['error' => "Muitas tentativas. Tente novamente em $timeoutSecs segundos."]);
                    exit; 
                }
            }
        } else {
            $data = ['attempts' => 1, 'time' => time()];
        }
        
        file_put_contents($file, json_encode($data));
    }
}