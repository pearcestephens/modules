<?php 
include("assets/functions/config.php");
include("assets/template/html-header.php");
include("assets/template/header.php") ;

$transferData = getCurrentTransferObjects(null,$_GET["shipmentID"])[0];
$outletObject = getSingleOutletFromDB($transferData->sourceOutlet->id);

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
        <li class="breadcrumb-item active">Receive Staff Stock Transfer</li>
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

                 <h4 class="card-title mb-0">Shipment #<?php echo $_GET["shipmentID"];?> of Internal Transfer #<?php echo $transferData->id;?> From <?php echo $outletObject->name?><br><strong><span id="itemsToTransferName"></span></strong></h4>

                 <div class="small text-muted">These products need to be stock taked and entered into the system</div>

               </div>
               <div class="card-body">

 <div id="mainData">
                <div class="row" style="padding-bottom: 20px;">
                  <div class="col-12 col-xl mb-3 mb-xl-0 float-right">
                   <div class="btn-group float-right">

                    <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Options</button>
                    <div class="dropdown-menu dropdown-menu-right" x-placement="bottom-end" style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(108px, 35px, 0px);">

                      <button class="dropdown-item" type="button" data-toggle="modal" data-target="#addProductsModal">Add Products</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">

                                 <div style=' width: 100%;padding: 10px;border: 1px dotted #DADADB; '>

                    <h6>FROM: <span style=" font-weight: normal; "><?php echo $transferData->sourceOutlet->name ?></span></h6> 
                    <h6>TO: <span style=" font-weight: normal; "><?php echo $transferData->outletDestination->name ?></span></h6>
                    <h6>SHIPMENT ID: <span style=" font-weight: normal; "><?php echo $_GET["shipmentID"];?></span></h6>
                    <h6>TRANSFER ID: <span style=" font-weight: normal; "><?php echo $transferData->id;?></span></h6>
                    <h6>PACKED & HANDLED BY: <span style=" font-weight: normal; "><?php echo $transferData->packed_by["first_name"] . " " . $transferData->packed_by["last_name"] ?></span></h6>
                    <h6>TIMESTAMP CREATED: <span style=" font-weight: normal; "><?php echo $transferData->timestamp_created ?></span></h6>
                    <h6>TIMESTAMP PACKED: <span style=" font-weight: normal; "><?php echo $transferData->timestamp_packed ?></span></h6>
                    <h6>TIMESTAMP RECEIVED: <span style=" font-weight: normal; "><?php echo $transferData->timestamp_received ?></span></h6>
                    
                                <?php

                      if (strlen($transferData->notes) > 0){

                        echo "<h5>Notes:</h5><p>".$transferData->notes."</p>";
                      }


                      ?>

                      </div>
               
                <span style="margin-top:20px;">Items To Transfer: <span id="itemsToTransfer"></span><?php echo count($transferData->products);?></span> 
                <table class="table table-responsive-sm table-bordered table-striped table-sm " id="transfer-table" >
                  <thead>
                    <tr>
                      <th></th>
                      <th>Name</th>
                      <th><p style="padding: 10px 0 0 0;margin: 0;line-height: 0;"> Qty In Stock</p><span style="font-size:8px;">(Qty Currently In Stock)</span></th>
                      <th>Qty Sent</th>
                      <th>Qty Received</th> 
                      <th>Outlet From</th>                
                      <th>Outlet To</th>
                    </tr>
                  </thead>
                  <tbody id="productSearchBody">

                    <?php 

                    for ($i = 0; $i < count($transferData->products);$i++){ 

                      if ($transferData->products[$i]->outlet->id == $outletObject->id){

                        if ($transferData->products[$i]->qtySent == null){
                          $delete = "";
                          $badge = '<span class="badge badge-warning">Product Manually Added</span>';
                        }else{
                          $badge = "";
                          $delete = "display:none;";
                        }

                        echo "<tr><td>
                        <p style='text-align:center;margin:0;".$delete."'><img style='cursor:pointer; padding: 0; margin: 0; height: 13px;' src='assets/img/remove-icon.png' title='Remove Product' onclick='removeProduct(this);'></p> 
                        <input type='hidden' class='productID' value='".$transferData->products[$i]->id."'>
                        <input type='hidden' class='vendID' value='".$transferData->products[$i]->product->id."'></td> 
                        <td>".$transferData->products[$i]->product->name." ".$badge."</td>
                        <td><span id='existingQty'>".$transferData->products[$i]->qtyInStockDestination."</span></td>
                        <td>".$transferData->products[$i]->qtySent."</td>
                        <td><input type='input' onkeyup='addToLocalStorage();' value=''></td>
                        <td>".$outletObject->name."</td> 
                        <td>".$transferData->outletDestination->name."</td> </tr>";

                      }

                    }

                    ?>

                  </tbody>
                </table>


                <div style="width:100%;">
                  <label for="notesForTransfer">Notes & Discrepancies</label>
                  <textarea onkeyup="addToLocalStorage();" style="  width:500px;  border: 1px solid #b3b3b3;height:100px;" class="form-control" id="notesForTransfer" rows="3"></textarea>
                </div>
                <p style=" padding: 0; margin: 10px 0 0 0; font-weight: bold; font-size: 12px; ">Counted & Handled By: <?php echo $userDetails["first_name"]?> <?php echo $userDetails["last_name"]?></p>
                <p style=" font-size: 10px; width: 100%; ">By accepting this transfer you declare that you have individually counted all the products recieved in this Transfer. You also declare that you have individually counted all of the existing stock of these products and that the inventory levels of these products are accurate.</p><br>  
                <button type="button" id="createTransferButton" class="btn btn-primary" onclick="markReadyForDelivery();">Set Transfer Stocktaked & Delivered</button>
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

      function removeProduct(object){
        $(object.parentElement.parentElement.parentElement).remove();
        var _productID = $(object.parentElement.nextElementSibling).val();
        addToLocalStorage();
        $.post("assets/functions/ajax.php?method=removeProductFromStockTransfer", { productTableID: _productID }, function(data, status){}); 
      } 


    function addProductToTransfer(_productID,productName,productQtyInStock,buttonObject){

      var alreadyExists = false;

      $('#transfer-table > tbody  > tr').each(function() {

        if (this.children[0].children[2].value == _productID){
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
        $('#transfer-table').prepend('<tr><td><p style="text-align:center;margin:0;"><img style="cursor:pointer; padding: 0; margin: 0; height: 13px;" src="assets/img/remove-icon.png" title="Remove Product" onclick="removeProduct(this);"></p> <input type="hidden" class="productID" value="'+productTableID+'"><input type="hidden" class="vendID" value="'+_productID+'"></td> <td>'+productName+' <span class="badge badge-warning">Product Manually Added</span></td> <td>'+productQtyInStock+'</td> <td><input type="input" onkeyup="addToLocalStorage();" value=""></td> <td>0</td><td><input type="input" onkeyup="addToLocalStorage();" value=""></td>  <td>'+outletFrom+'</td> <td>'+outletTo+'</td> </tr>');
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

    if (localStorage.staffTransfer.length > 0) {

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

        //  $('#createTransferButton').attr("disabled", "disabled");
          
          var _transferDetails = {
            _products: productArray,
            _transferID: <?php echo $transferData->id; ?>,
            _transferNotes: $('#notesForTransfer').val(),
            _shipmentID: "<?php echo $_GET["shipmentID"] ?>",
            _staffID: staffID,
            _sourceID: "<?php echo $transferData->outletDestination->id; ?>"
          };


          $.post("assets/functions/ajax.php?method=isLoggedIn", function(data, status){

            if (data == "true"){

              $.post("assets/functions/ajax.php?method=MarkStaffTransferReceived", { transferDetails: _transferDetails }, function(data, status){});

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
