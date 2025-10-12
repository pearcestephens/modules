<?php


// Safely resolve user context
$uid = $_SESSION["userID"] ?? null;
$userDetails = ["first_name" => "Guest"];
if ($uid && function_exists('getUserInformation')) {
  try {
    $ud = getUserInformation($uid);
    if (is_array($ud)) {
      $userDetails = $ud;
    } elseif (is_object($ud)) {
      $userDetails = ["first_name" => $ud->first_name ?? "User"];
    }
  } catch (Throwable $e) {
    // degrade silently
  }
}


?>
<body>
<header class="app-header navbar">
  <button class="navbar-toggler sidebar-toggler d-lg-none mr-auto" type="button" data-toggle="sidebar-show">
    <span class="navbar-toggler-icon"></span>
  </button>
  <a class="navbar-brand" href="#">
    <img class="navbar-brand-full" src="<?php echo HTTPS_URL; ?>assets/img/brand/logo.jpg" width="120va" height="38" alt="The Vape Shed Logo">
    <img class="navbar-brand-minimized" src="<?php echo HTTPS_URL; ?>assets/img/brand/vapeshed-emblem.png" width="30" height="30" alt="The Vape Shed Logo">
  </a>
  <button class="navbar-toggler sidebar-toggler d-md-down-none" type="button" data-toggle="sidebar-lg-show">
    <span class="navbar-toggler-icon"></span>
  </button>
  <ul class="nav navbar-nav d-md-down-none">
    <li class="aibar"></li>
  </ul>
  <ul class="nav navbar-nav ml-auto">
    <li class="nav-item d-md-down-none">
      <span>Hello, <?php echo htmlspecialchars($userDetails["first_name"] ?? "Guest"); ?><?php if ($uid): ?><a class="nav-link" href="?logout=true">Logout</a><?php endif; ?></span>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" id="notificationToggle">

        <?php
        $notificationObject = (object)["totalNotifications" => 0];
        if ($uid && function_exists('userNotifications_getAllUnreadNotifications')) {
          try {
            $notificationObject = userNotifications_getAllUnreadNotifications($uid) ?: (object)["totalNotifications" => 0];
          } catch (Throwable $e) {
            $notificationObject = (object)["totalNotifications" => 0];
          }
        }




        ?>
        <div class="notification-bell" style="background-color: #eeeeee;width: 35px;height: 35px;border-radius: 50px;margin: 0 5px 0 15px;padding: 0;cursor: pointer;">

          <span class="badge badge-pill badge-danger notify notific-count" style=" margin-top: -20px; margin-left: 10px; <?php if ($notificationObject->totalNotifications == 0) {
                                                                                                                            echo 'display:none;';
                                                                                                                          } ?>">
            <span class='userNotifCounter'><?php echo $notificationObject->totalNotifications; ?></span></span>
          <i class="fa fa-bell-o" style="padding-top: 10px;font-size: 15px;"></i>
        </div>
      </a><?php include("notification-dropdown.php"); ?>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="" role="button" aria-haspopup="true" aria-expanded="false">
        <img class="img-avatar" src="<?php echo HTTPS_URL; ?>assets/img/avatars/6.jpg" alt="The Vape Shed">
      </a>
    </li>
  </ul>

  <?php
// Add this to your main CIS navigation template:
//include_once($_SERVER['DOCUMENT_ROOT'] . '/modules/neural/nav_integration.php');
//echo getNeuralDashboardNavHtml($current_page);
?>
 
</header>
<div class="modal fade" id="notificationModel" tabindex="-1" role="dialog" aria-labelledby="notificationModelLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notificationModelLabel">Modal title</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" style=" font-size: 12px; ">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>