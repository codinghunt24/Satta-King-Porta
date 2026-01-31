<?php
function shouldRunAutoScrape($pdo) {
    $lastRun = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'last_auto_scrape'")->fetchColumn();
    
    if (!$lastRun) {
        $pdo->exec("INSERT INTO site_settings (setting_key, setting_value) VALUES ('last_auto_scrape', '2000-01-01 00:00:00') ON CONFLICT (setting_key) DO NOTHING");
        return true;
    }
    
    $lastTime = strtotime($lastRun);
    $now = time();
    $intervalMinutes = 30;
    
    return ($now - $lastTime) >= ($intervalMinutes * 60);
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
?>
