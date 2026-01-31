<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/scraper.php';

date_default_timezone_set('Asia/Kolkata');

echo "[" . date('Y-m-d H:i:s') . "] Starting automatic scrape...\n";

$sources = $pdo->query("SELECT * FROM scrape_sources WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

if (empty($sources)) {
    echo "No active scrape sources found.\n";
    exit(0);
}

$scraper = new SattaScraper($pdo);
$totalUpdated = 0;

foreach ($sources as $source) {
    $url = $source['url'];
    echo "Scraping: $url\n";
    
    $result = $scraper->scrape($url);
    
    if ($result['success']) {
        echo "  Success: " . $result['message'] . "\n";
        $totalUpdated += $result['records_updated'] ?? 0;
        
        $pdo->prepare("UPDATE scrape_sources SET last_scraped_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$source['id']]);
    } else {
        echo "  Failed: " . $result['message'] . "\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Completed. Total records updated: $totalUpdated\n";
?>
