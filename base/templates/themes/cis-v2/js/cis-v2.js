(function(){
	const CISV2 = {
		version: '1.0.0',
		els: {},
		init(){
			this.cache();
			this.bind();
			this.applyThemeFromStorage();
			this.enhanceTables();
			this.initCharts();
		},
		cache(){
			this.els.sidebar = document.querySelector('.cisv2-sidebar');
			this.els.overlay = document.querySelector('.cisv2-overlay');
			this.els.sidebarToggle = document.querySelector('[data-cisv2-toggle="sidebar"]');
			this.els.themeToggle = document.querySelector('[data-cisv2-toggle="theme"]');
		},
		bind(){
			const self = this;
			if (this.els.sidebarToggle) {
				this.els.sidebarToggle.addEventListener('click', function(){ self.toggleSidebar(); });
			}
			if (this.els.overlay) {
				this.els.overlay.addEventListener('click', function(){ self.closeSidebar(); });
			}
			if (this.els.themeToggle) {
				this.els.themeToggle.addEventListener('click', function(){ self.toggleTheme(); });
			}
			// Close on route change or escape
			document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') self.closeSidebar(); });
		},
		toggleSidebar(){
			if (!this.els.sidebar) return;
			this.els.sidebar.classList.toggle('open');
			if (this.els.overlay) this.els.overlay.classList.toggle('show');
		},
		closeSidebar(){
			if (!this.els.sidebar) return;
			this.els.sidebar.classList.remove('open');
			if (this.els.overlay) this.els.overlay.classList.remove('show');
		},
		toggleTheme(){
			const current = document.documentElement.getAttribute('data-theme') || 'light';
			const next = current === 'light' ? 'dark' : 'light';
			document.documentElement.setAttribute('data-theme', next);
			try { localStorage.setItem('cisv2-theme', next); } catch(e){}
		},
		applyThemeFromStorage(){
			try {
				const stored = localStorage.getItem('cisv2-theme');
				if (stored) document.documentElement.setAttribute('data-theme', stored);
			} catch(e){}
		},
		enhanceTables(){
			// future: add sticky headers, responsive wrappers
			document.querySelectorAll('table').forEach(tbl=>{
				tbl.classList.add('table', 'table-hover', 'align-middle');
			});
		},
		initCharts(){
			// hook for pages to register charts
			const event = new CustomEvent('cisv2:ready');
			document.dispatchEvent(event);
		}
	};

	window.CISV2 = CISV2;
	document.addEventListener('DOMContentLoaded', function(){ CISV2.init(); });
})();
