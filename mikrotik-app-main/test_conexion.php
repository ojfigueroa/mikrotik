<?php
// test_connection.php
// Colócalo en la raíz: C:\xampp\htdocs\mikrotik-app\test_connection.php
// Ábrelo en: http://localhost/mikrotik-app/test_connection.php

require_once 'config/config.php';
require_once 'lib/RouterOSAPI.php';

// Estilos inline para que se vea bien en el navegador
echo '<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Test de Conexión — MikroManager</title>
  <style>
    body { background: #0e1117; color: #e2e8f5; font-family: monospace;
           font-size: 14px; padding: 40px; }
    h2   { color: #4d9fff; margin-bottom: 24px; }
    .ok  { color: #00e5a0; }
    .err { color: #ff5b5b; }
    .inf { color: #4d9fff; }
    .box { background: #161b24; border: 1px solid #2a3347;
           border-radius: 8px; padding: 24px; max-width: 600px; }
    .row { padding: 8px 0; border-bottom: 1px solid #2a3347; }
    .row:last-child { border-bottom: none; }
    .label { color: #4a5568; font-size: 12px; margin-bottom: 4px; }
    .val   { font-size: 13px; }
    .btn   { display: inline-block; margin-top: 24px; padding: 10px 20px;
             background: #1e2533; border: 1px solid #334060; border-radius: 6px;
             color: #4d9fff; text-decoration: none; font-family: monospace; }
    .btn:hover { border-color: #4d9fff; }
  </style>
</head>
<body>
<h2>Test de Conexión MikroTik API</h2>
<div class="box">';

$api    = new RouterOSAPI();
$pasos  = [];
$ok     = true;

// ── Paso 1: Conexión TCP ────────────────────────────────────────────────────
echo '<div class="row">';
echo '<div class="label">Paso 1 — Conexión TCP</div>';

try {
    $api->connect(MIKROTIK_HOST, MIKROTIK_PORT, MIKROTIK_TIMEOUT);
    echo '<div class="val ok">✅ Conectado a ' . MIKROTIK_HOST . ':' . MIKROTIK_PORT . '</div>';
} catch (Exception $e) {
    echo '<div class="val err">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<div class="val err" style="margin-top:6px;font-size:12px">
            Verifica que el router esté encendido y la API habilitada:<br>
            <code>/ip service enable api</code>
          </div>';
    $ok = false;
}
echo '</div>';

// ── Paso 2: Autenticación ───────────────────────────────────────────────────
echo '<div class="row">';
echo '<div class="label">Paso 2 — Autenticación</div>';

if ($ok) {
    try {
        $api->login(MIKROTIK_USER, MIKROTIK_PASS);
        echo '<div class="val ok">✅ Autenticado como: ' . MIKROTIK_USER . '</div>';
    } catch (Exception $e) {
        echo '<div class="val err">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '<div class="val err" style="margin-top:6px;font-size:12px">
                Verifica usuario y contraseña en config.php<br>
                O crea el usuario: <code>/user add name=api_user password=ApiPass2025! group=full</code>
              </div>';
        $ok = false;
    }
} else {
    echo '<div class="val inf">— Omitido (fallo en paso anterior)</div>';
}
echo '</div>';

// ── Paso 3: Leer recursos del sistema ──────────────────────────────────────
echo '<div class="row">';
echo '<div class="label">Paso 3 — Lectura de datos (Fase 2)</div>';

if ($ok) {
    try {
        $raw = $api->communicate(['/system/resource/print']);

        // Parsear respuesta
        $res     = [];
        $current = [];
        foreach ($raw as $word) {
            if ($word === '!re') {
                if (!empty($current)) $res[] = $current;
                $current = [];
            } elseif (str_starts_with($word, '=')) {
                $word = ltrim($word, '=');
                $pos  = strpos($word, '=');
                if ($pos !== false) {
                    $current[substr($word, 0, $pos)] = substr($word, $pos + 1);
                }
            }
        }
        if (!empty($current)) $res[] = $current;
        $data = $res[0] ?? [];

        echo '<div class="val ok">✅ Datos del router obtenidos correctamente</div>';
        echo '<table style="margin-top:12px;width:100%;border-collapse:collapse">';

        $campos = [
            'board-name'     => 'Modelo',
            'version'        => 'RouterOS',
            'cpu-load'       => 'CPU Load',
            'total-memory'   => 'RAM Total',
            'free-memory'    => 'RAM Libre',
            'uptime'         => 'Uptime',
            'architecture-name' => 'Arquitectura',
        ];

        foreach ($campos as $key => $label) {
            $val = $data[$key] ?? '—';
            // Convertir bytes a MB para RAM
            if (in_array($key, ['total-memory', 'free-memory'])) {
                $val = round((int)$val / 1048576, 1) . ' MB';
            }
            echo '<tr style="border-bottom:1px solid #2a3347">
                    <td style="padding:6px 0;color:#4a5568;font-size:12px;width:140px">' . $label . '</td>
                    <td style="padding:6px 0;color:#e2e8f5;font-size:12px">' . htmlspecialchars($val) . '</td>
                  </tr>';
        }
        echo '</table>';

    } catch (Exception $e) {
        echo '<div class="val err">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
        $ok = false;
    }
} else {
    echo '<div class="val inf">— Omitido (fallo en paso anterior)</div>';
}
echo '</div>';

// ── Paso 4: Cierre de conexión ──────────────────────────────────────────────
echo '<div class="row">';
echo '<div class="label">Paso 4 — Cierre de conexión</div>';
$api->disconnect();
echo '<div class="val ok">✅ Conexión cerrada correctamente</div>';
echo '</div>';

// ── Resultado final ─────────────────────────────────────────────────────────
echo '<div style="margin-top:20px;padding:12px;border-radius:6px;text-align:center;background:' . ($ok ? 'rgba(0,229,160,0.08)' : 'rgba(255,91,91,0.08)') . ';border:1px solid ' . ($ok ? 'rgba(0,229,160,0.3)' : 'rgba(255,91,91,0.3)') . '">';

if ($ok) {
    echo '<div style="color:#00e5a0;font-size:15px">🎉 Fase 1 y Fase 2 operativas</div>
          <div style="color:#4a5568;font-size:11px;margin-top:4px">La API responde correctamente</div>';
} else {
    echo '<div style="color:#ff5b5b;font-size:15px">⚠️ Hay errores que corregir</div>
          <div style="color:#4a5568;font-size:11px;margin-top:4px">Revisa los pasos marcados en rojo</div>';
}

echo '</div>';
echo '<a href="index.php" class="btn">← Ir al Dashboard</a>';
echo '</div></body></html>';