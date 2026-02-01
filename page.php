<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/ads.php';

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (empty($slug)) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM site_pages WHERE slug = ? AND is_published = 1");
$stmt->execute([$slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    header('Location: index.php');
    exit;
}

$analyticsStmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'google_analytics_code'");
$analyticsStmt->execute();
$analyticsCode = $analyticsStmt->fetchColumn();

$adsenseStmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'adsense_auto_ads'");
$adsenseStmt->execute();
$adsenseAutoAds = $adsenseStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['meta_title'] ?: $page['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page['meta_description']); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="/page/<?php echo htmlspecialchars($page['slug']); ?>">
    <link rel="stylesheet" href="/css/style.css">
    <?php if (!empty($analyticsCode)): ?>
    <?php echo $analyticsCode; ?>
    <?php endif; ?>
    <?php if (!empty($adsenseAutoAds)): ?>
    <?php echo $adsenseAutoAds; ?>
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
                        <li><a href="/index.php">Home</a></li>
                        <li><a href="/daily-updates.php">Daily Update</a></li>
                        <li><a href="news.php">News</a></li>
                        <li><a href="/chart.php">Chart</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <?php displayAd($pdo, 'header_ad'); ?>
        
        <section class="page-content">
            <h1 class="page-title"><?php echo htmlspecialchars($page['title']); ?></h1>
            <div class="page-body">
                <?php echo nl2br(htmlspecialchars($page['content'])); ?>
            </div>
            <p class="page-updated">Last Updated: <?php echo date('d M Y', strtotime($page['updated_at'])); ?></p>
        </section>
        
        <?php displayAd($pdo, 'footer_ad'); ?>
    </main>

    <footer>
        <div class="container">
            <div class="footer-links">
                <a href="/page/about">About</a>
                <a href="/page/contact">Contact</a>
                <a href="/page/disclaimer">Disclaimer</a>
                <a href="/page/privacy-policy">Privacy Policy</a>
                <a href="/page/terms-conditions">Terms & Conditions</a>
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
