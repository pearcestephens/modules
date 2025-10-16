<?php
/**
 * CIS Error Page Helper
 * 
 * Displays a full error page with header, footer, and error template
 * Call this function and it exits immediately after displaying the error
 * 
 * Usage:
 *   showErrorPage($errorMessage);
 *   // OR with options
 *   showErrorPage($errorMessage, [
 *       'title' => 'Custom Title',
 *       'type' => 'danger',
 *       'backUrl' => 'custom.php',
 *       'showDetails' => true
 *   ]);
 * 
 * @param string $errorMessage The error message to display (REQUIRED)
 * @param array $options Optional configuration
 *   - title: string - Error title (default: "Unable to Process Request")
 *   - icon: string - FontAwesome icon class (default: "fa-exclamation-triangle")
 *   - type: string - Error type: warning, danger, info (default: "warning")
 *   - backUrl: string - URL for back button (default: "index.php")
 *   - backLabel: string - Label for back button (default: "Back to List")
 *   - retryUrl: string|null - If set, shows retry button (default: null)
 *   - showDetails: bool - Show troubleshooting section (default: false)
 *   - details: array - Custom troubleshooting steps (default: generic steps)
 * 
 * @package CIS\Shared\Functions
 * @version 1.0.0
 */

function showErrorPage(string $errorMessage, array $options = []): void
{
    // Extract options with defaults
    $errorTitle = $options['title'] ?? "Unable to Process Request";
    $errorIcon = $options['icon'] ?? "fa-exclamation-triangle";
    $errorType = $options['type'] ?? "warning";
    $backUrl = $options['backUrl'] ?? "index.php";
    $backLabel = $options['backLabel'] ?? "Back to List";
    $retryUrl = $options['retryUrl'] ?? null;
    $showDetails = $options['showDetails'] ?? false;
    $details = $options['details'] ?? [];
    
    // Include CIS templates
    include(ROOT_PATH."/assets/template/html-header.php");
    include(ROOT_PATH."/assets/template/header.php");
    ?>
    <body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
        <div class="app-body">
            <?php include(ROOT_PATH."/assets/template/sidemenu.php") ?>
            <main class="main">
                <div class="container-fluid">
                    <?php
                    // Include reusable error template block
                    include($_SERVER['DOCUMENT_ROOT'] . '/modules/shared/blocks/error.php');
                    ?>
                </div>
            </main>
        </div>
        <?php include(ROOT_PATH."/assets/template/html-footer.php") ?>
        <?php include(ROOT_PATH."/assets/template/footer.php") ?>
    </body>
    </html>
    <?php
    exit();
}
