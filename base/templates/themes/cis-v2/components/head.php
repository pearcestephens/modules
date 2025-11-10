<?php
/**
 * cis-v2 Head Component
 * Enterprise grade head with Bootstrap 5 and modern libraries.
 * Variables available: $pageTitle, $extraHead
 */
$___defaultTitle = 'CIS v2';
$___pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : $___defaultTitle;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
	<meta name="color-scheme" content="light dark" />
	<meta name="robots" content="noindex,nofollow" />
	<meta name="description" content="CIS Enterprise Interface" />
	<link rel="icon" type="image/png" href="/assets/img/brand/favicon.png" />
	<title><?= htmlspecialchars($___pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
	<base href="/" />

	<!-- Design Tokens -->
	<link rel="stylesheet" href="/modules/base/templates/themes/cis-v2/css/variables.css?v=20251110" />
	<!-- Theme Styles -->
	<link rel="stylesheet" href="/modules/base/templates/themes/cis-v2/css/theme.css?v=20251110" />

	<!-- Icon Packs (Font Awesome 6, Bootstrap Icons, Tabler Icons) -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />

	<!-- Core CSS Framework: Bootstrap 5 -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384" crossorigin="anonymous" />

	<!-- Optional libs CSS (e.g., simplebar for custom scrollbars) -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simplebar@6.2.5/dist/simplebar.min.css" />

	<!-- Performance: Preconnect -->
	<link rel="preconnect" href="https://cdn.jsdelivr.net" />
	<link rel="preconnect" href="https://cdnjs.cloudflare.com" />

	<!-- Dark Mode Preference Sync -->
	<script>
		(function(){
			const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
			const stored = localStorage.getItem('cisv2-theme');
			const mode = stored || (prefersDark ? 'dark' : 'light');
			document.documentElement.setAttribute('data-theme', mode);
		})();
	</script>

	<?php if (isset($extraHead) && is_string($extraHead)) echo $extraHead; ?>
</head>
