<?php
session_start();
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/scraper.php';

$admin_password = getenv('SESSION_SECRET');
if (!$admin_password) {
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        if (preg_match('/SESSION_SECRET=(.+)/', $envContent, $matches)) {
            $admin_password = trim($matches[1]);
        }
    }
}
if (!$admin_password) {
    die("Admin password not configured. Please set SESSION_SECRET in .env file or run install.php again.");
}
$message = '';
$messageType = 'success';

if (isset($_POST['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: admin.php');
    exit;
}

if (isset($_POST['login'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $message = "Invalid password!";
        $messageType = 'error';
    }
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="hi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - Satta King</title>
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/admin.css">
    </head>
    <body class="admin-login-page">
        <div class="login-container">
            <div class="login-logo">
                <h1>Satta <span>King</span></h1>
            </div>
            <h2 class="login-title">Admin Login</h2>
            <?php if ($message): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter admin password" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary btn-block">Login</button>
            </form>
            <p class="login-footer"><a href="index.php">Back to Home</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$csrf_token = $_SESSION['csrf_token'];
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $message = "Invalid request!";
        $messageType = 'error';
    } elseif (isset($_POST['add_result'])) {
        $game = $_POST['game_name'];
        $result = $_POST['result'];
        $time = $_POST['result_time'];
        
        $checkStmt = $pdo->prepare("SELECT id FROM satta_results WHERE game_name = ? AND result_date = CURRENT_DATE");
        $checkStmt->execute([$game]);
        
        if ($checkStmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE satta_results SET result = ?, result_time = ? WHERE game_name = ? AND result_date = CURRENT_DATE");
            $stmt->execute([$result, $time, $game]);
            $message = "Result updated successfully!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO satta_results (game_name, result, result_time) VALUES (?, ?, ?)");
            $stmt->execute([$game, $result, $time]);
            $message = "Result added successfully!";
        }
    } elseif (isset($_POST['add_game'])) {
        $name = $_POST['new_game_name'];
        $time = $_POST['game_time'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO games (name, time_slot) VALUES (?, ?)");
            $stmt->execute([$name, $time]);
            $message = "Game added successfully!";
        } catch(PDOException $e) {
            $message = "Game already exists!";
            $messageType = 'error';
        }
    } elseif (isset($_POST['scrape_data'])) {
        $scrapeUrl = trim($_POST['scrape_url']);
        if (!empty($scrapeUrl) && filter_var($scrapeUrl, FILTER_VALIDATE_URL)) {
            $scraper = new SattaScraper($pdo);
            $result = $scraper->scrape($scrapeUrl);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $message = $result['message'];
                $messageType = 'error';
            }
        } else {
            $message = "Please enter a valid URL!";
            $messageType = 'error';
        }
    } elseif (isset($_POST['import_paste'])) {
        $pastedContent = trim($_POST['pasted_content']);
        if (!empty($pastedContent)) {
            $scraper = new SattaScraper($pdo);
            $result = $scraper->parseFromPastedContent($pastedContent, 'pasted_content');
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $message = $result['message'];
                $messageType = 'error';
            }
        } else {
            $message = "Please paste some content!";
            $messageType = 'error';
        }
    } elseif (isset($_POST['update_page'])) {
        $pageId = (int)$_POST['page_id'];
        $content = $_POST['content'];
        $metaTitle = $_POST['meta_title'];
        $metaDesc = $_POST['meta_description'];
        
        $stmt = $pdo->prepare("UPDATE site_pages SET content = ?, meta_title = ?, meta_description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$content, $metaTitle, $metaDesc, $pageId]);
        $message = "Page updated successfully!";
    } elseif (isset($_POST['update_settings'])) {
        $analyticsCode = $_POST['google_analytics_code'] ?? '';
        $googleVerify = $_POST['meta_verification_google'] ?? '';
        $bingVerify = $_POST['meta_verification_bing'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
        $stmt->execute([$analyticsCode, 'google_analytics_code']);
        $stmt->execute([$googleVerify, 'meta_verification_google']);
        $stmt->execute([$bingVerify, 'meta_verification_bing']);
        $message = "Settings saved successfully!";
    } elseif (isset($_POST['save_news_post'])) {
        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']);
        $excerpt = trim($_POST['excerpt']);
        $content = $_POST['content'];
        $metaTitle = trim($_POST['meta_title']);
        $metaDescription = trim($_POST['meta_description']);
        $metaKeywords = trim($_POST['meta_keywords']);
        $status = $_POST['status'];
        $featuredImage = trim($_POST['featured_image']);
        
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)));
            $slug = trim($slug, '-');
        }
        
        if ($postId > 0) {
            $slugCheck = $pdo->prepare("SELECT COUNT(*) FROM news_posts WHERE slug = ? AND id != ?");
            $slugCheck->execute([$slug, $postId]);
            if ($slugCheck->fetchColumn() > 0) {
                $slug .= '-' . time();
            }
            
            $stmt = $pdo->prepare("UPDATE news_posts SET title = ?, slug = ?, excerpt = ?, content = ?, featured_image = ?, meta_title = ?, meta_description = ?, meta_keywords = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$title, $slug, $excerpt, $content, $featuredImage, $metaTitle, $metaDescription, $metaKeywords, $status, $postId]);
            
            if ($status === 'published') {
                $checkPublished = $pdo->prepare("SELECT published_at FROM news_posts WHERE id = ?");
                $checkPublished->execute([$postId]);
                $existingPublished = $checkPublished->fetchColumn();
                if (empty($existingPublished)) {
                    $pdo->prepare("UPDATE news_posts SET published_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$postId]);
                }
            }
            $message = "Post updated successfully!";
        } else {
            $slugCheck = $pdo->prepare("SELECT COUNT(*) FROM news_posts WHERE slug = ?");
            $slugCheck->execute([$slug]);
            if ($slugCheck->fetchColumn() > 0) {
                $slug .= '-' . time();
            }
            
            $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare("INSERT INTO news_posts (title, slug, excerpt, content, featured_image, meta_title, meta_description, meta_keywords, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $excerpt, $content, $featuredImage, $metaTitle, $metaDescription, $metaKeywords, $status, $publishedAt]);
            $message = "Post created successfully!";
        }
    } elseif (isset($_POST['delete_news_post'])) {
        $postId = intval($_POST['post_id']);
        $pdo->prepare("DELETE FROM news_posts WHERE id = ?")->execute([$postId]);
        $message = "Post deleted successfully!";
    } elseif (isset($_POST['publish_daily_update'])) {
        $publishDate = date('Y-m-d');
        $dateFormatTitle = date('j M Y', strtotime($publishDate));
        $dateFormatUrl = strtolower(date('j-F-Y', strtotime($publishDate)));
        
        $gamesWithResults = $pdo->query("
            SELECT DISTINCT game_name FROM satta_results 
            WHERE result_date = CURRENT_DATE AND result IS NOT NULL AND result != ''
            ORDER BY game_name
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($gamesWithResults) == 0) {
            $message = "No results available for today to publish!";
            $messageType = 'error';
        } else {
            $publishedCount = 0;
            $skippedCount = 0;
            
            foreach ($gamesWithResults as $gameName) {
                $slugGame = strtolower(str_replace(' ', '-', $gameName));
                $slugGame = preg_replace('/[^a-z0-9\-]/', '', $slugGame);
                
                $existingPost = $pdo->prepare("SELECT id FROM posts WHERE post_date = ? AND games_included = ?");
                $existingPost->execute([$publishDate, $gameName]);
                $existing = $existingPost->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $skippedCount++;
                    continue;
                }
                
                $title = "{$gameName} Satta King Result {$dateFormatTitle}";
                $baseSlug = "{$slugGame}-satta-king-result-{$dateFormatUrl}";
                $baseSlug = preg_replace('/-+/', '-', $baseSlug);
                $slug = $baseSlug;
                
                $slugCheck = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE slug = ?");
                $slugCheck->execute([$slug]);
                if ($slugCheck->fetchColumn() > 0) {
                    $slug = $baseSlug . '-' . time();
                }
                
                $metaDesc = "Check {$gameName} Satta King Result for {$dateFormatTitle}. Get live {$gameName} result, chart, and fast updates. Today's Satta King {$gameName} number.";
                $metaKeywords = "{$gameName} satta king result, {$gameName} result {$dateFormatTitle}, {$gameName} satta result today, satta king {$gameName}, {$gameName} chart, {$gameName} satta king";
                
                $insertStmt = $pdo->prepare("
                    INSERT INTO posts (title, slug, meta_description, meta_keywords, games_included, post_date) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$title, $slug, $metaDesc, $metaKeywords, $gameName, $publishDate]);
                $publishedCount++;
            }
            
            if ($publishedCount > 0) {
                $message = "{$publishedCount} game posts published successfully!";
                if ($skippedCount > 0) {
                    $message .= " ({$skippedCount} already existed)";
                }
            } else {
                $message = "All game posts already published for today!";
                $messageType = 'error';
            }
        }
    }
}

