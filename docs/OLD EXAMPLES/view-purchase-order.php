<?php
include("assets/functions/config.php");

if (!isset($_GET["orderID"])) {
  header("Location: index.php");
}

$orderObject = getPurchaseOrderViewObject($_GET["orderID"]);

include("assets/template/html-header.php");
include("assets/template/header.php");

$totalOrderedProducts = 0;
$totalArrivedProducts = 0;

?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("assets/template/sidemenu.php") ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">
          <a href="#">Admin</a>
        </li>
        <li class="breadcrumb-item active">View Purchase Order</li>
        <li class="breadcrumb-menu d-md-down-none">
          <?php include('assets/template/quick-product-search.php'); ?>
        </li>
      </ol>
      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="col">
            <div class="card">
              <div class="card-header">
                <h4 class="card-title mb-0">Purchase Order #<?php echo $_GET["orderID"]; ?> To <?php echo $orderObject->outletObject->name ?><br><strong><span id="itemsToTransferName"></span></strong></h4>
                <div class="small text-muted">Purchase Order From: <strong> <?php echo $orderObject->supplierObject->name ?></strong></div>
              </div>
              <div class="card-body transfer-data">
                <div class="row">
                  <div class="col-12 col-xl mb-3 mb-xl-0 float-right">
                    <div style=" width: 100%;padding: 10px;border: 1px dotted #DADADB; ">
                    <h6>CREATED AT: <span style=" font-weight: normal; "><?php echo $orderObject->date_created ?></span></h6>
                      <h6>FROM: <span style=" font-weight: normal; "><?php echo $orderObject->supplierObject->name ?></span></h6>
                      <h6>TO: <span style=" font-weight: normal; "><?php echo $orderObject->outletObject->name ?></span></h6>
                      <h6>UNPACKED &amp; STOCKTACKED BY: <span style=" font-weight: normal; "><?php echo $orderObject->completed_by_user["first_name"] . " " . $orderObject->completed_by_user["last_name"] ?></span></h6>
                      
                      <h6>RECEIVED: <span style=" font-weight: normal; "><?php echo $orderObject->completed_timestamp ?></span></h6>
                      <h5>Staff Unpacking Notes:</h5>
                      <p><?php echo $orderObject->completed_notes ?></p>

                      <h5>Computer Generated Notes:</h5>
                      <p>

                        <?php
                        if (isset($orderObject->flaggedObject->flaggedReasons) > 0) {
                          foreach ($orderObject->flaggedObject->flaggedReasons as $r) {
                            echo "- " . $r->flagged_reason . "<br>";
                          }
                        }

                        ?>
                      </p>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <br>
                  <table class="table table-responsive-sm table-bordered table-striped table-sm " id="transfer-table" style="margin-top:15px">
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Qty Ordered</th>
                        <th>Qty Arrived</th>
                        <th>Note</th>
                      </tr>
                    </thead>
                    <tbody id="productSearchBody">

                      <?php foreach ($orderObject->products as $p) { ?>

                        <?php

$totalOrderedProducts += $p["order_qty"];
$totalArrivedProducts += $p["qty_arrived"];


                        $qtyArrived = null;
                        $reason = null;
                        if (isset($orderObject->flaggedObject->flaggedReasons) > 0) {
                          foreach ($orderObject->flaggedObject->flaggedProducts as $fp) {
                            if ($fp->flagged_product_id == $p["product_id"]) {

                              $qtyArrived = $fp->flagged_product_qtyReceived;
                              $totalArrivedProducts += $qtyArrived;
                              $reason = $fp->flagged_product_reason;
                            }
                          }
                        }
                        ?>

                        <tr>
                          <td><?php echo $p["name"]; ?></td>
                          <td><?php echo $p["order_qty"]; ?></td>
                          <td><?php if (!is_null($qtyArrived)) {
                                echo "<strong style='color:red;'>" . $qtyArrived . "</strong>";
                              } else {
                                echo $p["qty_arrived"];
                              } ?></td>
                          <td><?php if (!is_null($reason)) {
                                echo "<strong style='color:red;'>" . $reason . "</strong>";
                              } ?></td>
                        </tr>
                      <?php }echo '<tr style="background-color: #f2f2f2;"><td><strong>Total</strong></td><td><strong>'.$totalOrderedProducts.'</strong></td><td><strong>'.$totalArrivedProducts.'</strong></td><td></td></tr>'; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
    </main>
    <?php include("assets/template/personalisation-menu.php") ?>
  </div>
  <?php include("assets/template/html-footer.php") ?>
  <?php include("assets/template/footer.php") ?>