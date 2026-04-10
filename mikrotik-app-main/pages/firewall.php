<?php
require '../lib/RouterOSAPI.php';
require '../lib/helpers.php';
require '../config/config.php';

requireLogin();

$routerId = (int)get('id', 0);
$router = getRouter($routerId);

if (!$router) {
    setFlash('danger', 'Router not found.');
    redirectTo('?page=routers');
}

[$ok, $api] = connectRouter($router);

if (!$ok) {
    setFlash('danger', 'Connection failed: ' . $api->error);
    redirectTo('?page=router_view&id=' . $routerId);
}

// Obtener reglas de firewall
$firewallRules = normalizeApiResponse($api->comm('/ip/firewall/filter/print'));

// Manejar la eliminación de una regla de firewall
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $firewallId = post('firewall_id');
    $api->comm('/ip/firewall/filter/remove', ['.id' => $firewallId]);
    setFlash('success', 'Firewall rule deleted.');
    redirectTo('?page=firewall&id=' . $routerId);
}
?>