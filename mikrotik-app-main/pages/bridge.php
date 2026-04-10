<?php
// pages/bridge.php

require_once '../config/config.php';
require_once '../lib/RouterOSAPI.php';
require_once '../lib/helpers.php';

$message = null;
$error   = null;
$api     = new RouterOSAPI();

try {
    $api->connect(MIKROTIK_HOST, MIKROTIK_PORT, MIKROTIK_TIMEOUT);
    $api->login(MIKROTIK_USER, MIKROTIK_PASS);

    // ── Manejo de acciones POST ──────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // Crear bridge
        if ($action === 'create_bridge') {
            $name    = trim($_POST['bridge_name'] ?? '');
            $comment = trim($_POST['comment']     ?? '');

            if (empty($name)) {
                throw new InvalidArgumentException("El nombre del bridge no puede estar vacío.");
            }
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
                throw new InvalidArgumentException("Nombre inválido. Solo letras, números, guiones y guiones bajos.");
            }

            // Verificar si ya existe
            $bridges = parseResponse($api->communicate(['/interface/bridge/print']));
            foreach ($bridges as $b) {
                if (($b['name'] ?? '') === $name) {
                    throw new RuntimeException("El bridge '$name' ya existe.");
                }
            }

            $cmd = ['/interface/bridge/add', '=name=' . $name];
            if (!empty($comment)) {
                $cmd[] = '=comment=' . $comment;
            }
            $api->communicate($cmd);
            auditLog('ok', "Bridge '$name' creado correctamente");
            $message = "Bridge '$name' creado correctamente.";
        }

        // Agregar puerto al bridge
        if ($action === 'add_port') {
            $bridge    = trim($_POST['bridge']    ?? '');
            $interface = trim($_POST['interface'] ?? '');

            if (empty($bridge) || empty($interface)) {
                throw new InvalidArgumentException("Debes seleccionar bridge e interfaz.");
            }

            $api->communicate([
                '/interface/bridge/port/add',
                '=bridge='    . $bridge,
                '=interface=' . $interface,
            ]);
            auditLog('ok', "Puerto '$interface' agregado al bridge '$bridge'");
            $message = "Puerto '$interface' agregado al bridge '$bridge'.";
        }

        // Asignar IP al bridge
        if ($action === 'add_ip') {
            $bridge  = trim($_POST['bridge_ip'] ?? '');
            $address = trim($_POST['address']   ?? '');

            if (empty($bridge) || empty($address)) {
                throw new InvalidArgumentException("Completa todos los campos.");
            }
            if (!filter_var(explode('/', $address)[0], FILTER_VALIDATE_IP)) {
                throw new InvalidArgumentException("Formato de IP inválido. Usa: 192.168.10.1/24");
            }

            // Verificar que no exista ya esa IP
            $ips = parseResponse($api->communicate(['/ip/address/print']));
            foreach ($ips as $ip) {
                if (($ip['address'] ?? '') === $address) {
                    throw new RuntimeException("La dirección '$address' ya está asignada.");
                }
            }

            $api->communicate([
                '/ip/address/add',
                '=address='   . $address,
                '=interface=' . $bridge,
            ]);
            auditLog('ok', "IP '$address' asignada a '$bridge'");
            $message = "IP '$address' asignada a '$bridge' correctamente.";
        }

        // Eliminar bridge
        if ($action === 'delete_bridge') {
            $id   = trim($_POST['id']   ?? '');
            $name = trim($_POST['name'] ?? '');

            if (empty($id)) {
                throw new InvalidArgumentException("ID de bridge inválido.");
            }

            $api->communicate(['/interface/bridge/remove', '=.id=' . $id]);
            auditLog('ok', "Bridge '$name' eliminado");
            $message = "Bridge '$name' eliminado correctamente.";
        }
    }

    // ── Leer datos actuales ──────────────────────────────────────────────
    $bridges    = parseResponse($api->communicate(['/interface/bridge/print']));
    $ports      = parseResponse($api->communicate(['/interface/bridge/port/print']));
    $interfaces = parseResponse($api->communicate(['/interface/print']));
    $ips        = parseResponse($api->communicate(['/ip/address/print']));

} catch (InvalidArgumentException $e) {
    $error = "Validación: " . $e->getMessage();
} catch (RuntimeException $e) {
    $error = "Conflicto: " . $e->getMessage();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
} finally {
    $api->disconnect();
}

