<?php
/**
 * Transaction List View
 *
 * Displays filterable, paginated list of all bank transactions
 */

// Set page variables for base template
$pageTitle = 'Bank Transactions - Transaction List';
$pageCSS = [
    '/modules/bank-transactions/assets/css/transactions.css'
];
$pageJS = [
    '/modules/bank-transactions/assets/js/transaction-list.js'
];

// Include base dashboard template
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/dashboard.php';
?>

<!-- Transaction List Content -->
<div class="transaction-list-wrapper">

    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-list-ul text-primary me-2"></i>
                    Transaction List
                </h1>
                <p class="page-subtitle text-muted">
                    View, filter, and manage all bank transactions
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" id="bulk-actions-toggle">
                    <i class="fas fa-tasks me-1"></i> Bulk Actions
                </button>
                <button type="button" class="btn btn-success" id="export-transactions">
                    <i class="fas fa-file-export me-1"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Filters Panel -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" id="filters-form">
                <div class="row g-3">

                    <!-- Search -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control"
                                id="search"
                                name="search"
                                placeholder="Reference, name, description..."
                                value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-12 col-md-6 col-lg-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="unmatched" <?= ($filters['status'] ?? '') === 'unmatched' ? 'selected' : '' ?>>
                                Unmatched
                            </option>
                            <option value="matched" <?= ($filters['status'] ?? '') === 'matched' ? 'selected' : '' ?>>
                                Matched
                            </option>
                            <option value="review" <?= ($filters['status'] ?? '') === 'review' ? 'selected' : '' ?>>
                                Needs Review
                            </option>
                            <option value="voided" <?= ($filters['status'] ?? '') === 'voided' ? 'selected' : '' ?>>
                                Voided
                            </option>
                        </select>
                    </div>

                    <!-- Type Filter -->
                    <div class="col-12 col-md-6 col-lg-2">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All Types</option>
                            <option value="store_deposit" <?= ($filters['type'] ?? '') === 'store_deposit' ? 'selected' : '' ?>>
                                Store Deposit
                            </option>
                            <option value="retail_customer" <?= ($filters['type'] ?? '') === 'retail_customer' ? 'selected' : '' ?>>
                                Retail Customer
                            </option>
                            <option value="wholesale_customer" <?= ($filters['type'] ?? '') === 'wholesale_customer' ? 'selected' : '' ?>>
                                Wholesale Customer
                            </option>
                            <option value="eftpos_settlement" <?= ($filters['type'] ?? '') === 'eftpos_settlement' ? 'selected' : '' ?>>
                                EFTPOS Settlement
                            </option>
                            <option value="other" <?= ($filters['type'] ?? '') === 'other' ? 'selected' : '' ?>>
                                Other
                            </option>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div class="col-12 col-md-6 col-lg-2">
                        <label for="date_from" class="form-label">Date From</label>
                        <input
                            type="date"
                            class="form-control"
                            id="date_from"
                            name="date_from"
                            value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>"
                        >
                    </div>

                    <!-- Date To -->
                    <div class="col-12 col-md-6 col-lg-2">
                        <label for="date_to" class="form-label">Date To</label>
                        <input
                            type="date"
                            class="form-control"
                            id="date_to"
                            name="date_to"
                            value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>"
                        >
                    </div>

                    <!-- Store Filter -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label for="store_id" class="form-label">Store</label>
                        <select class="form-select" id="store_id" name="store_id">
                            <option value="">All Stores</option>
                            <?php if (isset($stores) && is_array($stores)): ?>
                                <?php foreach ($stores as $store): ?>
                                    <option
                                        value="<?= $store['id'] ?>"
                                        <?= ($filters['store_id'] ?? '') == $store['id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($store['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Filter Buttons -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label d-none d-md-block">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="clear-filters">
                                <i class="fas fa-times me-1"></i> Clear
                            </button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions Bar (hidden by default) -->
    <div class="card mb-4 d-none" id="bulk-actions-bar">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong id="bulk-selected-count">0</strong> transactions selected
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-primary" id="bulk-auto-match">
                        <i class="fas fa-magic me-1"></i> Auto-Match Selected
                    </button>
                    <button type="button" class="btn btn-sm btn-info" id="bulk-send-review">
                        <i class="fas fa-eye me-1"></i> Send to Review
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" id="bulk-deselect">
                        <i class="fas fa-times me-1"></i> Deselect All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-body">

            <?php if (empty($transactions)): ?>
                <!-- No Results -->
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No transactions found matching your filters.</p>
                    <button type="button" class="btn btn-outline-primary" id="clear-filters-empty">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </button>
                </div>
            <?php else: ?>
                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover" id="transactions-table">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="select-all">
                                </th>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Transaction Name</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $txn): ?>
                                <tr data-transaction-id="<?= $txn['id'] ?>" class="transaction-row">
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="form-check-input transaction-checkbox"
                                            value="<?= $txn['id'] ?>"
                                        >
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($txn['transaction_date'])) ?>
                                        <?php if ($txn['transaction_time']): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= date('H:i', strtotime($txn['transaction_time'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars($txn['transaction_reference']) ?></code>
                                        <?php if ($txn['bag_number']): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-shopping-bag"></i>
                                                Bag #<?= htmlspecialchars($txn['bag_number']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($txn['transaction_name']) ?></strong>
                                        <?php if ($txn['transaction_description']): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars(substr($txn['transaction_description'], 0, 50)) ?>
                                                <?= strlen($txn['transaction_description']) > 50 ? '...' : '' ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $typeColors = [
                                            'store_deposit' => 'primary',
                                            'retail_customer' => 'success',
                                            'wholesale_customer' => 'info',
                                            'eftpos_settlement' => 'warning',
                                            'other' => 'secondary'
                                        ];
                                        $typeLabels = [
                                            'store_deposit' => 'Store Deposit',
                                            'retail_customer' => 'Retail',
                                            'wholesale_customer' => 'Wholesale',
                                            'eftpos_settlement' => 'EFTPOS',
                                            'other' => 'Other'
                                        ];
                                        $color = $typeColors[$txn['transaction_type']] ?? 'secondary';
                                        $label = $typeLabels[$txn['transaction_type']] ?? 'Unknown';
                                        ?>
                                        <span class="badge bg-<?= $color ?>">
                                            <?= $label ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <strong class="<?= $txn['transaction_amount'] < 0 ? 'text-danger' : 'text-success' ?>">
                                            $<?= number_format(abs($txn['transaction_amount']), 2) ?>
                                        </strong>
                                        <?php if ($txn['transaction_amount'] < 0): ?>
                                            <span class="text-danger">(DR)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'unmatched' => 'warning',
                                            'matched' => 'success',
                                            'review' => 'info',
                                            'voided' => 'danger',
                                            'duplicate' => 'secondary'
                                        ];
                                        $statusLabels = [
                                            'unmatched' => 'Unmatched',
                                            'matched' => 'Matched',
                                            'review' => 'Review',
                                            'voided' => 'Voided',
                                            'duplicate' => 'Duplicate'
                                        ];
                                        $statusColor = $statusColors[$txn['status']] ?? 'secondary';
                                        $statusLabel = $statusLabels[$txn['status']] ?? 'Unknown';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>">
                                            <?= $statusLabel ?>
                                        </span>
                                        <?php if ($txn['confidence_score']): ?>
                                            <br>
                                            <small class="text-muted">
                                                Score: <?= $txn['confidence_score'] ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a
                                                href="/modules/bank-transactions/detail?id=<?= $txn['id'] ?>"
                                                class="btn btn-outline-primary"
                                                title="View Details"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($txn['status'] === 'unmatched' || $txn['status'] === 'review'): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-success auto-match-btn"
                                                    data-transaction-id="<?= $txn['id'] ?>"
                                                    title="Auto-Match"
                                                >
                                                    <i class="fas fa-magic"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav aria-label="Transaction pagination" class="mt-4">
                        <ul class="pagination justify-content-center">

                            <!-- Previous -->
                            <li class="page-item <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>">
                                <a
                                    class="page-link"
                                    href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['current_page'] - 1])) ?>"
                                >
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php
                            $start = max(1, $pagination['current_page'] - 2);
                            $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
                            ?>

                            <?php if ($start > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => 1])) ?>">
                                        1
                                    </a>
                                </li>
                                <?php if ($start > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                                    <a
                                        class="page-link"
                                        href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"
                                    >
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end < $pagination['total_pages']): ?>
                                <?php if ($end < $pagination['total_pages'] - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a
                                        class="page-link"
                                        href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['total_pages']])) ?>"
                                    >
                                        <?= $pagination['total_pages'] ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Next -->
                            <li class="page-item <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">
                                <a
                                    class="page-link"
                                    href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['current_page'] + 1])) ?>"
                                >
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>

                        </ul>
                    </nav>

                    <!-- Pagination Info -->
                    <div class="text-center text-muted mt-2">
                        Showing <?= number_format($pagination['from']) ?> to <?= number_format($pagination['to']) ?>
                        of <?= number_format($pagination['total']) ?> transactions
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>

</div>

<!-- CSRF Token -->
<input type="hidden" id="csrf-token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
