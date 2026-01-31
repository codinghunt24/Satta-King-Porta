<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$cronSecret = getenv('CRON_SECRET');
$providedSecret = $_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '';

if (!$cronSecret) {
    echo json_encode(['success' => false, 'error' => 'CRON_SECRET not configured']);
    exit;
}

if ($providedSecret !== $cronSecret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid secret']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/scraper.php';

date_default_timezone_set('Asia/Kolkata');

$sources = $pdo->query("SELECT * FROM scrape_sources WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

if (empty($sources)) {
    echo json_encode(['success' => true, 'message' => 'No active scrape sources']);
    exit;
}

$scraper = new SattaScraper($pdo);
$results = [];
$totalUpdated = 0;

foreach ($sources as $source) {
    $url = $source['url'];
    $result = $scraper->scrape($url);
    
    $results[] = [
        'url' => $url,
        'success' => $result['success'],
        'message' => $result['message'],
        'records_updated' => $result['records_updated'] ?? 0
    ];
    
    if ($result['success']) {
        $totalUpdated += $result['records_updated'] ?? 0;
        $pdo->prepare("UPDATE scrape_sources SET last_scraped_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$source['id']]);
    }
}

echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'total_updated' => $totalUpdated,
    'sources' => $results
]);
?>
