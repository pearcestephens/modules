<?php
/**
 * File: resources/views/layout/header.php
 * Purpose: Shared HTML document head + opening structure for admin views.
 * Author: GitHub Copilot
 * Last Modified: 2025-11-02
 * Dependencies: public/assets/css/app.css, Bootstrap 5
 */

declare(strict_types=1);

$pageTitle = isset($pageTitle) && $pageTitle !== ''
    ? $pageTitle
    : 'CIS Operations Console';
$assetBase = isset($assetBase) && $assetBase !== ''
    ? rtrim((string)$assetBase, '/')
    : '/modules/public/assets';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php if (!empty($csrfToken)): ?>
        <meta name="csrf-token" content="<?= htmlspecialchars((string)$csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-xxweKXh3p0N2m6aU+4yPpnQuGl/40UmfDNMUL7SHaR0ECgfHDI127IhY+xQmB6m5" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8'); ?>/css/app.css?v=1">
</head>
<body class="vs-app">
    <a class="visually-hidden-focusable vs-skip-link" href="#main-content">Skip to main content</a>
    <header class="vs-header navbar navbar-dark bg-dark shadow-sm">
        <div class="container-fluid d-flex align-items-center justify-content-between">
            <span class="navbar-brand fw-semibold">The Vape Shed · Admin</span>
            <?php if (!empty($headerActions) && is_array($headerActions)): ?>
                <div class="d-flex gap-2">
                    <?php foreach ($headerActions as $action):
                        $label = htmlspecialchars((string)($action['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $url = htmlspecialchars((string)($action['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
                        $variant = htmlspecialchars((string)($action['variant'] ?? 'outline-light'), ENT_QUOTES, 'UTF-8');
                    ?>
                        <a class="btn btn-sm btn-<?= $variant; ?>" href="<?= $url; ?>"><?= $label; ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <div class="vs-app__body container-fluid">
        <div class="row flex-nowrap">
