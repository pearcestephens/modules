<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="/assets/img/brand/favicon.png">
    <?php
      $___defaultTitle = 'CIS Module';
      $___pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : $___defaultTitle;
      $___themeSettings = class_exists('Theme') ? \Theme::getSettings() : [];
      $___themeJson = json_encode($___themeSettings, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    ?>
    <title><?php echo htmlspecialchars($___pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <base href="/">

    <!-- CORE CSS (CoreUI v2 + Bootstrap 4 compatible) -->
    <link href="/assets/css/style1.css?updated=23232343" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.5.5/css/simple-line-icons.min.css" rel="stylesheet">

    <!-- UI + Pace -->
    <link href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/themes/blue/pace-theme-minimal.min.css" rel="stylesheet">

    <!-- CIS Overrides -->
  <link href="/modules/base/themes/cis/assets/css/00-theme-core.css" rel="stylesheet">
    <link href="/assets/css/bootstrap-compatibility.css?v=20250902" rel="stylesheet">
    <link href="/assets/css/custom.css?updated=222" rel="stylesheet">
    <link href="/assets/css/sidebar-styling-restore.css?v=20251030_tight_spacing" rel="stylesheet">
    <link href="/assets/css/buttons-modern.css?v=20251109_set3" rel="stylesheet">

    <!-- jQuery + Moment (safe globally) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <script>
      // Expose minimal, vetted theme settings to JS
      window.CIS_THEME = <?php echo $___themeJson ?: '{}'; ?>;
      // Apply sidebar collapsed state early to avoid FOUC
      (function(){
        try{
          var collapsed = !!(window.CIS_THEME && window.CIS_THEME.sidebar && window.CIS_THEME.sidebar.collapsed);
          if(collapsed){ document.documentElement.classList.add('cis-sidebar-collapsed'); }
        }catch(e){}
      })();
    </script>

    <?php
      if (isset($extraHead) && is_string($extraHead)) {
        echo $extraHead; // page-scoped head additions
      }
    ?>
</head>
