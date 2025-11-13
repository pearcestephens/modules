<?php if (isset($_SESSION['user_id'])): ?>
<!-- CoreUI bundle includes Popper/Bootstrap 4 compatible JS -->
<!-- Pace + Perfect Scrollbar -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/pace.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/perfect-scrollbar/1.5.5/perfect-scrollbar.min.js"></script>
<!-- CoreUI v3 (BS4-compatible) -->
<script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@3.4.0/dist/js/coreui.bundle.min.js"></script>
<!-- App JS -->
<script src="/assets/js/main.js"></script>
<!-- Global client error handlers -->
<link rel="stylesheet" href="/modules/base/public/error.css">
<script src="/modules/base/public/error.js"></script>
<!-- Behavior capture for auditing/fraud analysis -->
<script src="/modules/base/public/behavior.js"></script>
<!-- jQuery UI -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<!-- Sidebar Enhancements -->
<script src="/modules/base/themes/cis/assets/js/00-theme-core.js"></script>
<script src="/assets/js/sidebar-mobile-enhance.js?v=20250904c"></script>
<!-- Notifications support: ensure dropdown works if included -->
<script>
	// Show badge if server-side injected count
	(function(){
		var badge = document.querySelector('.notific-count');
		var cnt = document.querySelector('.userNotifCounter');
		if(badge && cnt && parseInt(cnt.textContent||'0',10)>0){ badge.style.display='inline-block'; }
	})();
	// Toggle dropdown
	document.addEventListener('click', function(e){
		if(e.target.closest('#notificationToggle')){
			var dd = document.getElementById('notificationDropDown');
			if(dd){ dd.classList.toggle('show'); }
		}
	});
	// Close dropdown on outside click
	document.addEventListener('click', function(e){
		var dd = document.getElementById('notificationDropDown');
		if(!dd) return;
		if(!e.target.closest('#notificationDropDown') && !e.target.closest('#notificationToggle')){
			dd.classList.remove('show');
		}
	});
</script>
<?php endif; ?>
</html>
