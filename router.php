<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

if (preg_match('#^/post/([^/]+)/?$#', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/post.php';
    return true;
}

if (preg_match('#^/page/([^/]+)/?$#', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/page.php';
    return true;
}

if ($path === '/news' || $path === '/news/') {
    require __DIR__ . '/news.php';
    return true;
}

if (preg_match('#^/news/([^/]+)/?$#', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/news-post.php';
    return true;
}

if ($path === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

if ($path === '/ads.txt') {
    require __DIR__ . '/config/database.php';
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute(['ads_txt_content']);
    $adsTxt = $stmt->fetchColumn() ?: '';
    header('Content-Type: text/plain; charset=utf-8');
    echo $adsTxt;
    return true;
}

if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "Disallow: /admin.php\n";
    echo "Disallow: /install.php\n";
    echo "Disallow: /config/\n\n";
    echo "Sitemap: https://{$host}/sitemap.xml\n";
    return true;
}

if (file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
    return false;
}

if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
    return true;
}

return false;
