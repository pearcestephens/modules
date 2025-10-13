<?php 
include("assets/functions/config.php");

if (!isset($_GET["transfer"])){
  header("Location: index.php");
}


include("assets/template/html-header.php");
include("assets/template/header.php") ;

$transferData = getTransferData($_GET["transfer"],true);

 ?>


 <body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
   
    <div class="app-body">
<?php include("assets/template/sidemenu.php") ?>
      <main class="main">
        <!-- Breadcrumb-->
        <ol class="breadcrumb">
          <li class="breadcrumb-item">Home</li>
          <li class="breadcrumb-item">
            <a href="#">Admin</a>
          </li>
          <li class="breadcrumb-item active">Stock Transfer Received</li>
          <!-- Breadcrumb Menu-->
          <li class="breadcrumb-menu d-md-down-none">
          <?php include('assets/template/quick-product-search.php');?>
          </li>
        </ol>
        <div class="container-fluid">
          <div class="animated fadeIn">

                <div class="col">

                 <div class="card">
                  <div class="card-header">

                   <h4 class="card-title mb-0">Stock Transfer Received #<?php echo $_GET["transfer"];?> <strong><span id="itemsToTransferName"></span></strong></h4>

                   <div class="small text-muted">Count the products you have received and count your existing inventory at the same time.</div>

                 </div>
                 <div class="card-body transfer-data">
                 <?php
                 // Obvious banner for dummy/testing transfers
                 $isDummy = false;
                 if (isset($transferData->source_module) && is_string($transferData->source_module)) {
                   $isDummy = (strcasecmp($transferData->source_module, 'TESTING') === 0);
                 }
                 if (!$isDummy && isset($transferData->transferNotes) && is_string($transferData->transferNotes)) {
                   $isDummy = (stripos($transferData->transferNotes, 'DUMMY') !== false || stripos($transferData->transferNotes, 'TEST') !== false);
                 }
                 if ($isDummy) {
                   echo '<div class="alert alert-danger" role="alert" style="border:3px solid #ff0000;background:#ffe6e6;">'
                       .'<strong>TESTING TRANSFER</strong> — This is a DUMMY/TEST job. Do not take action. Testing is underway.'
                       .'<br>'
                       .'<span class="small">If you weren\'t expecting this, please contact the IT team.</span>'
                       .'</div>';
                 }
                 ?>
                  <div class="row">
                  	<div style=' width: 100%;padding: 10px;border: 1px dotted #DADADB; '>

                  	<h6>FROM: <span style=" font-weight: normal; "><?php echo $transferData->outlet_from->name ?></span></h6> 
                  	<h6>TO: <span style=" font-weight: normal; "><?php echo $transferData->outlet_to->name ?></span></h6>
                    
                  	<h6>PACKED & HANDLED BY: <span style=" font-weight: normal; "><?php echo $transferData->transfer_created_by_user["first_name"] . " " . $transferData->transfer_created_by_user["last_name"] ?></span></h6>
                    <h6>UNPACKED & STOCKTACKED BY: <span style=" font-weight: normal; "><?php echo $transferData->transfer_completed_by_user["first_name"] . " " . $transferData->transfer_completed_by_user["last_name"] ?></span></h6>
                    <h6>TIMESTAMP CREATED: <span style=" font-weight: normal; "><?php echo $transferData->date_created ?></span></h6>
                    <h6>TIMESTAMP PACKED: <span style=" font-weight: normal; "><?php echo $transferData->transfer_completed ?></span></h6>
                    <h6>TIMESTAMP RECEIVED: <span style=" font-weight: normal; "><?php echo $transferData->recieve_completed ?></span></h6>
                                <?php

                      if (strlen($transferData->transferNotes) > 0){

                        echo "<h5>Packing Notes:</h5><p>".str_replace("\n","<br>",$transferData->transferNotes)."</p>";
                      }

                      if (strlen($transferData->completedNotes) > 0){

                        echo "<h5>Delivery Notes:</h5><p>".str_replace("\n","<br>",$transferData->completedNotes)."</p>";
                      }


                      ?>
                      </div>
                  <span style="margin-top:20px;">Items To Transfer: <span id="itemsToTransfer"></span><?php echo count($transferData->products); ?></span>
                  <table class="table table-responsive-sm table-bordered table-striped table-sm " id="transfer-table" >
                    <thead>
                      <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Current Store Stock Level</th>
                        <th>Qty Actually Received</th>
                        <th>Qty To Receive</th>
                        <th>Outlet Source</th>                
                        <th>Outlet Destination</th>
                        <th></th>
                      </tr> 
                    </thead>
                    <tbody id="productSearchBody">

                      <?php 
                
                      for ($i = 0; $i < count($transferData->products);$i++){

                        $storeQty = getProductQtyFromOutlet($transferData->products[$i]["id"],$transferData->outlet_to->id); 


                        if ($transferData->products[$i]["qty_counted_at_destination"] != $transferData->products[$i]["qty_transferred_at_source"]){
                              $flaggedProduct = ' <span class="badge badge-warning">Flagged Product</span>';
                        }else{
                            $flaggedProduct = "";
                        }

                        if ($storeQty < $transferData->products[$i]["qty_counted_at_destination"]){
                              $storeQty = $storeQty.' <span class="badge badge-danger">Is this correct?</span>'; 
                        }
                            
                        
                     

                        if ($transferData->products[$i]["qty_transferred_at_source"] > 0 || $transferData->products[$i]["unexpected_product_added"] == 1){
                        echo "<tr>
                        <td><p style='text-align:center;margin:0;'><img style='cursor:pointer; padding: 0; margin: 0; height: 13px; display:none;' src='assets/img/remove-icon.png' title='Remove Product' onclick='removeProduct(this);'></p> <input type='hidden' class='productID' value='".$transferData->products[$i]["id"]."'></td>
                        <td>".$transferData->products[$i]["name"]."".$flaggedProduct."</td>
                        <td>".$storeQty."</td>
                         <td>".$transferData->products[$i]["qty_counted_at_destination"]."</td>
                         <td>".$transferData->products[$i]["qty_transferred_at_source"]."</td>
                         <td>".$transferData->outlet_from->name."</td> 
                         <td>".$transferData->outlet_to->name."</td> 
                         </tr>";
                       }
                      }

                      ?>

                    </tbody> 
                  </table>                                                     
            </div>
        </div>
        </div>
      </main>
        <?php include("assets/template/personalisation-menu.php") ?>
    </div>

    <script>
      function checkInvalidQty(object){
        var expected = $(object.parentElement.children[4]).html();
        var input = $(object).html();
        if (!isNaN(expected) && !isNaN(input) && expected != "" && input != ""){
          expected = parseInt(expected);
          input = parseInt(input);
 
          if (expected != input){
            $(object.parentElement).css("background-color","#ffd2d2");
          }else{
            $(object.parentElement).css("background-color","rgb(221 255 210)");
          }
        }else{
          $(object.parentElement).css("background-color","initial");
        }
      }

      function displayIncorrectValueRows(){
      $('#transfer-table > tbody  > tr').each(function() {

        var input = $(this.children[3]);
        checkInvalidQty(input[0]);

        });
    } 

    </script>


<?php include("assets/template/html-footer.php") ?>
<?php include("assets/template/footer.php") ?>

<script>
   $(document).ready(function(){
    displayIncorrectValueRows();
   });
   </script>

