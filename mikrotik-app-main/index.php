<?php
// index.php — Panel principal MikroManager

require_once 'config/config.php';
require_once 'lib/RouterOSAPI.php';
require_once 'lib/helpers.php';

// ─── Datos que se cargan al inicio ──────────────────────────────────────────
$routerData = [
    'connected'  => false,
    'resources'  => [],
    'interfaces' => [],
    'arp'        => [],
    'leases'     => [],
    'error'      => null,
];

$api = new RouterOSAPI();

try {
    $api->connect(MIKROTIK_HOST, MIKROTIK_PORT, MIKROTIK_TIMEOUT);
    $api->login(MIKROTIK_USER, MIKROTIK_PASS);

    $routerData['connected'] = true;

    // Recursos del sistema (CPU, RAM, uptime, versión)
    $routerData['resources'] = parseResponse(
        $api->communicate(['/system/resource/print'])
    )[0] ?? [];

    // Lista de interfaces
    $routerData['interfaces'] = parseResponse(
        $api->communicate(['/interface/print'])
    );

    // Tabla ARP
    $routerData['arp'] = parseResponse(
        $api->communicate(['/ip/arp/print'])
    );

    // Leases DHCP activos
    $routerData['leases'] = parseResponse(
        $api->communicate(['/ip/dhcp-server/lease/print'])
    );



} catch (Exception $e) {
    $routerData['error'] = $e->getMessage();
} finally {
    $api->disconnect();
}

// ─── Helpers de vista ───────────────────────────────────────────────────────

/**
 * Calcula porcentaje de uso de RAM
 */
function getRamPercent(array $res): int {
    $total = (int)($res['total-memory'] ?? 0);
    $free  = (int)($res['free-memory']  ?? 0);
    if ($total === 0) return 0;
    return (int)(( ($total - $free) / $total ) * 100);
}

/**
 * Convierte bytes a MB legible
 */
function toMB(int $bytes): string {
    return round($bytes / 1048576, 1) . ' MB';
}

/**
 * Cuenta interfaces por estado
 */
function countByStatus(array $interfaces, string $status): int {
    return count(array_filter(
        $interfaces,
        fn($i) => ($i['running'] ?? 'false') === ($status === 'up' ? 'true' : 'false')
    ));
}

/**
 * Cuenta entradas ARP estáticas
 */
function countStaticArp(array $arp): int {
    return count(array_filter(
        $arp,
        fn($e) => ($e['dynamic'] ?? 'true') === 'false'
    ));
}

