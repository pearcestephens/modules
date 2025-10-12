<?php
declare(strict_types=1);

// Ensure HTML content type
if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
}

// The module PageController passes $content and optional $page_title/$page_blurb
$__cisContent = $content ?? '';
$__pageTitle = isset($page_title) && is_string($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : '';
$__pageBlurb = isset($page_blurb) && is_string($page_blurb) ? htmlspecialchars($page_blurb, ENT_QUOTES, 'UTF-8') : '';

// Inline CIS template markup (no external include), and inject $__cisContent
?>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php'); ?>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/html-header.php'); ?>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/header.php'); ?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">

    <div class="app-body">
        <?php include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/sidemenu.php'); ?>
        <main class="main">
            <!-- Breadcrumb -->
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Home</li>
                <li class="breadcrumb-item"><a href="#"></a></li>
                <li class="breadcrumb-item active"></li>
                <!-- Breadcrumb Menu-->
                <li class="breadcrumb-menu d-md-down-none">
                    <?php include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/quick-product-search.php'); ?>
                </li>
            </ol>
            <div class="container-fluid">
                <div class="animated fadeIn">
                    <div class="row">
                        <div class="col ">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title mb-0"><?php echo $__pageTitle; ?></h4>
                                    <div class="small text-muted"><?php echo $__pageBlurb; ?></div>
                                </div>
                                <div class="card-body">
                                    <div class="cis-content">
                                        <?php echo $__cisContent; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--/.row-->
                </div>
            </div>
        </main>
    </div>

    <?php include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/html-footer.php'); ?>
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/footer.php'); ?>
