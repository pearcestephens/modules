<div class="row">
            <div class="col">
              
              <div class="list-group">
                <button id="thingsToDoButton" type="button" class="list-group-item list-group-item-action active">
                  Things To Do
                </button>
                <?php

$negativeCount = getNegativeCountForOutlet($outletObject->id);
$pendingBanking = getPendingBanking($outletObject->id);

if ($outletObject->website_outlet_id == 13) {
  $websiteProductsToAdd = ProductsNeedingAddedToWebsiteCount();
} else {
  $websiteProductsToAdd = 0; 
}

$stockTransfers = getStockTransfers($outletObject->id);
//$lowBatteries = getLowBatteryProducts($outletObject->id);
$lowBatteries = array();
$stockTransfersPendingDelivery = getStockTransfersWaitingForDelivery($outletObject->id);
$incomingOrders = getIncomingOrders($outletObject->id);
$flaggedProducts = getFlaggedProductCount($outletObject->id);

$ordersToProcess = null;

if ($outletObject->website_outlet_id == 2) {
  $juiceTransfersProcessing = getJuiceTransfersProcessing($outletObject->id);
} else {
  $juiceTransfersProcessing = 0;
}

$juiceTransfersPendingDelivery = getJuiceTransfersPendingDelivery($outletObject->id);
$websiteV2ShipmentCount = getOutletShipmentCountVapeShed($outletObject->website_outlet_id);
$nicCheckRequired = nicotineCheckRequired($outletObject->id);
$pendingStaffTransferToPack = getStorePendingStaffTransfers($outletObject->id, true, null);
$pendingStaffTransferToReceive = getStorePendingStaffTransfers($outletObject->id, null, true);
$bankingCount = getBankingInSafeQty($outletObject->id);
$faultyProductsCheck = checkLatestStaffProductFaults($outletObject->id);
$pettyCashTotal = getOutletTotalUnreconciledPettyCash($outletObject->id);

if ($bankingCount >= $outletObject->banking_days_allocated) {
  echo '<a href="//staff.vapeshed.co.nz/take-holdings-to-bank.php?outletID=' . $outletObject->id . '"> <button style=" background-color: #ff0000; color: #fff; " type="button" class="list-group-item list-group-item-action">Banking is Overdue ('.($bankingCount - $outletObject->banking_days_allocated).' Days)</button></a>';
}

foreach ($pendingBanking as $p) {
  echo '<a href="closure-reconciliation.php?closureID=' . $p->id . '"> 
    <button style=" background-color: #607D8B; color: #fff; " type="button" class="list-group-item list-group-item-action">Register Closure Submission #' . $p->open_count_sequence . ' Required</button></a>';
}

if ($nicCheckRequired && $outletObject->id != "0a4735cc-4971-11e7-fc9e-e474383c52ab") {
  echo '<a href="#"> <button type="button" class="list-group-item list-group-item-action" onclick="openNicotineModal(\'' . $outletObject->id . '\',\'' . $outletObject->name . '\');"><strong style="color:red;">Nicotine Check Required</strong></button></a>';
}

if ($websiteV2ShipmentCount > 0) {
  echo '<a href="//staff.vapeshed.co.nz/orders-overview-outlet.php?outletID=' . $outletObject->website_outlet_id . '&status=6"> <button type="button" class="list-group-item list-group-item-action"  style=" background: #65c70a; color: white; ">' . $websiteV2ShipmentCount . ' Web Orders To Be Processed</button></a>';
}

if (!empty($juiceTransfersProcessing->total) && $juiceTransfersProcessing->total > 0) {
if (count($juiceTransfersProcessing->transfers) > 2){
echo '<button onclick="$(\'#juiceTransferList\').slideToggle();" style=" background-color: #ff0000; color: #fff; " type="button" class="list-group-item list-group-item-action">' . $juiceTransfersProcessing->total . ' Bottles Due For Bottling</button>';
echo '<div id="juiceTransferList" style="display:none;">';
foreach ($juiceTransfersProcessing->transfers as $transfer) {
echo ' <a href="view-juice-transfer.php?transfer=' . $transfer->id . '"> <button style=" background-color: #ff0000; color: #fff; " type="button" class="list-group-item list-group-item-action">' . $transfer->total_bottles . ' Bottles Due For Bottling To ' . $transfer->outlet_to->name . '</button></a>';
}
echo "</div>";
}else{
foreach ($juiceTransfersProcessing->transfers as $transfer) {
echo ' <a href="view-juice-transfer.php?transfer=' . $transfer->id . '"> <button style=" background-color: #ff0000; color: #fff; " type="button" class="list-group-item list-group-item-action">' . $transfer->total_bottles . ' Bottles Due For Bottling To ' . $transfer->outlet_to->name . '</button></a>';
}
} 
}

