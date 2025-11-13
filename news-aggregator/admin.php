<?php
/**
 * News Aggregator - Admin Dashboard
 *
 * Main admin interface for managing news sources and content moderation
 * Compatible with all CIS themes
 *
 * @package CIS_Themes
 * @subpackage NewsAggregator
 */

// Load dependencies
require_once __DIR__ . '/../../engine/ThemeEngine.php';
require_once __DIR__ . '/AdminController.php';

// TODO: Replace with real auth check
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
//     header('Location: /login.php');
//     exit;
// }

use CIS\NewsAggregator\AdminController;

// Database connection (adjust path as needed)
require_once dirname(__DIR__, 2) . '/config/database.php';
$db = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$admin = new AdminController($db);

// Handle actions
$action = $_GET['action'] ?? 'dashboard';
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'] ?? 1; // TODO: Get from session

    switch ($_POST['action']) {
        case 'create_source':
            if ($admin->createSource($_POST)) {
                $message = ['type' => 'success', 'text' => 'News source created successfully'];
            }
            break;

        case 'update_source':
            if ($admin->updateSource($_POST['id'], $_POST)) {
                $message = ['type' => 'success', 'text' => 'News source updated'];
            }
            break;

        case 'delete_source':
            if ($admin->deleteSource($_POST['id'])) {
                $message = ['type' => 'success', 'text' => 'News source deleted'];
            }
            break;

        case 'moderate_article':
            if ($admin->moderateArticle($_POST['id'], $_POST['status'], $userId)) {
                $message = ['type' => 'success', 'text' => 'Article moderated'];
            }
            break;

        case 'bulk_approve':
            $ids = explode(',', $_POST['ids']);
            if ($admin->bulkApprove($ids, $userId)) {
                $message = ['type' => 'success', 'text' => count($ids) . ' articles approved'];
            }
            break;

        case 'bulk_reject':
            $ids = explode(',', $_POST['ids']);
            if ($admin->bulkReject($ids, $userId)) {
                $message = ['type' => 'success', 'text' => count($ids) . ' articles rejected'];
            }
            break;

        case 'trigger_crawl':
            if ($admin->triggerManualCrawl($_POST['source_id'])) {
                $message = ['type' => 'success', 'text' => 'Crawl triggered - will run on next cron cycle'];
            }
            break;
    }

    // Redirect to prevent form resubmission
    if ($message) {
        $_SESSION['flash_message'] = $message;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?action=' . $action);
        exit;
    }
}

// Get flash message
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Get data based on action
$data = [];
switch ($action) {
    case 'dashboard':
        $data['stats'] = $admin->getDashboardStats();
        $data['recent_logs'] = $admin->getCrawlLogs(null, 20);
        break;

    case 'sources':
        $data['sources'] = $admin->getSources($_GET);
        break;

    case 'articles':
        $page = $_GET['page'] ?? 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $data['articles'] = $admin->getArticles($_GET, $perPage, $offset);
        $data['total'] = $admin->getArticleCount($_GET);
        $data['pages'] = ceil($data['total'] / $perPage);
        $data['current_page'] = $page;
        break;

    case 'logs':
        $sourceId = $_GET['source_id'] ?? null;
        $data['logs'] = $admin->getCrawlLogs($sourceId, 100);
        break;
}

