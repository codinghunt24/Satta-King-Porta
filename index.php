<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/ads.php';
require_once __DIR__ . '/lib/auto_scheduler.php';

date_default_timezone_set('Asia/Kolkata');

triggerAllScheduledTasks($pdo);

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$lastUpdateStmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'last_auto_scrape'");
$lastUpdateTime = $lastUpdateStmt->fetchColumn();
$lastUpdateFormatted = $lastUpdateTime ? date('h:i A', strtotime($lastUpdateTime)) : 'Not yet';

$allResults = $pdo->query("
    SELECT sr.game_name, sr.result, TO_CHAR(sr.result_date, 'YYYY-MM-DD') as result_date, sr.result_time,
           COALESCE(g.display_order, 999) as display_order
    FROM satta_results sr 
    LEFT JOIN games g ON g.name = sr.game_name
    WHERE sr.result_date IN (CURRENT_DATE, CURRENT_DATE - INTERVAL '1 day')
    ORDER BY COALESCE(g.display_order, 999) ASC
")->fetchAll(PDO::FETCH_ASSOC);

$gameResults = [];
foreach ($allResults as $r) {
    $gameName = $r['game_name'];
    if (!isset($gameResults[$gameName])) {
        $gameResults[$gameName] = [
            'name' => $gameName,
            'time' => $r['result_time'],
            'today' => '--',
            'yesterday' => '--',
            'order' => $r['display_order']
        ];
    }
    if ($r['result_date'] === $today) {
        $gameResults[$gameName]['today'] = $r['result'];
    } else {
        $gameResults[$gameName]['yesterday'] = $r['result'];
    }
}

usort($gameResults, function($a, $b) {
    return $a['order'] - $b['order'];
});

$chartData = $pdo->query("
    SELECT result_date, game_name, result 
    FROM satta_results 
    WHERE result_date >= CURRENT_DATE - INTERVAL '7 days'
    ORDER BY result_date DESC, game_name
")->fetchAll(PDO::FETCH_ASSOC);

$analyticsCode = '';
$googleVerify = '';
$bingVerify = '';
try {
    $adsenseAutoAds = '';
    $settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'google_analytics_code') $analyticsCode = $row['setting_value'];
        if ($row['setting_key'] === 'meta_verification_google') $googleVerify = $row['setting_value'];
        if ($row['setting_key'] === 'meta_verification_bing') $bingVerify = $row['setting_value'];
        if ($row['setting_key'] === 'adsense_auto_ads') $adsenseAutoAds = $row['setting_value'];
    }
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satta King | Satta King 786 | Delhi Satta King Live Results Today</title>
    <meta name="description" content="Satta King Fast Live Results - Check Satta King Disawar, Gali, Delhi Bazar, Shri Ganesh Satta King results. Get fastest Satta King chart and Black Satta King results updated daily.">
    <meta name="keywords" content="satta king, satta king 786, delhi satta king, satta king disawar, shri ganesh satta king, satta king fast, satta king chart, delhi bazar satta king, satta king gali disawar, black satta king">
    <meta name="robots" content="index, follow">
    <meta name="author" content="Satta King">
    <?php if (!empty($googleVerify)): ?><meta name="google-site-verification" content="<?php echo htmlspecialchars($googleVerify); ?>"><?php endif; ?>
    <?php if (!empty($bingVerify)): ?><meta name="msvalidate.01" content="<?php echo htmlspecialchars($bingVerify); ?>"><?php endif; ?>
    <meta property="og:title" content="Satta King | Satta King 786 | Delhi Satta King Live Results">
    <meta property="og:description" content="Get fastest Satta King results - Gali, Disawar, Delhi Bazar, Faridabad, Ghaziabad. Live Satta King chart updated daily.">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Satta King Fast Results | Satta King 786">
    <meta name="twitter:description" content="Live Satta King results for Gali, Disawar, Delhi Bazar. Check Satta King chart and Black Satta King results.">
    <link rel="canonical" href="/">
    <link rel="stylesheet" href="css/style.css">
    <?php if (!empty($analyticsCode)) echo $analyticsCode; ?>
    <?php if (!empty($adsenseAutoAds)) echo $adsenseAutoAds; ?>
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
                        <li><a href="index.php">Home</a></li>
                        <li><a href="daily-updates.php">Daily Update</a></li>
                        <li><a href="news.php">News</a></li>
                        <li><a href="chart.php">Chart</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="hero">
            <h2>Satta King Fast Live Results</h2>
            <p>Satta King 786 | Delhi Satta King | Satta King Disawar | Gali Disawar</p>
            <div class="current-date">
                <?php echo date('d F Y'); ?>
            </div>
            <div class="last-update">
                Last Updated: <?php echo $lastUpdateFormatted; ?>
            </div>
        </section>

        <section class="seo-intro">
            <p>Welcome to <strong>Satta King</strong> - your trusted source for <strong>Satta King Fast</strong> live results. Get instant updates for <strong>Delhi Satta King</strong>, <strong>Satta King Disawar</strong>, <strong>Shri Ganesh Satta King</strong>, and <strong>Delhi Bazar Satta King</strong> results. Check our <strong>Satta King Chart</strong> for <strong>Satta King Gali Disawar</strong> historical records. We provide the fastest <strong>Black Satta King</strong> and <strong>Satta King 786</strong> results updated daily.</p>
        </section>
        
        <?php displayAd($pdo, 'header_ad'); ?>

        <section class="results-section" id="results">
            <h3 class="section-title">Satta King Results - Delhi Satta King Today</h3>
            <p class="subtitle"><?php echo date('d M', strtotime($yesterday)); ?> & <?php echo date('d M Y', strtotime($today)); ?></p>
            
            <div class="results-table-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Game Name</th>
                            <th><?php echo date('D d', strtotime($yesterday)); ?></th>
                            <th><?php echo date('D d', strtotime($today)); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gameResults as $game): ?>
                        <tr>
                            <td class="game-name-cell">
                                <?php echo htmlspecialchars($game['name']); ?>
                                <div class="game-meta">
                                    <a href="chart.php?game=<?php echo urlencode($game['name']); ?>" class="record-chart-link">Record Chart</a>
                                    <span class="game-time-inline"><?php echo date('h:i A', strtotime($game['time'])); ?></span>
                                </div>
                            </td>
                            <td class="result-cell <?php echo ($game['yesterday'] !== '--' && $game['yesterday'] !== 'XX') ? 'has-result' : ''; ?>">
                                <?php echo ($game['yesterday'] === 'XX' || $game['yesterday'] === '--') ? '--' : htmlspecialchars($game['yesterday']); ?>
                            </td>
                            <td class="result-cell <?php echo ($game['today'] !== '--' && $game['today'] !== 'XX') ? 'has-result today-result' : 'waiting'; ?>">
                                <?php echo ($game['today'] === 'XX') ? 'Waiting' : htmlspecialchars($game['today']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button class="refresh-btn" onclick="location.reload()">Refresh Satta King Results</button>
            </div>
            
            <?php displayAd($pdo, 'after_result'); ?>
        </section>

        <section class="chart-section" id="chart">
            <h3 class="section-title">Satta King Chart - Gali, Desawar, Faridabad, Ghaziabad Weekly</h3>
            <div class="results-table-container">
                <table class="chart-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Gali</th>
                            <th>Desawar</th>
                            <th>Faridabad</th>
                            <th>Ghaziabad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $chartByDate = [];
                        foreach ($chartData as $row) {
                            $date = $row['result_date'];
                            if (!isset($chartByDate[$date])) {
                                $chartByDate[$date] = [];
                            }
                            $chartByDate[$date][$row['game_name']] = $row['result'];
                        }
                        
                        foreach ($chartByDate as $date => $gameResultsChart):
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($date)); ?></td>
                            <td><?php echo $gameResultsChart['Gali'] ?? '-'; ?></td>
                            <td><?php echo $gameResultsChart['Desawar'] ?? '-'; ?></td>
                            <td><?php echo $gameResultsChart['Faridabad'] ?? '-'; ?></td>
                            <td><?php echo $gameResultsChart['Ghaziabad'] ?? '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="faq-section" id="faq">
            <h3 class="section-title">Satta King FAQ</h3>
            <div class="faq-container">
                <div class="faq-item">
                    <h4 class="faq-question">What is Satta King?</h4>
                    <p class="faq-answer">Satta King is a popular game where players can check daily results. Our website provides fast Satta King results including Delhi Satta King, Satta King Disawar, Gali, Faridabad, and Ghaziabad results.</p>
                </div>
                <div class="faq-item">
                    <h4 class="faq-question">How to check Satta King Chart?</h4>
                    <p class="faq-answer">You can check Satta King Chart on our website. Click on "Record Chart" link next to any game name to view the monthly Satta King chart with historical results for Gali Disawar and other games.</p>
                </div>
                <div class="faq-item">
                    <h4 class="faq-question">What is Delhi Bazar Satta King?</h4>
                    <p class="faq-answer">Delhi Bazar Satta King is one of the popular games. We provide live results for Delhi Bazar, Shri Ganesh Satta King, Black Satta King, and Satta King 786 updated daily.</p>
                </div>
                <div class="faq-item">
                    <h4 class="faq-question">When are Satta King results updated?</h4>
                    <p class="faq-answer">Satta King Fast results are updated throughout the day as games conclude. Each game has a specific time slot - check the time shown next to each game name for exact result timing.</p>
                </div>
            </div>
        </section>

        <div class="disclaimer">
            <p><strong>Disclaimer:</strong> This website is for informational purposes only. We do not promote or encourage any form of gambling or betting. Please check your local laws before participating in any games.</p>
        </div>
        
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