if ($juiceTransfersPendingDelivery > 0) {
  foreach ($juiceTransfersPendingDelivery as $transfer) {
$blink = "";
$additionalText = "";
if ($transfer->nicotine_in_shipment == 1){
$blink = "blink_me";
$additionalText = " - <span style='color:#fff;background:red;'>NICOTINE SHIPMENT</span>";
}
    echo ' <a href="receive-juice-transfer.php?transfer=' . $transfer->id . '"> <button style=" background-color: #e407ff; color: #fff; " type="button" class="'.$blink.' list-group-item list-group-item-action">INCOMING Bottle Transfer #' . $transfer->id . ' From ' . $transfer->outlet_from->name . ' '.$additionalText.'</button></a>';
  }
}

if ($negativeCount > 0) { 
  echo '<a href="fix-negative-count.php?outletID='.$outletObject->id.'"> <button type="button" class="list-group-item list-group-item-action">'.$negativeCount.' Products have a Negative Inventory Count</button></a>';

}

if ($ordersToProcess > 0) {
  echo '<a target="_blank" href="https://www.vapeshed.co.nz/admin"> <button type="button" class="list-group-item list-group-item-action">' . $ordersToProcess . ' Website Orders To Be Processed</button></a>';
}

if ($outletObject->website_outlet_id == 13 && $websiteProductsToAdd > 0) {
  echo '<a href="products-to-add-to-website.php"> <button type="button" class="list-group-item list-group-item-action">'.$websiteProductsToAdd.' Products need to be added to the website</button></a>';
}

if ($stockTransfers > 0) {

if (count($stockTransfers) > 2){
echo '<button onclick="$(\'#' . $outletObject->id . '-outgoingstock\').slideToggle();" type="button" style="color: black;background-color: #ffca00;" class="list-group-item list-group-item-action">'.count($stockTransfers).'x OUTGOING Stock Transfers</button>';
echo '<div id="' . $outletObject->id . '-outgoingstock" style="display:none;">';
for ($ii = 0; $ii < count($stockTransfers); $ii++) {
echo '<a href="view-stock-transfer.php?transfer=' . $stockTransfers[$ii]["transfer_id"] . '"> <button style=" background: #ffca00; color: black; " type="button" class="list-group-item list-group-item-action">OUTGOING Stock Transfer #' . $stockTransfers[$ii]["transfer_id"] . ' To: <strong>' . $stockTransfers[$ii]["OutletDestinationTo"] . '</strong></button></a>';
}
echo "</div>";
}else{
for ($ii = 0; $ii < count($stockTransfers); $ii++) {
echo '<a href="view-stock-transfer.php?transfer=' . $stockTransfers[$ii]["transfer_id"] . '"> <button style=" background: #ffca00; color: black; " type="button" class="list-group-item list-group-item-action">OUTGOING Stock Transfer #' . $stockTransfers[$ii]["transfer_id"] . ' To: <strong>' . $stockTransfers[$ii]["OutletDestinationTo"] . '</strong></button></a>';
}
} 

  
}

if ($stockTransfersPendingDelivery > 0) {
  for ($ii = 0; $ii < count($stockTransfersPendingDelivery); $ii++) {
    if (isset($stockTransfersPendingDelivery[$ii]["tracking_number"]) && strlen($stockTransfersPendingDelivery[$ii]["tracking_number"]) > 0) {
      $icon = "<img src='assets/img/courier.png' style='width:20px' title='" . $stockTransfersPendingDelivery[$ii]["tracking_number"] . "'";
    } else {
      $icon = "<img src='assets/img/van.png' style='width:20px' title='Being Delivered via Vape Shed Van'";
    }
    echo '<a href="receive-stock-transfer.php?transfer=' . $stockTransfersPendingDelivery[$ii]["transfer_id"] . '"> <button style="background-color: #a2ff07; color: black;" type="button" class="list-group-item list-group-item-action">INCOMING Transfer #' . $stockTransfersPendingDelivery[$ii]["transfer_id"] . ' From: <strong>' . $stockTransfersPendingDelivery[$ii]["destinationFrom"] . '</strong>' . $icon . '</button></a>';
  }
}

if ($lowBatteries > 0) {
  for ($ii = 0; $ii < count($lowBatteries); $ii++) {
    echo '<a href="#"> <button type="button" class="list-group-item list-group-item-action" data-toggle="modal" data-target="#exampleModal"><strong style="color:red;">LOW BATTERY WARNING: ' . $lowBatteries[$ii]["inventory_level"] . ' In Stock</strong><br>' . $lowBatteries[$ii]["name"] . '</button></a>';
  }
}

