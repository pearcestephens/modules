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
        body.layout-split {
            margin: 0;
            overflow: hidden;
        }
        
        .split-wrapper {
            display: flex;
            height: 100vh;
        }
        
        .split-left {
            flex: <?= $splitRatio ?? '40' ?>;
            overflow-y: auto;
            background-color: var(--cis-white);
            border-right: 1px solid var(--cis-border-color);
        }
        
        .split-right {
            flex: <?= 100 - ($splitRatio ?? 40) ?>;
            overflow-y: auto;
            background-color: var(--cis-gray-100);
        }
        
        .split-resize-handle {
            width: 5px;
            cursor: col-resize;
            background-color: var(--cis-border-color);
            transition: background-color 0.2s;
        }
        
        .split-resize-handle:hover {
            background-color: var(--cis-primary);
        }
        
        /* Mobile responsive - stack vertically */
        @media (max-width: 768px) {
            .split-wrapper {
                flex-direction: column;
            }
            
            .split-left,
            .split-right {
                flex: 1;
                border-right: none;
                border-bottom: 1px solid var(--cis-border-color);
            }
            
            .split-resize-handle {
                display: none;
            }
        }
    </style>
    
    <!-- Inline Styles -->
    <?php if (!empty($inlineStyles)): ?>
        <style><?= $inlineStyles ?></style>
    <?php endif; ?>
</head>
<body class="layout-split">
    
    <div class="split-wrapper" id="splitWrapper">
        
        <!-- Left Panel -->
        <div class="split-left" id="splitLeft">
            <?= $leftContent ?>
        </div>
        
        <!-- Resize Handle -->
        <div class="split-resize-handle" id="resizeHandle"></div>
        
        <!-- Right Panel -->
        <div class="split-right" id="splitRight">
            <?= $rightContent ?>
        </div>
        
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Split Resize Logic -->
    <script>
        $(document).ready(function() {
            let isResizing = false;
            
            $('#resizeHandle').on('mousedown', function(e) {
                isResizing = true;
                $('body').css('cursor', 'col-resize');
                e.preventDefault();
            });
            
            $(document).on('mousemove', function(e) {
                if (!isResizing) return;
                
                const containerWidth = $('#splitWrapper').width();
                const leftWidth = e.clientX;
                const leftPercent = (leftWidth / containerWidth) * 100;
                
                // Limit between 20% and 80%
                if (leftPercent >= 20 && leftPercent <= 80) {
                    $('#splitLeft').css('flex', leftPercent);
                    $('#splitRight').css('flex', 100 - leftPercent);
                    
                    // Save to localStorage
                    localStorage.setItem('splitRatio', leftPercent);
                }
            });
            
            $(document).on('mouseup', function() {
                if (isResizing) {
                    isResizing = false;
                    $('body').css('cursor', 'default');
                }
            });
            
            // Restore split ratio from localStorage
            const savedRatio = localStorage.getItem('splitRatio');
            if (savedRatio) {
                $('#splitLeft').css('flex', savedRatio);
                $('#splitRight').css('flex', 100 - savedRatio);
            }
        });
    </script>
    
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
