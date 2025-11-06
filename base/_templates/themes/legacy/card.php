<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $pageTitle ?? 'CIS - Central Information System' ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    
    <!-- CIS Core CSS -->
    <link rel="stylesheet" href="/assets/css/cis-core.css">
    
    <!-- Additional Page CSS -->
    <?php if (!empty($pageCSS)): ?>
        <?php foreach ($pageCSS as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <style>
        body.layout-card {
            background-color: var(--cis-gray-100);
            padding: 2rem 0;
        }
        
        .card-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            body.layout-card {
                padding: 1rem 0;
            }
        }
    </style>
    
    <!-- Inline Styles -->
    <?php if (!empty($inlineStyles)): ?>
        <style><?= $inlineStyles ?></style>
    <?php endif; ?>
</head>
<body class="layout-card">
    
    <!-- Card Container -->
    <div class="container">
        <div class="card-container">
            <div class="card shadow">
                <?php if (!empty($cardHeader)): ?>
                    <div class="card-header">
                        <?= $cardHeader ?>
                    </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <?= $content ?>
                </div>
                
                <?php if (!empty($cardFooter)): ?>
                    <div class="card-footer">
                        <?= $cardFooter ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Additional Page JS -->
    <?php if (!empty($pageJS)): ?>
        <?php foreach ($pageJS as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Inline Scripts -->
    <?php if (!empty($inlineScripts)): ?>
        <script><?= $inlineScripts ?></script>
    <?php endif; ?>
    
</body>
</html>