$scraper = new SattaScraper($pdo);
$scrapeLogs = $scraper->getLastScrapeLog();
$games = $pdo->query("SELECT * FROM games WHERE is_active = 1 ORDER BY time_slot")->fetchAll(PDO::FETCH_ASSOC);
$todayResults = $pdo->query("SELECT * FROM satta_results WHERE result_date = CURRENT_DATE ORDER BY result_time")->fetchAll(PDO::FETCH_ASSOC);
$totalGames = $pdo->query("SELECT COUNT(*) FROM games WHERE is_active = 1")->fetchColumn();
$totalResults = $pdo->query("SELECT COUNT(*) FROM satta_results WHERE result_date = CURRENT_DATE")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Satta King</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-body">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <h2>Satta <span>King</span></h2>
            </div>
            <button class="sidebar-close" onclick="toggleSidebar()">×</button>
        </div>
        
        <nav class="sidebar-nav">
            <ul>
                <li class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <a href="admin.php?page=dashboard">
                        <span class="nav-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'scrape' ? 'active' : ''; ?>">
                    <a href="admin.php?page=scrape">
                        <span class="nav-icon">🔄</span>
                        <span>Import Data</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'results' ? 'active' : ''; ?>">
                    <a href="admin.php?page=results">
                        <span class="nav-icon">📝</span>
                        <span>Add Result</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'games' ? 'active' : ''; ?>">
                    <a href="admin.php?page=games">
                        <span class="nav-icon">🎮</span>
                        <span>Manage Games</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'today' ? 'active' : ''; ?>">
                    <a href="admin.php?page=today">
                        <span class="nav-icon">📅</span>
                        <span>Today's Results</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'logs' ? 'active' : ''; ?>">
                    <a href="admin.php?page=logs">
                        <span class="nav-icon">📋</span>
                        <span>Scrape Logs</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'posts' ? 'active' : ''; ?>">
                    <a href="admin.php?page=posts">
                        <span class="nav-icon">📰</span>
                        <span>Daily Update</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'news' ? 'active' : ''; ?>">
                    <a href="admin.php?page=news">
                        <span class="nav-icon">📝</span>
                        <span>News Posts</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'ads' ? 'active' : ''; ?>">
                    <a href="admin.php?page=ads">
                        <span class="nav-icon">💰</span>
                        <span>Ad Management</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'pages' ? 'active' : ''; ?>">
                    <a href="admin.php?page=pages">
                        <span class="nav-icon">📄</span>
                        <span>Footer Pages</span>
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <a href="admin.php?page=settings">
                        <span class="nav-icon">⚙️</span>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <a href="index.php" class="sidebar-link">
                <span class="nav-icon">🏠</span>
                <span>View Website</span>
            </a>
            <form method="POST" style="margin-top: 10px;">
                <button type="submit" name="logout" class="btn btn-logout">
                    <span class="nav-icon">🚪</span>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-overlay" id="adminOverlay" onclick="toggleSidebar()"></div>

    <main class="admin-main">
        <header class="admin-topbar">
            <button class="topbar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">
                <?php 
                $titles = [
                    'dashboard' => 'Dashboard',
                    'scrape' => 'Import Data',
                    'results' => 'Add/Update Result',
                    'games' => 'Manage Games',
                    'today' => "Today's Results",
                    'logs' => 'Scrape Logs',
                    'posts' => 'Daily Update Posts',
                    'news' => 'News Posts',
                    'ads' => 'Ad Management',
                    'pages' => 'Footer Pages',
                    'settings' => 'Settings'
                ];
                echo $titles[$currentPage] ?? 'Dashboard';
                ?>
            </h1>
            <div class="topbar-info">
                <span class="topbar-date"><?php echo date('d M Y'); ?></span>
            </div>
        </header>

        <div class="admin-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($currentPage === 'dashboard'): ?>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">🎮</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $totalGames; ?></span>
                        <span class="stat-label">Total Games</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $totalResults; ?></span>
                        <span class="stat-label">Today's Results</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔄</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo count($scrapeLogs); ?></span>
                        <span class="stat-label">Recent Imports</span>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <h3 class="card-title">Quick Actions</h3>
                <div class="quick-actions">
                    <a href="admin.php?page=scrape" class="action-btn action-import">Import Data</a>
                    <a href="admin.php?page=results" class="action-btn action-add">Add Result</a>
                    <a href="admin.php?page=games" class="action-btn action-game">Add Game</a>
                    <a href="index.php" class="action-btn action-view" target="_blank">View Site</a>
                </div>
            </div>

            <?php if (!empty($scrapeLogs)): ?>
            <div class="admin-card">
                <h3 class="card-title">Recent Import Activity</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Records</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($scrapeLogs, 0, 3) as $log): ?>
                        <tr>
                            <td><?php echo date('d/m h:i A', strtotime($log['scraped_at'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $log['status']; ?>">
                                    <?php echo ucfirst($log['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $log['records_updated']; ?></td>
                            <td class="message-cell"><?php echo htmlspecialchars(substr($log['message'], 0, 50)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php elseif ($currentPage === 'scrape'): ?>
            <div class="admin-card">
                <h3 class="card-title">Scrape Data from URL</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-group">
                        <label>Enter URL to Scrape</label>
                        <input type="url" name="scrape_url" value="https://satta-king-fast.com/" placeholder="https://satta-king-fast.com/" required>
                    </div>
                    <p class="form-hint">Enter the URL and click Scrape. Today's data will be updated, historical data will be preserved.</p>
                    <button type="submit" name="scrape_data" class="btn btn-success">Scrape Data</button>
                </form>
            </div>

            <div class="admin-card">
                <h3 class="card-title">Paste HTML Data</h3>
                <p class="form-hint">If URL scraping doesn't work (Cloudflare protection), paste the HTML table data directly from the website.</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-group">
                        <label>Paste Data</label>
                        <textarea name="pasted_content" rows="10" class="form-textarea" placeholder="Paste the HTML table data from satta-king-fast.com here..."></textarea>
                    </div>
                    <button type="submit" name="import_paste" class="btn btn-warning">Import Pasted Data</button>
                </form>
            </div>

            <?php elseif ($currentPage === 'results'): ?>
            <div class="admin-card">
                <h3 class="card-title">Add/Update Result</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-group">
                        <label>Select Game</label>
                        <select name="game_name" required>
                            <option value="">-- Select Game --</option>
                            <?php foreach ($games as $game): ?>
                                <option value="<?php echo htmlspecialchars($game['name']); ?>">
                                    <?php echo htmlspecialchars($game['name']); ?> (<?php echo date('h:i A', strtotime($game['time_slot'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Result (2 digits)</label>
                            <input type="text" name="result" maxlength="2" pattern="[0-9]{2}" placeholder="e.g., 45" required>
                        </div>
                        <div class="form-group">
                            <label>Result Time</label>
                            <input type="time" name="result_time" required>
                        </div>
                    </div>
                    <button type="submit" name="add_result" class="btn btn-primary">Add/Update Result</button>
                </form>
            </div>

            <?php elseif ($currentPage === 'games'): ?>
            <div class="admin-card">
                <h3 class="card-title">Add New Game</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Game Name</label>
                            <input type="text" name="new_game_name" placeholder="e.g., Mumbai King" required>
                        </div>
                        <div class="form-group">
                            <label>Result Time</label>
                            <input type="time" name="game_time" required>
                        </div>
                    </div>
                    <button type="submit" name="add_game" class="btn btn-primary">Add Game</button>
                </form>
            </div>

            <div class="admin-card">
                <h3 class="card-title">All Games (<?php echo count($games); ?>)</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Game Name</th>
                            <th>Time Slot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $game): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($game['name']); ?></td>
                            <td><?php echo date('h:i A', strtotime($game['time_slot'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($currentPage === 'today'): ?>
            <div class="admin-card">
                <h3 class="card-title">Today's Results (<?php echo count($todayResults); ?>)</h3>
                <?php if (count($todayResults) > 0): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Game</th>
                                <th>Result</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayResults as $result): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['game_name']); ?></td>
                                    <td class="result-highlight"><?php echo htmlspecialchars($result['result']); ?></td>
                                    <td><?php echo date('h:i A', strtotime($result['result_time'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">No results added yet for today.</p>
                <?php endif; ?>
            </div>

            <?php elseif ($currentPage === 'logs'): ?>
            <div class="admin-card">
                <h3 class="card-title">Scrape History</h3>
                <?php if (!empty($scrapeLogs)): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Records</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scrapeLogs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y h:i A', strtotime($log['scraped_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $log['status']; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $log['records_updated']; ?></td>
                                <td><?php echo htmlspecialchars($log['message']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">No scrape logs yet.</p>
                <?php endif; ?>
            </div>

            <?php elseif ($currentPage === 'posts'): 
                $publishedPosts = $pdo->query("SELECT * FROM posts ORDER BY post_date DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
                $todayGamesCount = $pdo->query("SELECT COUNT(DISTINCT game_name) FROM satta_results WHERE result_date = CURRENT_DATE AND result IS NOT NULL AND result != ''")->fetchColumn();
            ?>
            <div class="admin-card">
                <h3 class="card-title">Publish Daily Update Post</h3>
                <p class="form-hint">Click the button below to auto-generate and publish today's Satta King results post. Only games with results will be included.</p>
                
                <div style="background: rgba(16, 185, 129, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <p style="color: #10b981; margin-bottom: 10px;"><strong>Today's Stats:</strong></p>
                    <p style="color: #d1d5db;">Games with results: <strong style="color: #ffd700;"><?php echo $todayGamesCount; ?></strong></p>
                    <p style="color: #d1d5db;">Date: <strong style="color: #ffd700;"><?php echo date('d M Y'); ?></strong></p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="submit" name="publish_daily_update" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 1.1rem;">
                        📰 Publish Daily Update Post
                    </button>
                </form>
                
                <p style="color: #9ca3af; font-size: 0.85rem; margin-top: 15px; text-align: center;">
                    Post will be created with full SEO including meta tags, keywords, and sitemap entry.
                </p>
            </div>

            <div class="admin-card">
                <h3 class="card-title">Published Posts</h3>
                <?php if (!empty($publishedPosts)): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Game</th>
                                <th>Views</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($publishedPosts as $post): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($post['post_date'])); ?></td>
                                <td><?php echo htmlspecialchars($post['games_included']); ?></td>
                                <td style="color: #ffd700; font-weight: 600;"><?php echo number_format($post['views'] ?? 0); ?></td>
                                <td>
                                    <a href="/post/<?php echo htmlspecialchars($post['slug']); ?>" target="_blank" style="color: #60a5fa;">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">No posts published yet. Click the button above to publish your first daily update.</p>
                <?php endif; ?>
            </div>

            <div class="admin-card">
                <h3 class="card-title">Sitemap</h3>
                <p style="color: #d1d5db; margin-bottom: 15px;">Your sitemap is automatically generated and includes all published posts.</p>
                <a href="/sitemap.php" target="_blank" class="btn btn-primary">View Sitemap</a>
            </div>

            <?php elseif ($currentPage === 'ads'): 
                if (isset($_POST['save_ad'])) {
                    $adName = $_POST['ad_name'];
                    $adCode = $_POST['ad_code'];
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    $updateAd = $pdo->prepare("UPDATE ad_placements SET ad_code = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE placement_name = ?");
                    $updateAd->execute([$adCode, $isActive, $adName]);
                    $message = "Ad placement updated successfully!";
                }
                $adPlacements = $pdo->query("SELECT * FROM ad_placements ORDER BY placement_name")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="admin-card">
                <h3 class="card-title">Ad Placements</h3>
                <p class="form-hint">Add your AdSense or custom ad code to each placement. Leave empty to disable.</p>
                
                <div style="background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #3b82f6;">
                    <p style="color: #60a5fa; font-size: 0.9rem;"><strong>Available Placements:</strong></p>
                    <ul style="color: #9ca3af; font-size: 0.85rem; margin-left: 20px; margin-top: 10px;">
                        <li><strong>header_ad</strong> - Top of page (after header)</li>
                        <li><strong>after_result</strong> - Below result tables</li>
                        <li><strong>sidebar_ad</strong> - Sidebar area (desktop)</li>
                        <li><strong>footer_ad</strong> - Before footer</li>
                        <li><strong>between_posts</strong> - Between post listings</li>
                    </ul>
                </div>
            </div>

            <?php foreach ($adPlacements as $ad): ?>
            <div class="admin-card">
                <h3 class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <?php echo ucwords(str_replace('_', ' ', $ad['placement_name'])); ?>
                    <span style="font-size: 0.8rem; padding: 4px 10px; border-radius: 20px; background: <?php echo $ad['is_active'] && !empty($ad['ad_code']) ? 'rgba(16,185,129,0.2)' : 'rgba(107,114,128,0.2)'; ?>; color: <?php echo $ad['is_active'] && !empty($ad['ad_code']) ? '#10b981' : '#6b7280'; ?>;">
                        <?php echo $ad['is_active'] && !empty($ad['ad_code']) ? 'Active' : 'Inactive'; ?>
                    </span>
                </h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="ad_name" value="<?php echo htmlspecialchars($ad['placement_name']); ?>">
                    
                    <div class="form-group">
                        <label>Ad Code (AdSense or Custom HTML)</label>
                        <textarea name="ad_code" class="form-textarea" rows="4" placeholder="Paste your ad code here..."><?php echo htmlspecialchars($ad['ad_code']); ?></textarea>
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="is_active" id="active_<?php echo $ad['placement_name']; ?>" <?php echo $ad['is_active'] ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                        <label for="active_<?php echo $ad['placement_name']; ?>" style="margin-bottom: 0; color: #d1d5db;">Enable this ad placement</label>
                    </div>
                    
                    <button type="submit" name="save_ad" class="btn btn-primary">Save Ad</button>
                </form>
            </div>
            <?php endforeach; ?>

            <?php elseif ($currentPage === 'pages'): 
                $sitePages = $pdo->query("SELECT * FROM site_pages ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
                $editPageId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
                $editPage = null;
                if ($editPageId > 0) {
                    $editPageStmt = $pdo->prepare("SELECT * FROM site_pages WHERE id = ?");
                    $editPageStmt->execute([$editPageId]);
                    $editPage = $editPageStmt->fetch(PDO::FETCH_ASSOC);
                }
            ?>
            
            <?php if ($editPage): ?>
            <div class="admin-card">
                <h3 class="card-title">Edit Page: <?php echo htmlspecialchars($editPage['title']); ?></h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="page_id" value="<?php echo $editPage['id']; ?>">
                    
                    <div class="form-group">
                        <label>Page Title</label>
                        <input type="text" value="<?php echo htmlspecialchars($editPage['title']); ?>" disabled>
                        <p class="form-hint">Page title cannot be changed</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Meta Title (SEO)</label>
                        <input type="text" name="meta_title" value="<?php echo htmlspecialchars($editPage['meta_title']); ?>" placeholder="Meta title for search engines">
                    </div>
                    
                    <div class="form-group">
                        <label>Meta Description (SEO)</label>
                        <textarea name="meta_description" class="form-textarea" rows="2" placeholder="Description for search engines"><?php echo htmlspecialchars($editPage['meta_description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Page Content</label>
                        <textarea name="content" class="form-textarea" rows="10" placeholder="Enter page content..."><?php echo htmlspecialchars($editPage['content']); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_page" class="btn btn-primary">Save Changes</button>
                    <a href="admin.php?page=pages" class="btn btn-warning" style="margin-left: 10px;">Cancel</a>
                </form>
            </div>
            <?php else: ?>
            <div class="admin-card">
                <h3 class="card-title">Footer Pages</h3>
                <p class="form-hint">Manage your website footer pages - About, Contact, Disclaimer, Privacy Policy, Terms & Conditions</p>
                
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Page Title</th>
                            <th>URL Slug</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sitePages as $sitePage): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sitePage['title']); ?></td>
                            <td style="color: #60a5fa;">/page/<?php echo htmlspecialchars($sitePage['slug']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($sitePage['updated_at'])); ?></td>
                            <td>
                                <a href="admin.php?page=pages&edit=<?php echo $sitePage['id']; ?>" style="color: #fbbf24; margin-right: 15px;">Edit</a>
                                <a href="/page/<?php echo htmlspecialchars($sitePage['slug']); ?>" target="_blank" style="color: #60a5fa;">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php elseif ($currentPage === 'news'): 
                $editNewsPost = null;
                if (isset($_GET['edit'])) {
                    $editStmt = $pdo->prepare("SELECT * FROM news_posts WHERE id = ?");
                    $editStmt->execute([intval($_GET['edit'])]);
                    $editNewsPost = $editStmt->fetch(PDO::FETCH_ASSOC);
                }
                $isNewPost = isset($_GET['new']);
                $allNewsPosts = $pdo->query("SELECT * FROM news_posts ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <?php if ($editNewsPost || $isNewPost): ?>
            <div class="admin-card">
                <h3 class="card-title"><?php echo $editNewsPost ? 'Edit Post' : 'Create New Post'; ?></h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <?php if ($editNewsPost): ?>
                    <input type="hidden" name="post_id" value="<?php echo $editNewsPost['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Post Title *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($editNewsPost['title'] ?? ''); ?>" placeholder="Enter post title" required>
                    </div>
                    
                    <div class="form-group">
                        <label>URL Slug</label>
                        <input type="text" name="slug" value="<?php echo htmlspecialchars($editNewsPost['slug'] ?? ''); ?>" placeholder="auto-generated-from-title">
                        <p class="form-hint">Leave empty to auto-generate from title</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Featured Image URL</label>
                        <input type="text" name="featured_image" value="<?php echo htmlspecialchars($editNewsPost['featured_image'] ?? ''); ?>" placeholder="https://example.com/image.jpg">
                        <p class="form-hint">Enter full image URL or upload to /uploads/news/ folder</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Excerpt (Short Description)</label>
                        <textarea name="excerpt" class="form-textarea" rows="2" placeholder="Brief description for listings..."><?php echo htmlspecialchars($editNewsPost['excerpt'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Content (HTML supported) *</label>
                        <textarea name="content" class="form-textarea" rows="15" placeholder="<h2>Heading</h2>&#10;<p>Your paragraph text here...</p>&#10;<img src='image.jpg' alt='description'>&#10;<a href='link'>Link text</a>"><?php echo htmlspecialchars($editNewsPost['content'] ?? ''); ?></textarea>
                        <p class="form-hint">Use HTML tags: &lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;img&gt;, &lt;a&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;em&gt;</p>
                    </div>
                    
                    <div style="background: rgba(59, 130, 246, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h4 style="color: #60a5fa; margin-bottom: 15px;">SEO Settings</h4>
                        
                        <div class="form-group">
                            <label>Meta Title</label>
                            <input type="text" name="meta_title" value="<?php echo htmlspecialchars($editNewsPost['meta_title'] ?? ''); ?>" placeholder="SEO title for search engines">
                        </div>
                        
                        <div class="form-group">
                            <label>Meta Description</label>
                            <textarea name="meta_description" class="form-textarea" rows="2" placeholder="Description for search engines (150-160 characters)"><?php echo htmlspecialchars($editNewsPost['meta_description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Meta Keywords</label>
                            <input type="text" name="meta_keywords" value="<?php echo htmlspecialchars($editNewsPost['meta_keywords'] ?? ''); ?>" placeholder="keyword1, keyword2, keyword3">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?php echo ($editNewsPost['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo ($editNewsPost['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="save_news_post" class="btn btn-success">Save Post</button>
                    <a href="admin.php?page=news" class="btn btn-warning" style="margin-left: 10px;">Cancel</a>
                </form>
            </div>
            <?php else: ?>
            <div class="admin-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 class="card-title" style="margin: 0;">All News Posts</h3>
                    <a href="admin.php?page=news&new=1" class="btn btn-success">+ Create New Post</a>
                </div>
                
                <?php if (count($allNewsPosts) > 0): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allNewsPosts as $newsPost): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($newsPost['title']); ?></td>
                            <td>
                                <?php if ($newsPost['status'] === 'published'): ?>
                                <span style="color: #34d399;">Published</span>
                                <?php else: ?>
                                <span style="color: #fbbf24;">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($newsPost['views']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($newsPost['created_at'])); ?></td>
                            <td>
                                <a href="admin.php?page=news&edit=<?php echo $newsPost['id']; ?>" style="color: #fbbf24; margin-right: 10px;">Edit</a>
                                <?php if ($newsPost['status'] === 'published'): ?>
                                <a href="/news/<?php echo htmlspecialchars($newsPost['slug']); ?>" target="_blank" style="color: #60a5fa; margin-right: 10px;">View</a>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this post?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="post_id" value="<?php echo $newsPost['id']; ?>">
                                    <button type="submit" name="delete_news_post" style="background: none; border: none; color: #ef4444; cursor: pointer;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state" style="text-align: center; padding: 40px; color: #9ca3af;">
                    <p style="font-size: 48px; margin-bottom: 10px;">📝</p>
                    <p>No news posts yet. Create your first post!</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php elseif ($currentPage === 'settings'): 
                $settings = [];
                $settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
                while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            ?>
            <div class="admin-card">
                <h3 class="card-title">Google Analytics & Verification</h3>
                <p class="form-hint">Add your Google Analytics tracking code and verification meta tags for search engines.</p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label>Google Analytics Code</label>
                        <textarea name="google_analytics_code" class="form-textarea" rows="6" placeholder="Paste your Google Analytics script here...&#10;Example:&#10;<script async src='https://www.googletagmanager.com/gtag/js?id=GA_TRACKING_ID'></script>&#10;<script>...</script>"><?php echo htmlspecialchars($settings['google_analytics_code'] ?? ''); ?></textarea>
                        <p class="form-hint">Paste the complete Google Analytics or GTM script code. This will be added to all pages.</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Google Search Console Verification</label>
                        <input type="text" name="meta_verification_google" value="<?php echo htmlspecialchars($settings['meta_verification_google'] ?? ''); ?>" placeholder="Content value from Google meta tag">
                        <p class="form-hint">Enter only the content value from the Google verification meta tag</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Bing Webmaster Verification</label>
                        <input type="text" name="meta_verification_bing" value="<?php echo htmlspecialchars($settings['meta_verification_bing'] ?? ''); ?>" placeholder="Content value from Bing meta tag">
                        <p class="form-hint">Enter only the content value from the Bing verification meta tag</p>
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn btn-success">Save Settings</button>
                </form>
            </div>
            
            <div class="admin-card">
                <h3 class="card-title">SEO Tips</h3>
                <div style="background: rgba(59, 130, 246, 0.1); padding: 20px; border-radius: 10px; border: 1px solid #3b82f6;">
                    <ul style="color: #d1d5db; line-height: 1.8; margin-left: 20px;">
                        <li>Submit your sitemap (<a href="/sitemap.xml" target="_blank" style="color: #60a5fa;">/sitemap.xml</a>) to Google Search Console</li>
                        <li>Add Google Analytics to track your website visitors</li>
                        <li>Enable all Ad Placements for maximum revenue</li>
                        <li>Publish Daily Updates regularly to keep content fresh</li>
                        <li>Complete all Footer Pages (About, Contact, Disclaimer, Privacy, Terms)</li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('active');
            document.getElementById('adminOverlay').classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        }
    </script>
</body>
</html>