// Extraer datos para la vista
$res        = $routerData['resources'];
$cpuLoad    = (int)($res['cpu-load']      ?? 0);
$ramPercent = getRamPercent($res);
$ramUsed    = toMB((int)($res['total-memory'] ?? 0) - (int)($res['free-memory'] ?? 0));
$ramTotal   = toMB((int)($res['total-memory'] ?? 0));
$uptime     = $res['uptime']              ?? '—';
$version    = $res['version']             ?? '—';
$board      = $res['board-name']          ?? '—';
$totalIface = count($routerData['interfaces']);
$upIface    = countByStatus($routerData['interfaces'], 'up');
$totalLeases= count($routerData['leases']);
$staticArp  = countStaticArp($routerData['arp']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ProyectoMikro — Dashboard</title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<!-- ── Topbar ─────────────────────────────────────────────────────────────── -->
<header class="topbar">
  <div class="logo">
    <span class="logo-dot"></span>
    ProyectoMikro
  </div>
  <div class="topbar-right">
    <span class="router-info">
      <?= htmlspecialchars(MIKROTIK_HOST) ?> · <?= htmlspecialchars($board) ?>
    </span>

    <?php if ($routerData['connected']): ?>
      <span class="conn-badge conn-badge--online">
        <span class="conn-dot"></span> Conectado
      </span>
    <?php else: ?>
      <span class="conn-badge conn-badge--offline">
        <span class="conn-dot"></span> Sin conexión
      </span>
    <?php endif; ?>

    <a href="logout.php" class="btn btn-danger">Desconectar</a>
   
  </div>
</header>

<!-- ── Layout ─────────────────────────────────────────────────────────────── -->
<div class="layout">

  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="nav-section">General</div>
    <a href="index.php"            class="nav-item active">▦ Dashboard</a>
    <a href="pages/interfaces.php" class="nav-item">⬡ Interfaces
      <span class="nav-badge"><?= $totalIface ?></span>
    </a>

    <div class="nav-section">Red</div>
    <a href="pages/bridge.php"  class="nav-item">⬡ Bridge / LAN</a>
    <a href="pages/ip.php"      class="nav-item">◈ Direcciones IP</a>
    <a href="pages/dhcp.php"    class="nav-item">◈ DHCP Server
      <span class="nav-badge"><?= $totalLeases ?></span>
    </a>
    <a href="pages/arp.php"     class="nav-item">◈ ARP / Clientes
      <span class="nav-badge"><?= count($routerData['arp']) ?></span>
    </a>

    <div class="nav-section">Seguridad</div>
    <a href="pages/queues.php"   class="nav-item">≋ Queues</a>
    <a href="pages/firewall.php" class="nav-item">⊕ Firewall</a>

    <div class="nav-section">Sistema</div>
    <a href="pages/backup.php"   class="nav-item">↓ Backup</a>
    <a href="pages/logs.php"     class="nav-item">≡ Logs</a>
  </nav>

  <!-- Contenido principal -->
  <main class="main">

    <!-- Error de conexión -->
    <?php if ($routerData['error']): ?>
      <div class="alert alert-danger">
        Router no alcanzable: <?= htmlspecialchars($routerData['error']) ?>
      </div>
    <?php endif; ?>

    <!-- Tarjetas de estado -->
    <div class="stat-grid">

      <div class="stat-card green">
        <div class="stat-label">CPU Load</div>
        <div class="stat-value">
          <?= $cpuLoad ?><span class="stat-unit">%</span>
        </div>
        <div class="stat-bar">
          <div class="stat-fill" style="width:<?= $cpuLoad ?>%"></div>
        </div>
        <div class="stat-sub"><?= htmlspecialchars($board) ?></div>
      </div>

      <div class="stat-card blue">
        <div class="stat-label">Memoria RAM</div>
        <div class="stat-value">
          <?= $ramPercent ?><span class="stat-unit">%</span>
        </div>
        <div class="stat-bar">
          <div class="stat-fill" style="width:<?= $ramPercent ?>%"></div>
        </div>
        <div class="stat-sub"><?= $ramUsed ?> / <?= $ramTotal ?></div>
      </div>

      <div class="stat-card amber">
        <div class="stat-label">Clientes DHCP</div>
        <div class="stat-value"><?= $totalLeases ?></div>
        <div class="stat-bar">
          <div class="stat-fill" style="width:<?= min($totalLeases * 10, 100) ?>%"></div>
        </div>
        <div class="stat-sub"><?= $staticArp ?> con ARP estático</div>
      </div>

      <div class="stat-card purple">
        <div class="stat-label">Uptime</div>
        <div class="stat-value stat-value--sm">
          <?= htmlspecialchars($uptime) ?>
        </div>
        <div class="stat-bar">
          <div class="stat-fill" style="width:75%"></div>
        </div>
        <div class="stat-sub">RouterOS v<?= htmlspecialchars($version) ?></div>
      </div>

    </div>

    <!-- Interfaces + Acciones rápidas -->
    <div class="panels">

      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Interfaces</span>
          <a href="pages/interfaces.php" class="panel-action">Ver todas →</a>
        </div>
        <table class="table">
          <thead>
            <tr>
              <th>Nombre</th><th>Estado</th><th>MAC</th><th>Tipo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($routerData['interfaces'], 0, 5) as $iface): ?>
              <?php $isUp = ($iface['running'] ?? 'false') === 'true'; ?>
              <tr>
                <td><?= htmlspecialchars($iface['name'] ?? '—') ?></td>
                <td>
                  <span class="badge <?= $isUp ? 'badge-green' : 'badge-red' ?>">
                    <span class="badge-dot"></span>
                    <?= $isUp ? 'up' : 'down' ?>
                  </span>
                </td>
                <td class="mono">
                  <?= htmlspecialchars($iface['mac-address'] ?? '—') ?>
                </td>
                <td>
                  <span class="badge badge-blue">
                    <?= htmlspecialchars($iface['type'] ?? 'ether') ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Acciones rápidas</span>
        </div>
        <div class="actions-grid">
          <a href="pages/bridge.php"  class="action-btn blue">
            <div class="action-icon">⬡</div>
            <div class="action-title">Crear Bridge</div>
            <div class="action-desc">Nueva interfaz + puertos</div>
          </a>
          <a href="pages/dhcp.php"    class="action-btn green">
            <div class="action-icon">◈</div>
            <div class="action-title">Nuevo DHCP</div>
            <div class="action-desc">Pool + server + red</div>
          </a>
          <a href="pages/arp.php"     class="action-btn amber">
            <div class="action-icon">⊕</div>
            <div class="action-title">ARP Estático</div>
            <div class="action-desc">Amarre MAC → IP fija</div>
          </a>
          <a href="pages/backup.php"  class="action-btn purple">
            <div class="action-icon">↓</div>
            <div class="action-title">Backup</div>
            <div class="action-desc">Descargar configuración</div>
          </a>
        </div>
      </div>

    </div>

    <!-- ARP + Log -->

