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

if (file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
    return false;
}

if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
    return true;
}

return false;
