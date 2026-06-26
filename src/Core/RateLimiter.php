<?php

namespace Core;

class RateLimiter
{
    public static function check(string $ip, string $action, int $maxAttempts = 5, int $timeoutSecs = 60): void
    {
        $file = sys_get_temp_dir() . '/rate_limit_' . md5($ip . $action) . '.json';
        
        $fp = fopen($file, 'c+');

        if (!$fp) {
            return; 
        }

        if (flock($fp, LOCK_EX)) {
            $filesize = filesize($file);
            $data = ['attempts' => 0, 'time' => time()];

            if ($filesize > 0) {
                $json = fread($fp, $filesize);
                $parsed = json_decode($json, true);
                if (is_array($parsed)) {
                    $data = $parsed;
                }
            }

            if (time() - $data['time'] > $timeoutSecs) {
                $data = ['attempts' => 1, 'time' => time()];
            } else {
                $data['attempts']++;
                
                if ($data['attempts'] > $maxAttempts) {
                    flock($fp, LOCK_UN); 
                    fclose($fp);        
                    
                    header('Content-Type: application/json');
                    http_response_code(429); 
                    echo json_encode(['error' => "Muitas tentativas. Tente novamente em $timeoutSecs segundos."]);
                    exit;
                }
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));

            flock($fp, LOCK_UN);
        }
        
        fclose($fp);
    }
}