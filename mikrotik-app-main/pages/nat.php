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

// Obtener reglas de NAT
$natRules = normalizeApiResponse($api->comm('/ip/firewall/nat/print'));

// Manejar la eliminación de una regla de NAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $natId = post('nat_id');
    $api->comm('/ip/firewall/nat/remove', ['.id' => $natId]);
    setFlash('success', 'NAT rule deleted.');
    redirectTo('?page=nat&id=' . $routerId);
}
?>