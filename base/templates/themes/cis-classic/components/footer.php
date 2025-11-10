  </main>
</div><!-- Close app-body -->

<footer class="app-footer">
  <div>
    <a href="https://www.vapeshed.co.nz">The Vape Shed</a>
    <span>&copy; <?php echo date("Y"); ?> Ecigdis Ltd</span>
  </div>
  <div class="ml-auto">
    <small>Developed by <a href="https://www.pearcestephens.co.nz" target="_blank">Pearce Stephens</a></small>
    <a href="/submit_ticket.php" class="btn btn-sm btn-outline-danger ml-2">
      <i class="fas fa-bug"></i> Report a Bug
    </a>
  </div>
</footer>

<!-- Mobile sidebar management -->
<script>
(function() {
    'use strict';

    // Close mobile sidebar when clicking links
    if (window.innerWidth < 992) {
        document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                setTimeout(function() {
                    document.body.classList.remove('sidebar-mobile-show');
                }, 200);
            });
        });
    }

    // Close sidebar on window resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            document.body.classList.remove('sidebar-mobile-show');
        }
    });

    // ESC key closes mobile sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.body.classList.contains('sidebar-mobile-show')) {
            document.body.classList.remove('sidebar-mobile-show');
        }
    });
})();
</script>

<style>
  .btn-outline-danger:hover {
    background-color: #dc3545 !important;
    color: white !important;
    transition: 0.2s ease-in-out;
  }
</style>

</body>
</html>
