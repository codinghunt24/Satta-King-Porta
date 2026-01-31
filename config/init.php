<?php
require_once __DIR__ . '/database.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS satta_results (
        id SERIAL PRIMARY KEY,
        game_name VARCHAR(100) NOT NULL,
        result VARCHAR(10),
        result_time TIME,
        result_date DATE,
        source_url TEXT,
        scraped_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (game_name, result_date)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS games (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        time_slot TIME NOT NULL,
        is_active SMALLINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS scrape_logs (
        id SERIAL PRIMARY KEY,
        source_url TEXT NOT NULL,
        status VARCHAR(20) NOT NULL,
        message TEXT,
        records_updated INT DEFAULT 0,
        scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS site_pages (
        id SERIAL PRIMARY KEY,
        slug VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(200) NOT NULL,
        content TEXT,
        meta_title VARCHAR(200),
        meta_description TEXT,
        is_published SMALLINT DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS site_settings (
        id SERIAL PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS news_posts (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        excerpt TEXT,
        content TEXT,
        featured_image VARCHAR(500),
        meta_title VARCHAR(200),
        meta_description TEXT,
        meta_keywords TEXT,
        status VARCHAR(20) DEFAULT 'draft',
        views INT DEFAULT 0,
        published_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS posts (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        meta_description TEXT,
        meta_keywords TEXT,
        games_included TEXT,
        post_date DATE,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS ad_placements (
        id SERIAL PRIMARY KEY,
        placement_name VARCHAR(50) NOT NULL UNIQUE,
        ad_code TEXT,
        is_active SMALLINT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$checkPages = $pdo->query("SELECT COUNT(*) FROM site_pages")->fetchColumn();
if ($checkPages == 0) {
    $pages = [
        ['about', 'About Us', 'Welcome to Satta King - your trusted source for fast and accurate Satta King results. We provide live updates for Gali, Disawar, Faridabad, Ghaziabad and many other games.', 'About Us - Satta King', 'Learn about Satta King website - your trusted source for fast Satta King results and charts.'],
        ['contact', 'Contact Us', 'For any queries or feedback, please contact us through our website. We value your feedback and will respond as soon as possible.', 'Contact Us - Satta King', 'Contact Satta King for queries, feedback or support.'],
        ['disclaimer', 'Disclaimer', 'This website is for informational purposes only. We do not promote or encourage any form of gambling or betting. Please check your local laws before participating in any games. All results are provided for entertainment purposes only.', 'Disclaimer - Satta King', 'Read our disclaimer regarding Satta King results and website usage.'],
        ['privacy-policy', 'Privacy Policy', 'Your privacy is important to us. We collect minimal data necessary to provide our services. We do not sell or share your personal information with third parties. Cookies may be used for analytics purposes.', 'Privacy Policy - Satta King', 'Read our privacy policy regarding data collection and usage.'],
        ['terms-conditions', 'Terms & Conditions', 'By using this website, you agree to our terms and conditions. This website is for users above 18 years of age. We reserve the right to modify our services at any time without prior notice.', 'Terms & Conditions - Satta King', 'Read our terms and conditions for using Satta King website.']
    ];
    $pageStmt = $pdo->prepare("INSERT INTO site_pages (slug, title, content, meta_title, meta_description) VALUES (?, ?, ?, ?, ?)");
    foreach ($pages as $page) {
        $pageStmt->execute($page);
    }
}

$checkSettings = $pdo->query("SELECT COUNT(*) FROM site_settings")->fetchColumn();
if ($checkSettings == 0) {
    $settings = [
        ['google_analytics_code', ''],
        ['meta_verification_google', ''],
        ['meta_verification_bing', '']
    ];
    $settingStmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $setting) {
        $settingStmt->execute($setting);
    }
}

$checkAds = $pdo->query("SELECT COUNT(*) FROM ad_placements")->fetchColumn();
if ($checkAds == 0) {
    $placements = ['header_ad', 'after_result', 'sidebar', 'footer_ad', 'between_posts'];
    $stmt = $pdo->prepare("INSERT INTO ad_placements (placement_name, is_active) VALUES (?, 0)");
    foreach ($placements as $p) {
        $stmt->execute([$p]);
    }
}

$checkGames = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();

if ($checkGames == 0) {
    $games = [
        ['Gali', '11:30:00'],
        ['Desawar', '05:00:00'],
        ['Faridabad', '18:15:00'],
        ['Ghaziabad', '21:30:00'],
        ['Delhi Bazaar', '15:00:00'],
        ['Shri Ganesh', '17:30:00'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO games (name, time_slot) VALUES (?, ?)");
    foreach ($games as $game) {
        $stmt->execute($game);
    }
    
    $resultStmt = $pdo->prepare("INSERT INTO satta_results (game_name, result, result_time, result_date) VALUES (?, ?, ?, CURRENT_DATE)");
    $resultStmt->execute(['Gali', '45', '11:30:00']);
    $resultStmt->execute(['Desawar', '89', '05:00:00']);
    $resultStmt->execute(['Faridabad', '23', '18:15:00']);
    $resultStmt->execute(['Ghaziabad', '67', '21:30:00']);
}

echo "Database initialized successfully!";
?>
