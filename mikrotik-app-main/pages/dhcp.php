<?php

require '../lib/RouterOSAPI.php';
require '../lib/helpers.php';
//require '../lib/auth.php';
//require '../config/auth.php';
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

// Obtener leases DHCP
$leases = normalizeApiResponse($api->comm('/ip/dhcp-server/lease/print'));

// Manejar la eliminación de un lease
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $leaseId = post('lease_id');
    $api->comm('/ip/dhcp-server/lease/remove', ['.id' => $leaseId]);
    setFlash('success', 'Lease deleted.');
    redirectTo('?page=dhcp&id=' . $routerId);
}
?>