<li class="nav-item"><a class="nav- link <?=

get('page')==='arp'?'active':'' ?>" href="?page=arp&id=<?= (int) $router['id'] ?>">ARP</a></li>

    <div class="panels">

      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Clientes ARP</span>
          <a href="pages/arp.php" class="panel-action">Gestionar →</a>
        </div>
        <table class="table">
          <thead>
            <tr><th>IP</th><th>MAC</th><th>Tipo</th><th>Interfaz</th></tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($routerData['arp'], 0, 6) as $entry): ?>
              <?php $isDynamic = ($entry['dynamic'] ?? 'true') === 'true'; ?>
              <tr>
                <td class="mono"><?= htmlspecialchars($entry['address']     ?? '—') ?></td>
                <td class="mono"><?= htmlspecialchars($entry['mac-address'] ?? '—') ?></td>
                <td>
                  <span class="badge <?= $isDynamic ? 'badge-green' : 'badge-amber' ?>">
                    <?= $isDynamic ? 'dinámico' : 'estático' ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($entry['interface'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Log de auditoría</span>
          <a href="pages/logs.php" class="panel-action">Ver todos →</a>
        </div>
        <div class="log-list">
          <?php
          // Lee el archivo de log si existe
          $logFile = __DIR__ . '/logs/audit.log';
          if (file_exists($logFile)) {
              $lines = array_reverse(
                  array_filter(file($logFile, FILE_IGNORE_NEW_LINES))
              );
              foreach (array_slice($lines, 0, 8) as $line):
                  $parts = explode(' | ', $line, 3);
                  $time  = $parts[0] ?? '';
                  $level = strtolower($parts[1] ?? 'info');
                  $msg   = $parts[2] ?? $line;
          ?>
            <div class="log-item <?= htmlspecialchars($level) ?>">
              <span class="log-time"><?= htmlspecialchars(substr($time, 11, 8)) ?></span>
              <span class="log-msg"><?= htmlspecialchars($msg) ?></span>
            </div>
          <?php
              endforeach;
          } else {
              echo '<div class="log-item"><span class="log-msg text-muted">Sin registros aún.</span></div>';
          }
          ?>
        </div>
      </div>

    </div>

  </main>
</div>

</body>
</html>