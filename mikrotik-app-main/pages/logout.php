<?php

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="?page=dashboard"><?= h(APP_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= get('page','dashboard')==='dashboard'?'active':'' ?>" href="?page=dashboard">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?= get('page')==='routers'?'active':'' ?>" href="?page=routers">Routers</a></li>
                <li class="nav-item"><a class="nav-link <?= get('page')==='settings'?'active':'' ?>" href="?page=settings">Settings</a></li>
                <li class="nav-item"><a class="nav-link <?= get('page')==='arp'?'active':'' ?>" href="?page=arp&id=<?= (int)$router['id'] ?>">ARP</a></li>
                <li class="nav-item"><a class="nav-link <?= get('page')==='nat'?'active':'' ?>" href="?page=nat&id=<?= (int)$router['id'] ?>">NAT</a></li>
                <li class="nav-item"><a class="nav-link <?= get('page')==='firewall'?'active':'' ?>" href="?page=firewall&id=<?= (int)$router['id'] ?>">Firewall</a></li>
            </ul>
            <span class="navbar-text me-3">Logged in as <strong><?= h($_SESSION['username'] ?? '') ?></strong></span>
            <a class="btn btn-outline-light btn-sm" href="?page=logout">Logout</a>
        </div>
    </div>
</nav>
?>

