<?php
require_once 'config/database.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: news.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM news_posts WHERE slug = ? AND status = 'published'");
$stmt->execute([$slug]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><title>404 - Post Not Found</title></head><body style="background:#020d1f;color:#fff;text-align:center;padding:100px;font-family:sans-serif;"><h1>404</h1><p>Post not found</p><a href="news.php" style="color:#fbbf24;">Back to News</a></body></html>';
    exit;
}

$pdo->prepare("UPDATE news_posts SET views = views + 1 WHERE id = ?")->execute([$post['id']]);
$post['views']++;

$settings = [];
$settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$relatedPosts = $pdo->prepare("SELECT id, title, slug, featured_image, published_at FROM news_posts WHERE status = 'published' AND id != ? ORDER BY published_at DESC LIMIT 3");
$relatedPosts->execute([$post['id']]);
$related = $relatedPosts->fetchAll(PDO::FETCH_ASSOC);

$metaTitle = $post['meta_title'] ?: $post['title'] . ' - Satta King News';
$metaDescription = $post['meta_description'] ?: substr(strip_tags($post['content']), 0, 160);
$metaKeywords = $post['meta_keywords'] ?: 'satta king news, satta king, satta result';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($metaTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($metaKeywords); ?>">
    <link rel="canonical" href="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/news/' . htmlspecialchars($post['slug']); ?>">
    
    <?php if (!empty($settings['meta_verification_google'])): ?>
    <meta name="google-site-verification" content="<?php echo htmlspecialchars($settings['meta_verification_google']); ?>">
    <?php endif; ?>
    
    <meta property="og:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/news/' . htmlspecialchars($post['slug']); ?>">
    <?php if (!empty($post['featured_image'])): ?>
    <meta property="og:image" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($post['featured_image']); ?>">
    <?php endif; ?>
    <meta property="article:published_time" content="<?php echo date('c', strtotime($post['published_at'])); ?>">
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": "<?php echo htmlspecialchars($post['title']); ?>",
        "description": "<?php echo htmlspecialchars($metaDescription); ?>",
        "datePublished": "<?php echo date('c', strtotime($post['published_at'])); ?>",
        "dateModified": "<?php echo date('c', strtotime($post['updated_at'])); ?>",
        <?php if (!empty($post['featured_image'])): ?>
        "image": "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($post['featured_image']); ?>",
        <?php endif; ?>
        "publisher": {
            "@type": "Organization",
            "name": "Satta King"
        }
    }
    </script>
    
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .article-content {
            background: linear-gradient(135deg, rgba(30, 58, 95, 0.3) 0%, rgba(15, 23, 42, 0.5) 100%);
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
        }
        .article-content h1 {
            color: #fbbf24;
            font-size: 2rem;
            margin-bottom: 20px;
            line-height: 1.3;
        }
        .article-content h2 {
            color: #60a5fa;
            font-size: 1.5rem;
            margin: 30px 0 15px 0;
        }
        .article-content h3 {
            color: #34d399;
            font-size: 1.2rem;
            margin: 25px 0 10px 0;
        }
        .article-content p {
            color: #d1d5db;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        .article-content img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin: 20px 0;
        }
        .article-content a {
            color: #fbbf24;
            text-decoration: underline;
        }
        .article-content ul, .article-content ol {
            color: #d1d5db;
            margin: 15px 0 15px 30px;
            line-height: 1.8;
        }
        .article-meta {
            display: flex;
            gap: 20px;
            color: #9ca3af;
            font-size: 14px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .featured-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .breadcrumb {
            color: #9ca3af;
            margin-bottom: 20px;
        }
        .breadcrumb a {
            color: #60a5fa;
            text-decoration: none;
        }
        .related-posts {
            margin-top: 40px;
        }
        .related-posts h3 {
            color: #fbbf24;
            margin-bottom: 20px;
        }
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .related-card {
            background: rgba(30, 58, 95, 0.3);
            border-radius: 10px;
            overflow: hidden;
            text-decoration: none;
            transition: transform 0.3s;
        }
        .related-card:hover {
            transform: translateY(-5px);
        }
        .related-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .related-card .card-body {
            padding: 15px;
        }
        .related-card h4 {
            color: #fff;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .related-card span {
            color: #9ca3af;
            font-size: 12px;
        }
        @media (max-width: 768px) {
            .article-content {
                padding: 20px;
            }
            .article-content h1 {
                font-size: 1.5rem;
            }
        }
    </style>
    <?php if (!empty($settings['google_analytics_code'])): ?>
    <?php echo $settings['google_analytics_code']; ?>
    <?php endif; ?>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1>Satta <span>King</span></h1>
                </div>
                <button class="mobile-menu" onclick="toggleMenu()">&#9776;</button>
                <nav id="mainNav">
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="daily-updates.php">Daily Update</a></li>
                        <li><a href="news.php" class="active">News</a></li>
                        <li><a href="chart.php">Chart</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="breadcrumb">
            <a href="index.php">Home</a> » <a href="news.php">News</a> » <?php echo htmlspecialchars($post['title']); ?>
        </div>
        
        <article class="article-content">
            <h1><?php echo htmlspecialchars($post['title']); ?></h1>
            
            <div class="article-meta">
                <span>📅 <?php echo date('d M Y', strtotime($post['published_at'])); ?></span>
                <span>👁 <?php echo number_format($post['views']); ?> views</span>
            </div>
            
            <?php if (!empty($post['featured_image'])): ?>
            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="featured-image">
            <?php endif; ?>
            
            <div class="post-body">
                <?php echo $post['content']; ?>
            </div>
        </article>
        
        <?php if (count($related) > 0): ?>
        <section class="related-posts">
            <h3>Related Posts</h3>
            <div class="related-grid">
                <?php foreach ($related as $relPost): ?>
                <a href="news-post.php?slug=<?php echo htmlspecialchars($relPost['slug']); ?>" class="related-card">
                    <?php if (!empty($relPost['featured_image'])): ?>
                    <img src="<?php echo htmlspecialchars($relPost['featured_image']); ?>" alt="<?php echo htmlspecialchars($relPost['title']); ?>">
                    <?php else: ?>
                    <div style="height: 150px; background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%); display: flex; align-items: center; justify-content: center;">
                        <span style="font-size: 40px;">📰</span>
                    </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h4><?php echo htmlspecialchars($relPost['title']); ?></h4>
                        <span><?php echo date('d M Y', strtotime($relPost['published_at'])); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
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