// Interfaces físicas disponibles (ether, wlan — excluir bridges)
$physicalIfaces = array_filter(
    $interfaces ?? [],
    fn($i) => in_array($i['type'] ?? '', ['ether', 'wlan', 'wireless'])
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Bridge / LAN — MikroManager</title>
  <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>

<div class="shell">
  <!-- Topbar -->
  <header class="topbar">
    <div class="logo"><span class="logo-dot"></span> MikroManager</div>
    <div class="topbar-right">
      <span class="router-info"><?= htmlspecialchars(MIKROTIK_HOST) ?></span>
      <span class="conn-badge conn-badge--online">
        <span class="conn-dot"></span> Conectado
      </span>
      <a href="../logout.php" class="btn btn-danger">Desconectar</a>
    </div>
  </header>

  <div class="layout">
    <!-- Sidebar -->
    <nav class="sidebar">
      <div class="nav-section">General</div>
      <a href="../index.php"         class="nav-item">▦ Dashboard</a>
      <a href="interfaces.php"       class="nav-item">⬡ Interfaces</a>
      <div class="nav-section">Red</div>
      <a href="bridge.php"           class="nav-item active">⬡ Bridge / LAN</a>
      <a href="ip.php"               class="nav-item">◈ Direcciones IP</a>
      <a href="dhcp.php"             class="nav-item">◈ DHCP Server</a>
      <a href="arp.php"              class="nav-item">◈ ARP / Clientes</a>
      <div class="nav-section">Seguridad</div>
      <a href="queues.php"           class="nav-item">≋ Queues</a>
      <a href="firewall.php"         class="nav-item">⊕ Firewall</a>
      <div class="nav-section">Sistema</div>
      <a href="backup.php"           class="nav-item">↓ Backup</a>
      <a href="logs.php"             class="nav-item">≡ Logs</a>
    </nav>

    <main class="main">

      <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- ── Formularios ──────────────────────────────────────────────── -->
      <div class="panels">

        <!-- Crear Bridge -->
        <div class="panel">
          <div class="panel-head">
            <span class="panel-title">Crear Bridge</span>
          </div>
          <form method="POST" action="" style="padding:16px">
            <input type="hidden" name="action" value="create_bridge">
            <div class="form-group" style="margin-bottom:12px">
              <label>Nombre del bridge</label>
              <input type="text" name="bridge_name"
                     placeholder="bridge-local"
                     pattern="[a-zA-Z0-9_\-]+"
                     required>
            </div>
            <div class="form-group" style="margin-bottom:16px">
              <label>Comentario (opcional)</label>
              <input type="text" name="comment" placeholder="Red LAN principal">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">
              Crear Bridge
            </button>
          </form>
        </div>

        <!-- Asignar IP al Bridge -->
        <div class="panel">
          <div class="panel-head">
            <span class="panel-title">Asignar IP al Bridge</span>
          </div>
          <form method="POST" action="" style="padding:16px">
            <input type="hidden" name="action" value="add_ip">
            <div class="form-group" style="margin-bottom:12px">
              <label>Bridge</label>
              <select name="bridge_ip">
                <?php foreach ($bridges as $b): ?>
                  <option value="<?= htmlspecialchars($b['name'] ?? '') ?>">
                    <?= htmlspecialchars($b['name'] ?? '') ?>
                  </option>
                <?php endforeach; ?>
                <?php if (empty($bridges)): ?>
                  <option value="">— Sin bridges —</option>
                <?php endif; ?>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:16px">
              <label>Dirección IP / Máscara</label>
              <input type="text" name="address"
                     placeholder="192.168.10.1/24"
                     required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">
              Asignar IP
            </button>
          </form>
        </div>

      </div>

      <!-- Agregar Puerto al Bridge -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Agregar Puerto al Bridge</span>
        </div>
        <form method="POST" action=""
              style="padding:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
          <input type="hidden" name="action" value="add_port">
          <div class="form-group">
            <label>Bridge destino</label>
            <select name="bridge">
              <?php foreach ($bridges as $b): ?>
                <option value="<?= htmlspecialchars($b['name'] ?? '') ?>">
                  <?= htmlspecialchars($b['name'] ?? '') ?>
                </option>
              <?php endforeach; ?>
              <?php if (empty($bridges)): ?>
                <option value="">— Crea un bridge primero —</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Interfaz física</label>
            <select name="interface">
              <?php foreach ($physicalIfaces as $iface): ?>
                <option value="<?= htmlspecialchars($iface['name'] ?? '') ?>">
                  <?= htmlspecialchars($iface['name'] ?? '') ?>
                  (<?= ($iface['running'] ?? 'false') === 'true' ? 'up' : 'down' ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-group--end">
            <button type="submit" class="btn btn-primary">Agregar Puerto</button>
          </div>
        </form>
      </div>

      <!-- ── Tabla de Bridges ──────────────────────────────────────────── -->
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Bridges activos</span>
          <span class="badge badge-blue"><?= count($bridges) ?> bridges</span>
        </div>
        <table class="table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>MAC</th>
              <th>Estado</th>
              <th>IP asignada</th>
              <th>Puertos</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bridges)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted">
                  No hay bridges creados aún.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($bridges as $bridge): ?>
                <?php
                  $bName    = $bridge['name'] ?? '—';
                  $isUp     = ($bridge['running'] ?? 'false') === 'true';

                  // IPs asignadas a este bridge
                  $bridgeIPs = array_filter(
                      $ips,
                      fn($ip) => ($ip['interface'] ?? '') === $bName
                  );

                  // Puertos de este bridge
                  $bridgePorts = array_filter(
                      $ports,
                      fn($p) => ($p['bridge'] ?? '') === $bName
                  );
                ?>
                <tr>
                  <td class="mono"><?= htmlspecialchars($bName) ?></td>
                  <td class="mono"><?= htmlspecialchars($bridge['mac-address'] ?? '—') ?></td>
                  <td>
                    <span class="badge <?= $isUp ? 'badge-green' : 'badge-red' ?>">
                      <span class="badge-dot"></span>
                      <?= $isUp ? 'up' : 'down' ?>
                    </span>
                  </td>
                  <td class="mono">
                    <?php if (empty($bridgeIPs)): ?>
                      <span class="text-muted">Sin IP</span>
                    <?php else: ?>
                      <?php foreach ($bridgeIPs as $ip): ?>
                        <div><?= htmlspecialchars($ip['address'] ?? '—') ?></div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (empty($bridgePorts)): ?>
                      <span class="text-muted">Sin puertos</span>
                    <?php else: ?>
                      <?php foreach ($bridgePorts as $port): ?>
                        <span class="badge badge-blue" style="margin:1px">
                          <?= htmlspecialchars($port['interface'] ?? '—') ?>
                        </span>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" action=""
                          onsubmit="return confirm('¿Eliminar el bridge <?= htmlspecialchars($bName) ?>? Esta acción no se puede deshacer.')">
                      <input type="hidden" name="action" value="delete_bridge">
                      <input type="hidden" name="id"
                             value="<?= htmlspecialchars($bridge['.id'] ?? '') ?>">
                      <input type="hidden" name="name"
                             value="<?= htmlspecialchars($bName) ?>">
                      <button type="submit" class="btn btn-sm btn-danger">
                        Eliminar
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </main>
  </div>
</div>

</body>
</html>