<?php
header('Content-Type: application/xml; charset=utf-8');
require_once __DIR__ . '/config/database.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

$posts = $pdo->query("SELECT slug, updated_at, post_date FROM posts ORDER BY post_date DESC LIMIT 10000")->fetchAll(PDO::FETCH_ASSOC);
$games = $pdo->query("SELECT DISTINCT name FROM games WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
$staticPages = ['about', 'contact', 'disclaimer', 'privacy-policy', 'terms-conditions'];

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?php echo $baseUrl; ?>/</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?php echo $baseUrl; ?>/daily-updates.php</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?php echo $baseUrl; ?>/chart.php</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <?php foreach ($posts as $post): ?>
    <url>
        <loc><?php echo $baseUrl; ?>/post/<?php echo htmlspecialchars($post['slug']); ?></loc>
        <lastmod><?php echo date('Y-m-d', strtotime($post['updated_at'])); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>
    <?php foreach ($games as $game): ?>
    <url>
        <loc><?php echo $baseUrl; ?>/chart.php?game=<?php echo urlencode($game); ?></loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.6</priority>
    </url>
    <?php endforeach; ?>
    <?php foreach ($staticPages as $page): ?>
    <url>
        <loc><?php echo $baseUrl; ?>/page/<?php echo $page; ?></loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <?php endforeach; ?>
</urlset>
