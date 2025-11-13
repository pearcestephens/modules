<?php
/**
 * Simple Dashboard View - Example
 *
 * This demonstrates how clean views are with the template system.
 * The view file contains ONLY the main content.
 *
 * @package Consignments\Views\Examples
 */

require_once __DIR__ . '/template.php';

$template = new ConsignmentsTemplate();
$template->setTitle('Dashboard');
$template->setCurrentPage('consignments/dashboard');

// Start content
$template->startContent();
?>

<!-- This is ALL you need in your view file! -->

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h2 class="mb-0">
            <i class="fa fa-tachometer-alt"></i>
            Consignments Dashboard
        </h2>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card consignment-card">
            <div class="card-body text-center">
                <i class="fa fa-boxes fa-3x text-primary mb-3"></i>
                <h3>156</h3>
                <p class="text-muted">Total Consignments</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card consignment-card">
            <div class="card-body text-center">
                <i class="fa fa-truck fa-3x text-info mb-3"></i>
                <h3>23</h3>
                <p class="text-muted">In Transit</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card consignment-card">
            <div class="card-body text-center">
                <i class="fa fa-check-circle fa-3x text-success mb-3"></i>
                <h3>12</h3>
                <p class="text-muted">Completed Today</p>
            </div>
        </div>
    </div>
</div>

<div class="card consignment-card mt-4">
    <div class="card-header">
        <i class="fa fa-chart-line"></i>
        <strong>Recent Activity</strong>
    </div>
    <div class="card-body">
        <p>Recent consignment activity will appear here...</p>

        <button class="ai-button" onclick="showAIDemo()">
            <i class="fa fa-robot"></i>
            Ask AI About This Data
        </button>
    </div>
</div>

<script>
function showAIDemo() {
    ConsignmentsApp.toast('AI Assistant integration ready!', 'success');
}
</script>

<?php
// End content
$template->endContent();
?>