// Page title
$titles = [
    'dashboard' => 'News Aggregator Dashboard',
    'sources' => 'Manage News Sources',
    'articles' => 'Content Moderation',
    'logs' => 'Crawl Logs',
];
$pageTitle = $titles[$action] ?? 'News Aggregator';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - CIS News Aggregator</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f7fa;
            color: #2d3748;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 1.75rem; font-weight: 600; margin-bottom: 0.5rem; }
        .header p { opacity: 0.9; font-size: 0.95rem; }

        /* Navigation */
        .nav {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 2rem;
        }
        .nav ul { list-style: none; display: flex; gap: 0; }
        .nav li a {
            display: block;
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #4a5568;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .nav li a:hover { background: #f7fafc; color: #667eea; }
        .nav li a.active { color: #667eea; border-bottom-color: #667eea; background: #f7fafc; }

        /* Container */
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }

        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card h3 { font-size: 0.875rem; color: #718096; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: #2d3748; }
        .stat-card .change { font-size: 0.875rem; color: #48bb78; margin-top: 0.5rem; }

        /* Table */
        .table-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .table-header { padding: 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { font-size: 1.25rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f7fafc; }
        th { text-align: left; padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: #4a5568; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; }
        tbody tr:hover { background: #f7fafc; }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #48bb78; color: white; }
        .btn-success:hover { background: #38a169; }
        .btn-danger { background: #f56565; color: white; }
        .btn-danger:hover { background: #e53e3e; }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #718096;
        }
        .empty-state svg { width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5; }
        .empty-state h3 { font-size: 1.25rem; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>📰 CIS News Aggregator</h1>
        <p>External content aggregation for Facebook Feed layouts</p>
    </div>

    <!-- Navigation -->
    <nav class="nav">
        <ul>
            <li><a href="?action=dashboard" class="<?= $action === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="?action=sources" class="<?= $action === 'sources' ? 'active' : '' ?>">News Sources</a></li>
            <li><a href="?action=articles" class="<?= $action === 'articles' ? 'active' : '' ?>">Content Moderation</a></li>
            <li><a href="?action=logs" class="<?= $action === 'logs' ? 'active' : '' ?>">Crawl Logs</a></li>
        </ul>
    </nav>

    <!-- Container -->
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message['type'] ?>">
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'dashboard'): ?>
            <!-- Dashboard Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Active Sources</h3>
                    <div class="value"><?= $data['stats']['active_sources'] ?></div>
                    <div class="change">of <?= $data['stats']['total_sources'] ?> total</div>
                </div>
                <div class="stat-card">
                    <h3>Total Articles</h3>
                    <div class="value"><?= number_format($data['stats']['total_articles']) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Review</h3>
                    <div class="value"><?= $data['stats']['pending_articles'] ?></div>
                    <div class="change"><?= $data['stats']['approved_articles'] ?> approved</div>
                </div>
                <div class="stat-card">
                    <h3>Crawls Today</h3>
                    <div class="value"><?= $data['stats']['crawls_today'] ?></div>
                    <div class="change"><?= $data['stats']['successful_crawls_today'] ?> successful</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="table-card">
                <div class="table-header">
                    <h2>Recent Crawl Activity</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Found</th>
                            <th>New</th>
                            <th>Time</th>
                            <th>Started</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['recent_logs'] as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['source_name']) ?></td>
                            <td>
                                <?php if ($log['status'] === 'success'): ?>
                                    <span class="badge badge-success">Success</span>
                                <?php elseif ($log['status'] === 'failed'): ?>
                                    <span class="badge badge-danger">Failed</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Running</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $log['articles_found'] ?></td>
                            <td><?= $log['articles_new'] ?></td>
                            <td><?= $log['execution_time'] ?>s</td>
                            <td><?= date('M j, g:i A', strtotime($log['started_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($action === 'sources'): ?>
            <!-- Sources List -->
            <div class="table-card">
                <div class="table-header">
                    <h2>News Sources</h2>
                    <button class="btn btn-primary" onclick="alert('Add source modal - TODO')">+ Add Source</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Articles</th>
                            <th>Pending</th>
                            <th>Last Crawled</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['sources'] as $source): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($source['name']) ?></strong></td>
                            <td><span class="badge badge-info"><?= strtoupper($source['type']) ?></span></td>
                            <td><?= htmlspecialchars($source['category']) ?></td>
                            <td>
                                <?php if ($source['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $source['article_count'] ?></td>
                            <td><?= $source['pending_count'] ?></td>
                            <td><?= $source['last_crawled_at'] ? date('M j, g:i A', strtotime($source['last_crawled_at'])) : 'Never' ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="trigger_crawl">
                                    <input type="hidden" name="source_id" value="<?= $source['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Crawl Now</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($action === 'articles'): ?>
            <!-- Articles List -->
            <div class="table-card">
                <div class="table-header">
                    <h2>Content Moderation (<?= number_format($data['total']) ?> articles)</h2>
                    <div>
                        <button class="btn btn-sm btn-success" onclick="alert('Bulk approve - TODO')">Bulk Approve</button>
                        <button class="btn btn-sm btn-danger" onclick="alert('Bulk reject - TODO')">Bulk Reject</button>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th width="40"><input type="checkbox" id="select-all"></th>
                            <th>Title</th>
                            <th>Source</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Published</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['articles'] as $article): ?>
                        <tr>
                            <td><input type="checkbox" name="article_ids[]" value="<?= $article['id'] ?>"></td>
                            <td>
                                <strong><?= htmlspecialchars(substr($article['title'], 0, 80)) ?></strong>
                                <?php if ($article['is_pinned']): ?>
                                    <span class="badge badge-warning">Pinned</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($article['source_name']) ?></td>
                            <td><?= htmlspecialchars($article['category']) ?></td>
                            <td>
                                <?php if ($article['status'] === 'approved'): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php elseif ($article['status'] === 'pending'): ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($article['published_at'])) ?></td>
                            <td>
                                <?php if ($article['status'] === 'pending'): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="moderate_article">
                                        <input type="hidden" name="id" value="<?= $article['id'] ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="moderate_article">
                                        <input type="hidden" name="id" value="<?= $article['id'] ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($data['pages'] > 1): ?>
                <div style="padding: 1.5rem; text-align: center;">
                    <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                        <a href="?action=articles&page=<?= $i ?>" class="btn btn-sm <?= $i === $data['current_page'] ? 'btn-primary' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'logs'): ?>
            <!-- Crawl Logs -->
            <div class="table-card">
                <div class="table-header">
                    <h2>Crawl Logs</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Found</th>
                            <th>New</th>
                            <th>Updated</th>
                            <th>Time</th>
                            <th>Started</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['logs'] as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['source_name']) ?></td>
                            <td>
                                <?php if ($log['status'] === 'success'): ?>
                                    <span class="badge badge-success">Success</span>
                                <?php elseif ($log['status'] === 'failed'): ?>
                                    <span class="badge badge-danger">Failed</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Running</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $log['articles_found'] ?></td>
                            <td><?= $log['articles_new'] ?></td>
                            <td><?= $log['articles_updated'] ?></td>
                            <td><?= $log['execution_time'] ?>s</td>
                            <td><?= date('M j, g:i A', strtotime($log['started_at'])) ?></td>
                            <td><?= $log['error_message'] ? '<span title="' . htmlspecialchars($log['error_message']) . '">❌</span>' : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
