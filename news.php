<?php
require_once 'config/database.php';

$settings = [];
$settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

$totalPosts = $pdo->query("SELECT COUNT(*) FROM news_posts WHERE status = 'published'")->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);

$stmt = $pdo->query("SELECT * FROM news_posts WHERE status = 'published' ORDER BY published_at DESC LIMIT " . intval($perPage) . " OFFSET " . intval($offset));
$newsPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satta King News - Latest Updates & Articles</title>
    <meta name="description" content="Read the latest Satta King news, updates, tips and articles. Stay informed with our latest posts.">
    <meta name="keywords" content="satta king news, satta king updates, satta king articles, satta king tips">
    <link rel="canonical" href="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/news'; ?>">
    
    <?php if (!empty($settings['meta_verification_google'])): ?>
    <meta name="google-site-verification" content="<?php echo htmlspecialchars($settings['meta_verification_google']); ?>">
    <?php endif; ?>
    <?php if (!empty($settings['meta_verification_bing'])): ?>
    <meta name="msvalidate.01" content="<?php echo htmlspecialchars($settings['meta_verification_bing']); ?>">
    <?php endif; ?>
    
    <meta property="og:title" content="Satta King News - Latest Updates">
    <meta property="og:description" content="Read the latest Satta King news, updates, tips and articles.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/news'; ?>">
    
    <link rel="stylesheet" href="/css/style.css">
    <?php if (!empty($settings['google_analytics_code'])): ?>
    <?php echo $settings['google_analytics_code']; ?>
    <?php endif; ?>
</head>
<body>
    <header>
        <div class="container">
            <a href="/" class="logo">SATTA <span>KING</span></a>
            <button class="menu-toggle" onclick="toggleMenu()">☰</button>
            <nav id="mainNav">
                <a href="index.php">Home</a>
                <a href="daily-updates.php">Daily Update</a>
                <a href="news.php" class="active">News</a>
                <a href="chart.php">Chart</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="results-section">
            <h1 class="section-title">SATTA KING NEWS</h1>
            <p style="text-align: center; color: #9ca3af; margin-bottom: 30px;">Latest updates, tips and articles</p>
            
            <?php if (count($newsPosts) > 0): ?>
            <div class="posts-grid">
                <?php foreach ($newsPosts as $newsPost): ?>
                <a href="news-post.php?slug=<?php echo htmlspecialchars($newsPost['slug']); ?>" class="post-card">
                    <?php if (!empty($newsPost['featured_image'])): ?>
                    <div class="post-image" style="background-image: url('<?php echo htmlspecialchars($newsPost['featured_image']); ?>');"></div>
                    <?php else: ?>
                    <div class="post-image" style="background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%); display: flex; align-items: center; justify-content: center;">
                        <span style="font-size: 40px; color: #fbbf24;">📰</span>
                    </div>
                    <?php endif; ?>
                    <div class="post-content">
                        <h2 class="post-title"><?php echo htmlspecialchars($newsPost['title']); ?></h2>
                        <p class="post-excerpt"><?php echo htmlspecialchars(substr($newsPost['excerpt'] ?? '', 0, 100)); ?>...</p>
                        <div class="post-meta">
                            <span><?php echo date('d M Y', strtotime($newsPost['published_at'])); ?></span>
                            <span><?php echo number_format($newsPost['views']); ?> views</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="news.php?p=<?php echo $page - 1; ?>" class="page-link">« Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="news.php?p=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="news.php?p=<?php echo $page + 1; ?>" class="page-link">Next »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-state" style="text-align: center; padding: 60px 20px; color: #9ca3af;">
                <p style="font-size: 48px; margin-bottom: 20px;">📰</p>
                <p style="font-size: 18px;">No news posts published yet.</p>
                <p>Check back soon for updates!</p>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="footer-links">
                <a href="page.php?slug=about">About</a>
                <a href="page.php?slug=contact">Contact</a>
                <a href="page.php?slug=disclaimer">Disclaimer</a>
                <a href="page.php?slug=privacy-policy">Privacy Policy</a>
                <a href="page.php?slug=terms-conditions">Terms & Conditions</a>
            </div>
            <p>&copy; <?php echo date('Y'); ?> Satta King. All Rights Reserved.</p>
            <p>Satta King Fast Results | Delhi Satta King | Satta King 786 | Satta King Chart</p>
        </div>
    </footer>

    <script>
        function toggleMenu() {
            document.getElementById('mainNav').classList.toggle('active');
        }
    </script>
</body>
</html>
