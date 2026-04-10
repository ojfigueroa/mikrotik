<?php
// test_connection.php - Solo para pruebas, no va en producción

require_once 'config/config.php';
require_once 'lib/RouterOSAPI.php';

$api = new RouterOSAPI();

try {
    echo "Intentando conectar a " . MIKROTIK_HOST . "...\n";
    
    $api->connect(MIKROTIK_HOST, MIKROTIK_PORT, MIKROTIK_TIMEOUT);
    echo "✅ Conexión establecida.\n";

    $api->login(MIKROTIK_USER, MIKROTIK_PASS);
    echo "✅ Autenticación exitosa. ¡Fase 1 completada!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    $api->disconnect();
    echo "🔌 Conexión cerrada correctamente.\n";
}