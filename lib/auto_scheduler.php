<?php
date_default_timezone_set('Asia/Kolkata');

function shouldRunAutoScrape($pdo) {
    $lastRun = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'last_auto_scrape'")->fetchColumn();
    
    if (!$lastRun) {
        $pdo->exec("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('last_auto_scrape', '2000-01-01 00:00:00')");
        return true;
    }
    
    $lastTime = strtotime($lastRun);
    $now = time();
    $intervalMinutes = 30;
    
    return ($now - $lastTime) >= ($intervalMinutes * 60);
}

function shouldAutoPublishPosts($pdo) {
    $autoPublishEnabled = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'auto_publish_enabled'")->fetchColumn();
    
    if ($autoPublishEnabled === '0') {
        return false;
    }
    
    $autoPublishHour = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'auto_publish_hour'")->fetchColumn();
    if ($autoPublishHour === false) {
        $autoPublishHour = 1;
    }
    $autoPublishHour = intval($autoPublishHour);
    
    $lastPublish = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'last_auto_publish'")->fetchColumn();
    
    if (!$lastPublish) {
        $pdo->exec("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('last_auto_publish', '2000-01-01')");
        $lastPublish = '2000-01-01';
    }
    
    $today = date('Y-m-d');
    $currentHour = (int)date('H');
    
    if ($lastPublish !== $today && $currentHour >= $autoPublishHour) {
        return true;
    }
    
    return false;
}

function autoPublishDailyPosts($pdo) {
    $publishDate = date('Y-m-d');
    $dateFormatTitle = date('j M Y', strtotime($publishDate));
    $dateFormatUrl = strtolower(date('j-F-Y', strtotime($publishDate)));
    
    $allGames = $pdo->query("SELECT DISTINCT name FROM games WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($allGames)) {
        $allGames = $pdo->query("SELECT DISTINCT game_name FROM satta_results ORDER BY game_name")->fetchAll(PDO::FETCH_COLUMN);
    }
    
    if (empty($allGames)) {
        return 0;
    }
    
    $publishedCount = 0;
    
    foreach ($allGames as $gameName) {
        $slugGame = strtolower(str_replace(' ', '-', $gameName));
        $slugGame = preg_replace('/[^a-z0-9\-]/', '', $slugGame);
        
        $existingPost = $pdo->prepare("SELECT id FROM posts WHERE post_date = ? AND games_included = ?");
        $existingPost->execute([$publishDate, $gameName]);
        
        if ($existingPost->fetch()) {
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
        
        $metaDesc = "Check {$gameName} Satta King Result for {$dateFormatTitle}. Get live {$gameName} result, chart, and fast updates.";
        $metaKeywords = "{$gameName} satta king result, {$gameName} result {$dateFormatTitle}, satta king {$gameName}";
        
        $insertStmt = $pdo->prepare("INSERT INTO posts (title, slug, meta_description, meta_keywords, games_included, post_date) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([$title, $slug, $metaDesc, $metaKeywords, $gameName, $publishDate]);
        $publishedCount++;
    }
    
    $pdo->prepare("UPDATE site_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = 'last_auto_publish'")->execute([$publishDate]);
    
    return $publishedCount;
}

function runAutoScrape($pdo) {
    require_once __DIR__ . '/scraper.php';
    
    $sources = $pdo->query("SELECT * FROM scrape_sources WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sources)) {
        return;
    }
    
    $scraper = new SattaScraper($pdo);
    
    foreach ($sources as $source) {
        try {
            $result = $scraper->scrape($source['url']);
            if ($result['success']) {
                $pdo->prepare("UPDATE scrape_sources SET last_scraped_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$source['id']]);
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    $pdo->prepare("UPDATE site_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = 'last_auto_scrape'")->execute([date('Y-m-d H:i:s')]);
}

function triggerAutoScrapeIfNeeded($pdo) {
    if (shouldRunAutoScrape($pdo)) {
        runAutoScrape($pdo);
    }
}

function triggerAutoPublishIfNeeded($pdo) {
    if (shouldAutoPublishPosts($pdo)) {
        return autoPublishDailyPosts($pdo);
    }
    return 0;
}

function triggerAllScheduledTasks($pdo) {
    triggerAutoScrapeIfNeeded($pdo);
    triggerAutoPublishIfNeeded($pdo);
}
?>
