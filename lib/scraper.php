<?php
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Kolkata');

class SattaScraper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    private function fetchUrl($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                    'Cache-Control: max-age=0',
                ],
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_REFERER => 'https://www.google.com/',
            ]);
            
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($html === false || $httpCode >= 400) {
                return ['success' => false, 'error' => $error ?: "HTTP $httpCode", 'code' => $httpCode];
            }
            return ['success' => true, 'html' => $html, 'code' => $httpCode];
        }
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'header' => "Accept: text/html,application/xhtml+xml\r\nAccept-Language: en-US,en;q=0.5\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            return ['success' => false, 'error' => 'Failed to fetch', 'code' => 0];
        }
        return ['success' => true, 'html' => $html, 'code' => 200];
    }
    
    public function scrape($url) {
        $result = [
            'success' => false,
            'message' => '',
            'records_updated' => 0
        ];
        
        try {
            $fetch = $this->fetchUrl($url);
            
            if (!$fetch['success']) {
                $result['message'] = 'Failed to fetch URL (HTTP ' . $fetch['code'] . '). Website may be protected. Use paste option instead.';
                $this->logScrape($url, 'failed', $result['message'], 0);
                return $result;
            }
            
            $html = $fetch['html'];
            
            if (strpos($html, 'Just a moment') !== false || strpos($html, 'cf_chl') !== false || strpos($html, 'challenge-platform') !== false) {
                $result['message'] = 'Website is protected by Cloudflare. Use paste option instead.';
                $this->logScrape($url, 'cloudflare', $result['message'], 0);
                return $result;
            }
            
            $data = $this->parseHtml($html);
            
            if (empty($data)) {
                $result['message'] = 'No data found on the page. The website structure may have changed.';
                $this->logScrape($url, 'failed', $result['message'], 0);
                return $result;
            }
            
            $updated = $this->saveData($data, $url);
            
            $result['success'] = true;
            $result['message'] = "Successfully scraped and updated {$updated} records.";
            $result['records_updated'] = $updated;
            
            $this->logScrape($url, 'success', $result['message'], $updated);
            
        } catch (Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
            $this->logScrape($url, 'failed', $result['message'], 0);
        }
        
        return $result;
    }
    
    public function parseFromPastedContent($content, $sourceUrl = 'pasted_content') {
        $result = [
            'success' => false,
            'message' => '',
            'records_updated' => 0
        ];
        
        try {
            $data = $this->parseTableData($content);
            
            if (empty($data)) {
                $result['message'] = 'No valid data found in pasted content.';
                $this->logScrape($sourceUrl, 'failed', $result['message'], 0);
                return $result;
            }
            
            $updated = $this->saveData($data, $sourceUrl);
            
            $result['success'] = true;
            $result['message'] = "Successfully imported {$updated} records from pasted content.";
            $result['records_updated'] = $updated;
            
            $this->logScrape($sourceUrl, 'success', $result['message'], $updated);
            
        } catch (Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
            $this->logScrape($sourceUrl, 'failed', $result['message'], 0);
        }
        
        return $result;
    }
    
    private function parseTableData($content) {
        $data = [];
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        if (strpos($content, '<h3 class="game-name">') !== false || strpos($content, 'class="game-result"') !== false) {
            return $this->parseHtmlTable($content);
        }
        
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (preg_match('/^([A-Z][A-Z0-9\s\-\.]+)\s+(\d{1,2}:\d{2}\s*[AP]M)\s+(\d{2}|--|-|XX)\s+(\d{2}|--|-|XX)$/i', $line, $m)) {
                $gameName = trim($m[1]);
                $timeStr = trim($m[2]);
                $yesterdayResult = trim($m[3]);
                $todayResult = trim($m[4]);
                
                $time24 = date('H:i:s', strtotime($timeStr));
                
                if ($yesterdayResult !== '--' && $yesterdayResult !== '-' && $yesterdayResult !== 'XX') {
                    $data[] = [
                        'game_name' => $this->normalizeGameName($gameName),
                        'result' => $yesterdayResult,
                        'result_time' => $time24,
                        'result_date' => $yesterday
                    ];
                }
                
                if ($todayResult !== '--' && $todayResult !== '-' && $todayResult !== 'XX') {
                    $data[] = [
                        'game_name' => $this->normalizeGameName($gameName),
                        'result' => $todayResult,
                        'result_time' => $time24,
                        'result_date' => $today
                    ];
                }
                continue;
            }
            
            if (preg_match('/^\|?\s*([A-Z][A-Z0-9\s\-\.]+)\s*\|\s*(\d{1,2}:\d{2}\s*[AP]M)\s*\|\s*(\d{2}|--|-|XX)\s*\|\s*(\d{2}|--|-|XX)\s*\|?$/i', $line, $m)) {
                $gameName = trim($m[1]);
                $timeStr = trim($m[2]);
                $yesterdayResult = trim($m[3]);
                $todayResult = trim($m[4]);
                
                $time24 = date('H:i:s', strtotime($timeStr));
                
                if ($yesterdayResult !== '--' && $yesterdayResult !== '-' && $yesterdayResult !== 'XX') {
                    $data[] = [
                        'game_name' => $this->normalizeGameName($gameName),
                        'result' => $yesterdayResult,
                        'result_time' => $time24,
                        'result_date' => $yesterday
                    ];
                }
                
                if ($todayResult !== '--' && $todayResult !== '-' && $todayResult !== 'XX') {
                    $data[] = [
                        'game_name' => $this->normalizeGameName($gameName),
                        'result' => $todayResult,
                        'result_time' => $time24,
                        'result_date' => $today
                    ];
                }
            }
        }
        
        return $data;
    }
    
    private function parseHtmlTable($html) {
        $data = [];
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $pattern = '/<tr[^>]*class="[^"]*game-result[^"]*"[^>]*>.*?<h3[^>]*class="[^"]*game-name[^"]*"[^>]*>([^<]+)<\/h3>.*?<h3[^>]*class="[^"]*game-time[^"]*"[^>]*>\s*at\s*(\d{1,2}:\d{2}\s*[AP]M)<\/h3>.*?<td[^>]*class="[^"]*yesterday-number[^"]*"[^>]*>.*?<h3>(\d{2}|--|-|XX)<\/h3>.*?<td[^>]*class="[^"]*today-number[^"]*"[^>]*>.*?<h3>(\d{2}|--|-|XX)<\/h3>/is';
        
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $gameName = trim($match[1]);
            $timeStr = trim($match[2]);
            $yesterdayResult = trim($match[3]);
            $todayResult = trim($match[4]);
            
            $time24 = date('H:i:s', strtotime($timeStr));
            
            if ($yesterdayResult !== '--' && $yesterdayResult !== '-' && $yesterdayResult !== 'XX') {
                $data[] = [
                    'game_name' => $this->normalizeGameName($gameName),
                    'result' => $yesterdayResult,
                    'result_time' => $time24,
                    'result_date' => $yesterday
                ];
            }
            
            if ($todayResult !== '--' && $todayResult !== '-' && $todayResult !== 'XX') {
                $data[] = [
                    'game_name' => $this->normalizeGameName($gameName),
                    'result' => $todayResult,
                    'result_time' => $time24,
                    'result_date' => $today
                ];
            }
        }
        
        return $data;
    }
    
    private function normalizeGameName($name) {
        $name = preg_replace('/\s+/', ' ', trim($name));
        return ucwords(strtolower($name));
    }
    
    private function parseHtml($html) {
        $data = [];
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        if (strpos($html, 'Just a moment') !== false || strpos($html, 'cf_chl') !== false) {
            return $data;
        }
        
        // Pattern 1: satta-king-fast.com - game-result rows with game-name, game-time, yesterday-number, today-number
        if (strpos($html, 'game-result') !== false && strpos($html, 'game-name') !== false) {
            preg_match_all('/<tr[^>]*class="[^"]*game-result[^"]*"[^>]*>.*?<h3[^>]*class="[^"]*game-name[^"]*"[^>]*>([^<]+)<\/h3>.*?<h3[^>]*class="[^"]*game-time[^"]*"[^>]*>\s*at\s*(\d{1,2}:\d{2}\s*[AP]M)<\/h3>.*?<td[^>]*class="[^"]*yesterday-number[^"]*"[^>]*>.*?<h3>(\d{2}|--|-|XX)<\/h3>.*?<td[^>]*class="[^"]*today-number[^"]*"[^>]*>.*?<h3>(\d{2}|--|-|XX)<\/h3>/is', $html, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $gameName = trim($match[1]);
                $timeStr = trim($match[2]);
                $yesterdayResult = trim($match[3]);
                $todayResult = trim($match[4]);
                
                $time24 = date('H:i:s', strtotime($timeStr));
                
                if ($yesterdayResult !== '--' && $yesterdayResult !== '-' && $yesterdayResult !== 'XX' && preg_match('/^\d{2}$/', $yesterdayResult)) {
                    $data[] = [
                        'game_name' => $this->normalizeGameName($gameName),
                        'result' => $yesterdayResult,
                        'result_time' => $time24,
                        'result_date' => $yesterday
                    ];
                }
                
                if ($todayResult !== '--' && $todayResult !== '-' && $todayResult !== 'XX' && preg_match('/^\d{2}$/', $todayResult)) {
                    $data[] = [
                        'game_name' => $this->normalizeGameName($gameName),
                        'result' => $todayResult,
                        'result_time' => $time24,
                        'result_date' => $today
                    ];
                }
            }
            
            if (!empty($data)) {
                return $data;
            }
        }
        
        // Pattern 2: Generic table pattern - game name, time, yesterday, today in sequence
        preg_match_all('/<tr[^>]*>.*?<td[^>]*>.*?<(?:h3|a|strong|b)[^>]*>([A-Z][A-Za-z0-9\s\-\.]+)<\/(?:h3|a|strong|b)>.*?(?:at\s*)?(\d{1,2}:\d{2}\s*[AP]M).*?<\/td>.*?<td[^>]*>.*?(\d{2}|--|-|XX).*?<\/td>.*?<td[^>]*>.*?(\d{2}|--|-|XX).*?<\/td>.*?<\/tr>/is', $html, $fullMatches, PREG_SET_ORDER);
        
        if (!empty($fullMatches)) {
            foreach ($fullMatches as $match) {
                $gameName = trim(strip_tags($match[1]));
                $timeStr = trim($match[2]);
                $yesterdayResult = trim(strip_tags($match[3]));
                $todayResult = trim(strip_tags($match[4]));
                
                $time24 = date('H:i:s', strtotime($timeStr));
                
                if ($yesterdayResult !== '--' && $yesterdayResult !== '-' && $yesterdayResult !== 'XX' && preg_match('/^\d{2}$/', $yesterdayResult)) {
                    $data[] = [
                        'game_name' => $this->normalizeGameName($gameName),
                        'result' => $yesterdayResult,
                        'result_time' => $time24,
                        'result_date' => $yesterday
                    ];
                }
                
                if ($todayResult !== '--' && $todayResult !== '-' && $todayResult !== 'XX' && preg_match('/^\d{2}$/', $todayResult)) {
                    $data[] = [
                        'game_name' => $this->normalizeGameName($gameName),
                        'result' => $todayResult,
                        'result_time' => $time24,
                        'result_date' => $today
                    ];
                }
            }
            
            if (!empty($data)) {
                return $data;
            }
        }
        
        // Pattern 3: Legacy regionName pattern
        preg_match_all('/<tr[^>]*>.*?<td[^>]*class="[^"]*regionName[^"]*"[^>]*>.*?<a[^>]*>([^<]+)<\/a>.*?<span[^>]*>at\s*(\d{1,2}:\d{2}\s*[AP]M)<\/span>.*?<td[^>]*class="[^"]*result[^"]*"[^>]*>(\d{2}|--|-|XX)<\/td>.*?<td[^>]*class="[^"]*result[^"]*"[^>]*>(\d{2}|--|-|XX)<\/td>/is', $html, $fullMatches, PREG_SET_ORDER);
        
        foreach ($fullMatches as $match) {
            $gameName = trim($match[1]);
            $timeStr = trim($match[2]);
            $yesterdayResult = trim($match[3]);
            $todayResult = trim($match[4]);
            
            $time24 = date('H:i:s', strtotime($timeStr));
            
            if ($yesterdayResult !== '--' && $yesterdayResult !== '-' && $yesterdayResult !== 'XX') {
                $data[] = [
                    'game_name' => $this->normalizeGameName($gameName),
                    'result' => $yesterdayResult,
                    'result_time' => $time24,
                    'result_date' => $yesterday
                ];
            }
            
            if ($todayResult !== '--' && $todayResult !== '-' && $todayResult !== 'XX') {
                $data[] = [
                    'game_name' => $this->normalizeGameName($gameName),
                    'result' => $todayResult,
                    'result_time' => $time24,
                    'result_date' => $today
                ];
            }
        }
        
        return $data;
    }
    
    private function saveData($data, $sourceUrl) {
        $updated = 0;
        $today = date('Y-m-d');
        
        $gameStmt = $this->pdo->prepare("
            INSERT INTO games (name, time_slot)
            VALUES (?, ?)
            ON CONFLICT (name) DO NOTHING
        ");
        
        $todayStmt = $this->pdo->prepare("
            INSERT INTO satta_results (game_name, result, result_time, result_date, source_url, scraped_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (game_name, result_date) DO UPDATE SET 
                result = EXCLUDED.result,
                result_time = EXCLUDED.result_time,
                source_url = EXCLUDED.source_url,
                scraped_at = CURRENT_TIMESTAMP
        ");
        
        $historyStmt = $this->pdo->prepare("
            INSERT INTO satta_results (game_name, result, result_time, result_date, source_url, scraped_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (game_name, result_date) DO NOTHING
        ");
        
        foreach ($data as $row) {
            $gameStmt->execute([$row['game_name'], $row['result_time']]);
            
            if ($row['result_date'] === $today) {
                $todayStmt->execute([
                    $row['game_name'],
                    $row['result'],
                    $row['result_time'],
                    $row['result_date'],
                    $sourceUrl
                ]);
                $updated++;
            } else {
                $historyStmt->execute([
                    $row['game_name'],
                    $row['result'],
                    $row['result_time'],
                    $row['result_date'],
                    $sourceUrl
                ]);
                if ($historyStmt->rowCount() > 0) {
                    $updated++;
                }
            }
        }
        
        return $updated;
    }
    
    private function logScrape($url, $status, $message, $count) {
        $stmt = $this->pdo->prepare("
            INSERT INTO scrape_logs (source_url, status, message, records_updated)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$url, $status, $message, $count]);
    }
    
    public function getLastScrapeLog() {
        return $this->pdo->query("
            SELECT * FROM scrape_logs ORDER BY scraped_at DESC LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
