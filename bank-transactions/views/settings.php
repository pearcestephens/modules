<?php
/**
 * Settings View
 */
?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Bank Transactions Settings</h1>
            <p class="text-muted">Configure matching rules, automation, and notifications.</p>
            <div class="card">
                <div class="card-header">
                    <h5>Configuration Options</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/modules/bank-transactions/api/settings.php">
                        <div class="form-group mb-3">
                            <label>Auto-Match Threshold (%)</label>
                            <input type="number" class="form-control" name="auto_match_threshold" value="85" min="0" max="100">
                        </div>
                        <div class="form-group mb-3">
                            <label>
                                <input type="checkbox" name="auto_match_enabled" checked>
                                Enable Automatic Matching
                            </label>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
?>
