<?php
/**
 * Shared layout (top part): <head>, sidebar and top bar.
 *
 * Set these variables BEFORE including this file:
 *   $pageTitle    - big title in the top bar        (e.g. 'Dashboard')
 *   $pageSubtitle - small text under the title
 *   $activePage   - which sidebar item is highlighted (see $nav keys)
 *   $pageCss      - optional extra stylesheet file name (e.g. 'dashboard.css')
 *   $pageBodyClass - optional page-specific body class
 *
 * Always close the page with includes/footer.php
 */
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Login guard: anyone who is not signed in goes back to the login page.
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$pageTitle    = $pageTitle    ?? 'Sakura Beaded Bags';
$pageSubtitle = $pageSubtitle ?? '';
$activePage   = $activePage   ?? '';
$pageCss      = $pageCss      ?? '';
$pageBodyClass = $pageBodyClass ?? '';

$userName = $_SESSION['user_name'] ?? 'Sakura Admin';
$userRole = $_SESSION['user_role'] ?? 'Admin';
$initials = strtoupper(substr(implode('', array_map(fn($w) => $w[0], explode(' ', trim($userName)))), 0, 2));

// Sidebar menu: key => [label, link, icon]
// Teammates: create these pages inside /admin using the same file names.
$nav = [
    'dashboard' => ['Dashboard',                 'dashboard.php', 'dashboard'],
    'orders'    => ['Order Management',          'orders.php',    'orders'],
    'products'  => ['Product Costing & Pricing', 'products.php',  'costing'],
    'inventory' => ['Inventory Management',      'inventory.php', 'inventory'],
    'payments'  => ['Payments & Sales',          'payments.php',  'payments'],
    'reports'   => ['Financial Reports',         'reports.php',   'reports'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Sakura Beaded Bags</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <?php if ($pageCss): ?><link rel="stylesheet" href="../assets/css/<?= e($pageCss) ?>"><?php endif; ?>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body<?= $pageBodyClass ? ' class="' . e($pageBodyClass) . '"' : '' ?>>
<div class="app">

  <!-- ===== Sidebar ===== -->
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php" aria-label="Sakura Beaded Bags">
      <img src="../assets/images/logo.png" alt="Sakura Beaded Bags"
           onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'brand-fallback',textContent:'SAKURA'}))">
    </a>
    <nav class="nav">
      <?php foreach ($nav as $key => [$label, $link, $ico]): ?>
        <a href="<?= e($link) ?>" class="<?= $key === $activePage ? 'active' : '' ?>">
          <?= icon($ico) ?><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <strong>Made with love, managed with care.</strong>
      Sakura Beaded Bags · Philippines
    </div>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="main">
    <!-- ===== Top bar ===== -->
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Open menu"><?= icon('menu') ?></button>
      <div class="topbar-title">
        <h1><?= e($pageTitle) ?></h1>
        <?php if ($pageSubtitle): ?><p><?= e($pageSubtitle) ?></p><?php endif; ?>
      </div>

      <div class="profile" id="profile">
        <button class="profile-btn" id="profileBtn" aria-haspopup="true" aria-expanded="false">
          <span class="avatar"><?= e($initials) ?></span>
          <span class="profile-text"><strong><?= e($userName) ?></strong><small><?= e($userRole) ?></small></span>
          <?= icon('chevron') ?>
        </button>
        <div class="dropdown" id="profileMenu" role="menu">
          <a href="profile.php" role="menuitem"><?= icon('user') ?> My Profile</a>
          <a href="settings.php" role="menuitem"><?= icon('settings') ?> Account Settings</a>
          <a href="logout.php" role="menuitem"><?= icon('logout') ?> Logout</a>
        </div>
      </div>
    </header>

    <main class="content">