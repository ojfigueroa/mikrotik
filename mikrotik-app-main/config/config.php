<?php
// config/config.php
// NUNCA hardcodear credenciales en el código

define('MIKROTIK_HOST', getenv('MKT_HOST') ?: '192.168.88.1');
define('MIKROTIK_PORT', getenv('MKT_PORT') ?: 8728);
define('MIKROTIK_USER', getenv('MKT_USER') ?: 'alumno');
define('MIKROTIK_PASS', getenv('MKT_PASS') ?: 'mk2026');
define('MIKROTIK_TIMEOUT', 5); // segundos

