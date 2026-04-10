<?php
//lib/helpers.php

/**
 * Convierte respuesta cruda de la API MikroTik
 * en array asociativo. Reutilizable en todas las páginas.
 */
function parseResponse(array $raw): array {
    $entries = [];
    $current = [];

    foreach ($raw as $word) {
        if ($word === '!re') {
            if (!empty($current)) $entries[] = $current;
            $current = [];
        } elseif (str_starts_with($word, '=')) {
            $word = ltrim($word, '=');
            $pos  = strpos($word, '=');
            if ($pos !== false) {
                $current[substr($word, 0, $pos)] = substr($word, $pos + 1);
            }
        }
    }

    if (!empty($current)) $entries[] = $current;
    return $entries;
}

function requireLogin(){
    if (!isUserLoggedIn()){
        header('Location:
        login.php');
        exit;
    }
}
/**
 * Escribe una línea en el log de auditoría
 */
function auditLog(string $level, string $message): void {
    $logDir  = __DIR__ . '/../logs';
    $logFile = $logDir . '/audit.log';

    if (!is_dir($logDir)) mkdir($logDir, 0755, true);

    $line = date('Y-m-d H:i:s') . ' | ' . strtoupper($level) . ' | ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}