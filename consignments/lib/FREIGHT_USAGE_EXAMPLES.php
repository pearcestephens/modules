<?php
/**
 * Example Usage: Consignments Freight Integration
 * 
 * This shows how to use the FreightIntegration class in your pack.php page
 * 
 * @example How to integrate into modules/consignments/stock-transfers/pack.php
 */

// ============================================================================
// EXAMPLE 1: Get Weight & Volume Metrics
// ============================================================================

require_once __DIR__ . '/../lib/FreightIntegration.php';

$freight = new \CIS\Modules\Consignments\FreightIntegration($pdo);

try {
    $metrics = $freight->calculateTransferMetrics($transfer_id);
    
    echo "Weight: {$metrics['weight']['total_weight_kg']} kg<br>";
    echo "Volume: {$metrics['volume']['total_volume_m3']} m³<br>";
    
    // Show warnings if any products missing weight/dimensions
    if (!empty($metrics['weight']['warnings'])) {
        foreach ($metrics['weight']['warnings'] as $warning) {
            echo "<div class='alert alert-warning'>{$warning}</div>";
        }
    }
    
} catch (\Exception $e) {
    echo "<div class='alert alert-danger'>Error: {$e->getMessage()}</div>";
}

// ============================================================================
// EXAMPLE 2: Get Freight Quotes
// ============================================================================

try {
    $quotes = $freight->getTransferRates($transfer_id);
    
    echo "<h3>Available Freight Options</h3>";
    echo "<table class='table'>";
    echo "<tr><th>Carrier</th><th>Service</th><th>Price</th><th>ETA</th></tr>";
    
    foreach ($quotes['rates'] as $rate) {
        echo "<tr>";
        echo "<td>{$rate['carrier']}</td>";
        echo "<td>{$rate['service']}</td>";
        echo "<td>\${$rate['price']}</td>";
        echo "<td>{$rate['eta_days']} days</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Show AI recommendation
    $rec = $quotes['recommended'];
    echo "<div class='alert alert-success'>";
    echo "<strong>✨ Recommended:</strong> {$rec['carrier']} - {$rec['service']} (\${$rec['price']})";
    echo "<br><small>{$rec['reason']}</small>";
    echo "</div>";
    
} catch (\Exception $e) {
    echo "<div class='alert alert-danger'>Error: {$e->getMessage()}</div>";
}

// ============================================================================
// EXAMPLE 3: Suggest Containers (Smart Packing)
// ============================================================================

try {
    $containers = $freight->suggestTransferContainers($transfer_id, 'min_cost');
    
    echo "<h3>🎁 Suggested Packing</h3>";
    echo "<p>Total Boxes: <strong>{$containers['total_boxes']}</strong></p>";
    echo "<p>Estimated Cost: <strong>\${$containers['total_cost']}</strong></p>";
    echo "<p>Utilization: <strong>{$containers['utilization_pct']}%</strong></p>";
    
    echo "<table class='table'>";
    echo "<tr><th>Container Type</th><th>Qty</th><th>Dimensions</th><th>Cost</th></tr>";
    
    foreach ($containers['containers'] as $c) {
        $dims = "{$c['dimensions_cm']['length']}x{$c['dimensions_cm']['width']}x{$c['dimensions_cm']['height']} cm";
        echo "<tr>";
        echo "<td>{$c['label']}</td>";
        echo "<td>{$c['quantity']}</td>";
        echo "<td>{$dims}</td>";
        echo "<td>\${$c['total_price']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (\Exception $e) {
    echo "<div class='alert alert-danger'>Error: {$e->getMessage()}</div>";
}

// ============================================================================
// EXAMPLE 4: Create Label (When user clicks "Create Label")
// ============================================================================

if (isset($_POST['create_label'])) {
    $carrier = $_POST['carrier']; // 'nzpost' or 'gss'
    $service = $_POST['service']; // e.g., 'COURIERPOST_EXPRESS'
    $auto_print = isset($_POST['auto_print']);
    
    try {
        $label = $freight->createTransferLabel(
            $transfer_id,
            $carrier,
            $service,
            $auto_print
        );
        
        echo "<div class='alert alert-success'>";
        echo "<strong>✅ Label Created!</strong><br>";
        echo "Tracking Number: <strong>{$label['tracking_number']}</strong><br>";
        echo "<a href='{$label['label_url']}' target='_blank' class='btn btn-primary'>Download Label PDF</a>";
        echo "</div>";
        
        // Transfer status is automatically updated to 'shipped'
        
    } catch (\Exception $e) {
        echo "<div class='alert alert-danger'>Failed to create label: {$e->getMessage()}</div>";
    }
}

// ============================================================================
// EXAMPLE 5: Track Shipment
// ============================================================================

try {
    $tracking = $freight->trackTransferShipment($transfer_id);
    
    echo "<h3>📦 Shipment Tracking</h3>";
    echo "<p>Status: <strong>{$tracking['status']}</strong></p>";
    
    if ($tracking['delivered']) {
        echo "<div class='alert alert-success'>✅ Delivered!</div>";
    } else {
        echo "<p>Estimated Delivery: {$tracking['estimated_delivery']}</p>";
    }
    
    echo "<h4>Tracking Events</h4>";
    echo "<ul>";
    foreach ($tracking['events'] as $event) {
        echo "<li><strong>{$event['timestamp']}</strong> - {$event['location']}: {$event['description']}</li>";
    }
    echo "</ul>";
    
} catch (\Exception $e) {
    // No tracking info yet
    echo "<p class='text-muted'>No tracking information available</p>";
}

// ============================================================================
// JAVASCRIPT AJAX EXAMPLE
// ============================================================================
?>

<script>
// AJAX: Get quotes without page reload
async function getFreightQuotes(transferId) {
    const response = await fetch('/assets/services/core/freight/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_rates&transfer_id=${transferId}`
    });
    
    const result = await response.json();
    
    if (result.success) {
        displayQuotes(result.data);
    } else {
        alert('Error: ' + result.error.message);
    }
}

function displayQuotes(data) {
    let html = '<h4>Freight Options</h4><table class="table">';
    html += '<tr><th>Carrier</th><th>Service</th><th>Price</th><th>ETA</th><th></th></tr>';
    
    data.rates.forEach(rate => {
        html += `<tr>
            <td>${rate.carrier}</td>
            <td>${rate.service}</td>
            <td>$${rate.price}</td>
            <td>${rate.eta_days} days</td>
            <td><button onclick="createLabel('${rate.carrier}', '${rate.service}')" class="btn btn-sm btn-primary">Select</button></td>
        </tr>`;
    });
    
    html += '</table>';
    
    // Show recommendation
    const rec = data.recommended;
    html += `<div class="alert alert-info">
        <strong>✨ AI Recommends:</strong> ${rec.carrier} - ${rec.service} ($${rec.price})
        <br><small>${rec.reason}</small>
    </div>`;
    
    document.getElementById('freight-quotes').innerHTML = html;
}

async function createLabel(carrier, service) {
    const transferId = document.getElementById('transfer_id').value;
    const autoPrint = confirm('Auto-print label?');
    
    const response = await fetch('/assets/services/core/freight/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=create_label&transfer_id=${transferId}&carrier=${carrier}&service=${service}&auto_print=${autoPrint?1:0}`
    });
    
    const result = await response.json();
    
    if (result.success) {
        alert(`✅ Label created! Tracking: ${result.data.tracking_number}`);
        window.open(result.data.label_url, '_blank');
        location.reload(); // Refresh to show updated status
    } else {
        alert('Error: ' + result.error.message);
    }
}
</script>
