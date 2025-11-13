<?php
/**
 * Personalised Menu Component (Theme-Level)
 * Lightweight placeholder for notifications, quick actions, and user personalization.
 * Later we can hydrate with live data (AI suggestions, recent items, etc.).
 */

$uid = $_SESSION['user_id'] ?? null;
$firstName = 'User';
if ($uid && function_exists('getUserInformation')) {
    try {
        $info = getUserInformation($uid);
        if (is_array($info)) { $firstName = $info['first_name'] ?? $firstName; }
        elseif (is_object($info)) { $firstName = $info->first_name ?? $firstName; }
    } catch (Throwable $e) { /* ignore for now */ }
}
?>
<div class="dropdown-menu dropdown-menu-right p-0 shadow-sm" aria-labelledby="notificationToggle" style="min-width:320px;">
  <div class="px-3 py-2 border-bottom bg-light">
    <strong>Welcome, <?php echo htmlspecialchars($firstName); ?></strong>
    <div class="small text-muted">Personalised shortcuts & insights coming soon.</div>
  </div>
  <div class="list-group list-group-flush">
    <a class="list-group-item list-group-item-action d-flex align-items-center" href="#" onclick="return false;">
      <i class="fas fa-bell mr-2 text-secondary"></i>
      <span class="flex-grow-1">No new notifications</span>
      <span class="badge badge-secondary">0</span>
    </a>
    <a class="list-group-item list-group-item-action d-flex align-items-center" href="#" onclick="return false;">
      <i class="fas fa-history mr-2 text-secondary"></i>
      <span class="flex-grow-1">Recent activity (stub)</span>
    </a>
    <a class="list-group-item list-group-item-action d-flex align-items-center" href="#" onclick="return false;">
      <i class="fas fa-magic mr-2 text-secondary"></i>
      <span class="flex-grow-1">AI suggestions (coming)</span>
    </a>
  </div>
  <div class="px-3 py-2 border-top bg-light text-right">
    <a href="/modules/consignments/basic-card.php" class="btn btn-sm btn-outline-primary">Demo Page</a>
  </div>
</div>
