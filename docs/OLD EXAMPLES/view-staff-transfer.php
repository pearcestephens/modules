<?php 
include("assets/functions/config.php");


if (isset($_GET["deleteShipment"])){
  deleteShipment($_GET["shipmentID"]);
  header('Location: //staff.vapeshed.co.nz/');
  die();
}

include("assets/template/html-header.php");
include("assets/template/header.php") ;

$transferData = getCurrentTransferObjects(null,$_GET["shipmentID"])[0];
$outletObject = getSingleOutletFromDB($transferData->sourceOutlet->id);

// Define staffID for JS usage (prevents ReferenceError in markReadyForDelivery)
$staffID = isset($_SESSION["userID"]) ? (int)$_SESSION["userID"] : 0;

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
        <li class="breadcrumb-item active">View Staff Stock Transfer</li>
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

                 <h4 class="card-title mb-0">Staff Stock Transfer #<?php echo $transferData->id;?>-<?php echo $outletObject->store_code;?> <br> <span style=" font-size: 18px; color: #2594f1; margin-bottom: 10px;"><?php echo $outletObject->name?> To <?php echo $transferData->outletDestination->name?></span><br><strong><span id="itemsToTransferName"></span></strong></h4>

                 <div class="small text-muted" style=" margin-top: 5px; ">These products need to be gathered and prepared for delivery</div>

               </div>
               <div class="card-body">

                <div id="address-details">
                  
                  <p>Delivery Address:</p>

                  <p>
                    The Vape Shed<br>
                    <?php echo $transferData->outletDestination->physical_address_1; ?><br>
                    <?php echo $transferData->outletDestination->physical_suburb; echo " " .$transferData->outletDestination->physical_postcode; ?><br>
                    <?php echo $transferData->outletDestination->physical_city; ?><br><br>

                    GSS Packing Reference: #<?php echo $transferData->id;?>-<?php echo $outletObject->store_code;?>
                  </p>
                </div>

 <div id="mainData">
                <div class="row" style="padding-bottom: 20px;">
                  <div class="col-12 col-xl mb-3 mb-xl-0 float-right">
                   <div class="btn-group float-right">

                    <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Options</button>
                    <div class="dropdown-menu dropdown-menu-right" x-placement="bottom-end" style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(108px, 35px, 0px);">

                      <button class="dropdown-item" type="button" data-toggle="modal" data-target="#addProductsModal">Add Products</button>
                      <button class="dropdown-item" type="button" onclick="deleteTransfer(<?php echo $_GET['shipmentID'];?>)">Delete Transfer</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
               
                <span style="margin-top:20px;">Items To Transfer: <span id="itemsToTransfer"></span><?php echo count($transferData->products); ?></span> 
                <table class="table table-responsive-sm table-bordered table-striped table-sm " id="transfer-table" >
                  <thead>
                    <tr>
                      <th></th>
                      <th>Name</th>
                      <th><p style="padding: 10px 0 0 0;margin: 0;line-height: 0;"> Qty In Stock</p><span style="font-size:8px;">(Qty In Stock BEFORE The Transfer)</span></th>
                      <th>Qty To Transfer</th>
                      <th>Qty Transferred</th>
                      <th>Qty To Remain</th>
                      <th>Outlet Source</th>                
                      <th>Outlet Destination</th>
                    </tr>
                  </thead>
                  <tbody id="productSearchBody">

                    <?php 

                    for ($i = 0; $i < count($transferData->products);$i++){ 

                      if ($transferData->products[$i]->outlet->id == $outletObject->id){

                        if ($transferData->products[$i]->qty_requested == 0){
                          $qtyToRemain = "N/A";
                        }else{
                          $qtyToRemain = $transferData->products[$i]->qtyInStock - $transferData->products[$i]->qty_requested;
                        }

                        echo "<tr><td>
                        <p style='text-align:center;margin:0;'><img style='cursor:pointer; padding: 0; margin: 0; height: 13px;' src='assets/img/remove-icon.png' title='Remove Product' onclick='removeProduct(this);'></p> 
                        <input type='hidden' class='productID' value='".$transferData->products[$i]->id."'></td> 
                        <td>".$transferData->products[$i]->product->name."</td>
                        <td><span>".$transferData->products[$i]->qtyInStock."</span></td>
                        <td>".$transferData->products[$i]->qty_requested."</td>
                        <td><input type='input' onkeyup='addToLocalStorage();' value=''></td>
                        <td>".$qtyToRemain."</td>
                        <td>".$outletObject->name."</td> 
                        <td>".$transferData->outletDestination->name."</td> </tr>";

                      }

                    }

                    ?>

                  </tbody>
                </table>
                <div style="width:100%;">
                  <label for="notesForTransfer">Tracking Number</label><br>
                  <input class="form-control" name="tracking-number" style="border: 1px solid #b3b3b3;width:200px;" type="text" id="tracking-number" placeholder="e.g. E40-12345678" required="">
                  <br>
                </div>

                <div style="width:100%;">
                  <label for="notesForTransfer">Notes & Discrepancies</label>
                  <textarea onkeyup="addToLocalStorage();" style="  width:500px;  border: 1px solid #b3b3b3;height:100px;" class="form-control" id="notesForTransfer" rows="3"></textarea>
                </div>
                <p style=" padding: 0; margin: 10px 0 0 0; font-weight: bold; font-size: 12px; ">Counted & Handled By: <?php echo $userDetails["first_name"]?> <?php echo $userDetails["last_name"]?></p>
                <p style=" font-size: 10px; width: 100%; ">By setting this transfer "Ready For Delivery" you declare that you have individually counted all the products despatched in this Transfer. You also declare that you have individually counted all of the existing stock of these products and that the inventory levels of these products are accurate.</p><br>  
                <button type="button" id="createTransferButton" class="btn btn-primary" onclick="markReadyForDelivery();">Set Transfer Ready For Delivery</button>
                <span id="loadingWarning" style="display:none;">Please Wait For Transfer to Complete...May take up to 60 seconds..Will automatically refresh when complete.</span>
                <input type='hidden' id='transferID' value='<?php echo $transferData->id; ?>'>

              </div>          
            </div> 
          </div>
            <div class="card-body update-success" style="display:none;">
              <div class="alert alert-primary" role="alert">
                <strong>Update Complete</strong><br><br>Changes in vend will take a short moment to display.<br><br>
                Redirecting to dashboard in 3...2...1
              </div>
            </div>                                      
          </div>
        </div>
      </div>
    </main>
    <?php include("assets/template/personalisation-menu.php") ?>
  </div>

  <div class="modal fade" id="addProductsModal" tabindex="-1" role="dialog" aria-labelledby="addProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document" style=" padding: 5px; ">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addProductsModalLabel">Add Products</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Quickly add individual products to this Transfer.</p>

          <div class="form-group">
            <input type="text" id="search-input" onkeyup="searchProducts()" name="text-input" class="form-control" placeholder="Begin searching here...">
          </div>
          <table class="table table-responsive-sm table-bordered table-striped table-sm " id="addProductSearch">
            <thead>
              <tr>
                <th>Name</th>               
                <th>Qty In Stock</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="productAddSearchBody">

            </tbody>
          </table>       
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>


        </div>
      </div>
    </div>
  </div>

  <script>

    function deleteTransfer(shipmentID){

      if (confirm("Are you sure you want to delete this shipment?")){
        location.replace("//staff.vapeshed.co.nz/view-staff-transfer.php?shipmentID="+shipmentID+"&deleteShipment=true");
      }
    }

      function removeProduct(object){
        $(object.parentElement.parentElement.parentElement).remove();
        var _productID = $(object.parentElement.nextElementSibling).val();
        addToLocalStorage();
        $.post("assets/functions/ajax.php?method=removeProductFromStockTransfer", { productID: _productID }, function(data, status){}); 
      } 


    function addProductToTransfer(_productID,productName,productQtyInStock,buttonObject){

      var alreadyExists = false;

      $('#transfer-table > tbody  > tr').each(function() {

        if (this.children[0].children[1].value == _productID){
          alreadyExists = true;
        }
      });

      if (alreadyExists == false){    

        var outletFrom = "<?php echo $outletObject->name; ?>";
        var outletTo = "<?php echo $transferData->outletDestination->name; ?>";

        var _transferID = <?php echo $transferData->id ?>; 
        var _sourceOutletID = "<?php echo $transferData->sourceOutlet->id ?>";

        $.post("assets/functions/ajax.php?method=addProductToStaffTransfer", { transferID: _transferID,productID: _productID, outletID:_sourceOutletID}, function(data, status){

          var productTableID = data;
        $('#transfer-table').prepend('<tr><td><p style="text-align:center;margin:0;"><img style="cursor:pointer; padding: 0; margin: 0; height: 13px;" src="assets/img/remove-icon.png" title="Remove Product" onclick="removeProduct(this);"></p> <input type="hidden" class="productID" value="'+productTableID+'"></td> <td>'+productName+' <span class="badge badge-warning">Product Manually Added</span></td> <td><span>'+productQtyInStock+'</span></td> <td>0</td><td><input type="input" onkeyup="addToLocalStorage();" value=""></td><td></td>  <td>'+outletFrom+'</td> <td>'+outletTo+'</td> </tr>');
        $(buttonObject).html("Added");

        });


        
      }else{
        alert("Product Already Exists In Transfer");
      }
    }

    function searchProducts(){

      var searchTerm = $('#search-input').val();

      if (searchTerm.length > 1){
       $.post("assets/functions/ajax.php?method=searchForProductByOutlet", {keyword: searchTerm, outletID: '<?php echo $transferData->sourceOutlet->id ?>'}, function(result){

        var searchResults = JSON.parse(result);

        $('#productAddSearchBody').empty();

        for (var i = 0; i < 10;i++){
          $('#productAddSearchBody').append("<tr><td><a target='_blank' href='product.php?id="+searchResults[i].id+"'>"+searchResults[i].name+"</a></td><td>"+searchResults[i].qtyInStock+"</td><td><button class='btn btn-square btn-block btn-success' type='button' onclick='addProductToTransfer(\""+searchResults[i].id+"\",\""+searchResults[i].name+"\",\""+searchResults[i].qtyInStock+"\", this)' style=' font-size: 14px; padding: 0; '>Add Product</button></td></tr>");
        }

      });
     }else{
      $('#productAddSearchBody').empty();
    }

  }


  function addToLocalStorage(){

    var staffTransfer = {
      _products: getProducts(),
      _transferID: $('#transferID').val(),
      _transferNotes: $('#notesForTransfer').val(),
      _staffID: <?php echo $_SESSION["userID"]?>
    };

    if (staffTransfer._products.length == 0){
      localStorage.removeItem("staffTransfer");
    }else{
      localStorage.staffTransfer = JSON.stringify(staffTransfer);  
    }

  }

  function loadStoredValues(){

    if (localStorage.staffTransfer) {

      var staffTransfer = JSON.parse(localStorage.staffTransfer);
      var currentTransferID = document.getElementById("transferID").value;
      var transferNotes = staffTransfer._transferNotes;

      if (currentTransferID == staffTransfer._transferID){

        if (confirm("You have a recovered list of Stock Transfer Data, would you like to restore it?")){

          $('#notesForTransfer').val(transferNotes);

          if (staffTransfer._products.length > 0){

            for (var i = 0; i < staffTransfer._products.length;i++){

              var productID = staffTransfer._products[i].productID;
              var qtyToTransfer = staffTransfer._products[i].qtyToTransfer;



              $('#transfer-table tr td .productID').each(function(){ 
                var productRowID = $(this).val();

                if (productRowID == productID){
                  $(this.parentElement.parentElement.children[4].children[0]).val(qtyToTransfer);

                }
              });
            }
          }
        }
      }
    }

  }


  function getProducts(){

    var productArray = [];

    $('#transfer-table > tbody  > tr').each(function() {

      var product = {
        productID:this.children[0].children[1].value,
        qtyInStock:this.children[2].children[0].innerText,
        qtyToTransfer:this.children[4].children[0].value
      };

      productArray.push(product); 
    });

    return productArray;

  }

  function markReadyForDelivery(){

    var productArray = getProducts();

    $('#transfer-table > tbody  > tr').each(function() {

      var product = {
        productID:this.children[0].children[1].value,
        qtyInStock:this.children[2].children[0].innerText,
        qtyToTransfer:this.children[4].children[0].value
      };

      if (this.children[4].children[0].value.length == 0){
        productArray = false;
      }
    });


    if (productArray.length > 0 && productArray != false){

          $('#createTransferButton').attr("disabled", "disabled");
          
          var _transferDetails = {
            _products: productArray,
            _transferID: <?php echo $transferData->id; ?>,
            _transferNotes: $('#notesForTransfer').val(),
            _staffID: <?php echo $staffID; ?>,
            _trackingCode: $('#tracking-number').val(),
            _sourceID: "<?php echo $transferData->sourceOutlet->id; ?>"
          };

          $.post("assets/functions/ajax.php?method=isLoggedIn", function(data, status){

            if (data == "true"){

              $.post("assets/functions/ajax.php?method=MarkStaffTransferPacked", { transferDetails: _transferDetails }, function(data, status){
                alert(data);
              });
 
              $('#mainData').hide(); 
              $('.update-success').fadeIn();
              localStorage.removeItem("staffTransfer");
              setTimeout(function(){window.location.assign("/")}, 1000);      

            }else{
              alert("Session has timed out, please refresh and login again. Don't worry, your data will still be saved.");
            }
            
          });    
        }else{
          alert("Products missing a Qty in Stock or Qty To Transfer");
        }


      }

      
    </script>

    <?php include("assets/template/html-footer.php") ?>
    <?php include("assets/template/footer.php") ?>

    <script>
      $(document).ready(function(){
        loadStoredValues();  
      });

    </script>
