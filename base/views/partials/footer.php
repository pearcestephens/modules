<?php
/**
 * Template Partial: Footer Content
 * 
 * Contains the copyright notice and bug report button
 * 
 * @package Modules\Base\Views\Layouts
 */
?>
<footer class="app-footer">
  <div>
    <a href="https://www.vapeshed.co.nz">The Vape Shed</a>
    <span>&copy; <?php echo date("Y"); ?> Ecigdis Ltd</span>
  </div>
  <div class="ml-auto">
    <div>
      <small class="">
        Developed by <a href="https://www.pearcestephens.co.nz" target="_blank">Pearce Stephens</a>
      </small>
      <a href="/submit_ticket.php" class="btn btn-sm btn-outline-danger" style="font-size: 13px;">
        🐞 Report a Bug
      </a>
    </div>

    <style>
      .btn-outline-danger:hover {
        background-color: #dc3545 !important;
        color: white !important;
        transition: 0.2s ease-in-out;
      }
    </style>
  </div>
  
  <?php if (isset($_SESSION["userID"])){ ?>
    <script>
        var url = document.location.toString();
        if (url.match('#')) {
            $('.nav-tabs a[href="#' + url.split('#')[1] + '"]').tab('show');
        }

        //Change hash for page-reload
        $('.nav-tabs li a').on('click', function (e) {
            window.location.hash = e.target.hash;
        }); 
    </script>

    <meta name="analytics-token" content="">
    <!-- Analytics scripts commented out - can be re-enabled later -->
  <?php } ?>
</footer>