if ($incomingOrders > 0) {

if (count($incomingOrders) > 2){
echo '<button onclick="$(\'#' . $outletObject->id . '-incomingOrders\').slideToggle();" style=" color: #ff7800; " type="button" class="list-group-item list-group-item-action">' . count($incomingOrders) . 'x Incoming Purchase Orders</button>';
echo '<div id="' . $outletObject->id . '-incomingOrders" style="display:none;">';
for ($ii = 0; $ii < count($incomingOrders); $ii++) {
echo '<a href="receive-purchase-order.php?id=' . $incomingOrders[$ii]->id . '"> <button type="button" style="color:#ff7800;" class="list-group-item list-group-item-action">Incoming Order #' . $incomingOrders[$ii]->id . ': ' . $incomingOrders[$ii]->name . '</button></a>';
}
echo "</div>";
}else{
for ($ii = 0; $ii < count($incomingOrders); $ii++) {
echo '<a href="receive-purchase-order.php?id=' . $incomingOrders[$ii]->id . '"> <button type="button" style="color:#ff7800;" class="list-group-item list-group-item-action">Incoming Order #' . $incomingOrders[$ii]->id . ': ' . $incomingOrders[$ii]->name . '</button></a>';
}
} 

  
}

if ($flaggedProducts > 0) {
  echo '<a href="flagged-products.php?outletID=' . $outletObject->id . '"> <button type="button" class="list-group-item list-group-item-action">' . $flaggedProducts . ' Products Flagged For Stocktake</button></a>';
}

if (count($pendingStaffTransferToPack) > 0) {
  for ($ii = 0; $ii < count($pendingStaffTransferToPack); $ii++) {
    echo '<a href="//staff.vapeshed.co.nz/view-staff-transfer.php?shipmentID=' . $pendingStaffTransferToPack[$ii]->id . '"> <button type="button" style="color: #ffffff;background-color: #ff0081;" class="list-group-item list-group-item-action">Outgoing Internal Transfer #' . $pendingStaffTransferToPack[$ii]->transferID . ' To ' . $pendingStaffTransferToPack[$ii]->name . '</button></a>';
  }
}

if (count($pendingStaffTransferToReceive) > 0) {
  for ($ii = 0; $ii < count($pendingStaffTransferToReceive); $ii++) {
    echo '<a href="receive-staff-transfer.php?shipmentID=' . $pendingStaffTransferToReceive[$ii]->id . '"> <button type="button" style="color: #ffffff;background-color: #929292;" class="list-group-item list-group-item-action">INCOMING Internal Transfer #' . $pendingStaffTransferToReceive[$ii]->transferID . ' From ' . $pendingStaffTransferToReceive[$ii]->name . '</button></a>';
  }
}

if ($outletObject->website_outlet_id == 2){

$outletsCollection = getAllOutletsFromDB();

  foreach($outletsCollection as $o){

    if ($o->website_outlet_id == 2){
      continue;
    }

    $nic = getLatestNicotineMLFromOutletCheck($o->id);

    if (isset($nic->ml) && $nic->ml <= 400){
      echo '<button style=" color: #fff; background-color: #ff8888; " onclick="alert(\'Send at next earliest convinience.\n\nThis warning will not go away until the store marks the nicotine level back in stock.\n\nMake sure is not double sent.\')" type="button" class="list-group-item list-group-item-action">Low Nicotine Warning - '.$o->name.' : '.$nic->ml.'ml</button>';
    }
  }
}

if (!is_null($pettyCashTotal) && $pettyCashTotal > 0){
echo '<a href="https://staff.vapeshed.co.nz/cash-expenses.php?outletID='.$outletObject->id.'" style="  background: #27b085;color: #fff; " type="button" class="list-group-item list-group-item-action faultyProductButton">$'.formatPrice($pettyCashTotal).' Petty Cash Needs Reconciling</a>';

}

if (!is_null($faultyProductsCheck)) {

$lastFaultProduct = "Never Submitted";
if ($faultyProductsCheck->lastReturn != null){
$lastFaultProduct = getDaysAgoRaw($faultyProductsCheck->lastReturn->time_created);
}

echo '<button data-lastfault=\''.$lastFaultProduct.'\' onclick="opencheckProductReturnsModal(\'' . $outletObject->id . '\',\'' . $outletObject->name . '\',this);" style="  background: #9c27b0;color: #fff; " type="button" class="list-group-item list-group-item-action faultyProductButton">Faulty Return Products Check</button>';
}?>
              </div>
            </div>
          </div>