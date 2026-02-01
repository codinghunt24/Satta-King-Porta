<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/ads.php';

date_default_timezone_set('Asia/Kolkata');

$allGames = $pdo->query("SELECT DISTINCT name FROM games ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
if (empty($allGames)) {
    $allGames = $pdo->query("SELECT DISTINCT game_name FROM satta_results ORDER BY game_name")->fetchAll(PDO::FETCH_COLUMN);
}

$gameName = isset($_GET['game']) ? urldecode($_GET['game']) : '';
if (empty($gameName) && !empty($allGames)) {
    $gameName = $allGames[0];
}
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$totalGamesCount = count($allGames);

$availableYears = $pdo->query("
    SELECT DISTINCT EXTRACT(YEAR FROM result_date) as year 
    FROM satta_results 
    ORDER BY year DESC
")->fetchAll(PDO::FETCH_COLUMN);

if (empty($availableYears)) {
    $currentYear = (int)date('Y');
    $availableYears = [$currentYear, $currentYear - 1, $currentYear - 2];
} else {
    $currentYear = (int)date('Y');
    for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
        if (!in_array($y, $availableYears)) {
            $availableYears[] = $y;
        }
    }
    rsort($availableYears);
}

$results = [];
$resultMap = [];
if (!empty($gameName)) {
    $stmt = $pdo->prepare("
        SELECT result_date, result, result_time 
        FROM satta_results 
        WHERE game_name = ? 
        AND EXTRACT(MONTH FROM result_date) = ? 
        AND EXTRACT(YEAR FROM result_date) = ?
        ORDER BY result_date DESC
    ");
    $stmt->execute([$gameName, $selectedMonth, $selectedYear]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $r) {
        $resultMap[$r['result_date']] = $r['result'];
    }
}

$daysInMonth = (int)date('t', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$monthName = date('F', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$oddCount = 0;
$evenCount = 0;
$digitFrequency = array_fill(0, 10, 0);
foreach ($results as $r) {
    if (!empty($r['result']) && $r['result'] !== '--') {
        $num = intval($r['result']);
        if ($num % 2 == 0) $evenCount++; else $oddCount++;
        $lastDigit = $num % 10;
        $digitFrequency[$lastDigit]++;
    }
}
arsort($digitFrequency);
$hotDigits = array_slice(array_keys($digitFrequency), 0, 3);

$pageTitle = $gameName ? htmlspecialchars($gameName) . ' Chart ' . $monthName . ' ' . $selectedYear . ' | Satta King Record' : 'Satta King Chart | All Games Record Chart';
$pageDescription = $gameName 
    ? 'Check ' . htmlspecialchars($gameName) . ' Satta King Chart for ' . $monthName . ' ' . $selectedYear . '. View complete monthly record, historical results, and patterns. Updated daily.'
    : 'Satta King Chart - View monthly record chart for all Satta King games. Check Gali, Disawar, Faridabad, Ghaziabad and ' . $totalGamesCount . '+ games historical results.';
$pageKeywords = $gameName 
    ? htmlspecialchars($gameName) . ' chart, ' . htmlspecialchars($gameName) . ' record, ' . htmlspecialchars($gameName) . ' satta king chart, ' . htmlspecialchars($gameName) . ' result ' . $monthName . ' ' . $selectedYear
    : 'satta king chart, satta chart, gali chart, disawar chart, satta king record, monthly chart';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <meta name="keywords" content="<?php echo $pageKeywords; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="/chart.php<?php echo $gameName ? '?game=' . urlencode($gameName) . '&month=' . $selectedMonth . '&year=' . $selectedYear : ''; ?>">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="css/style.css">
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "<?php echo $pageTitle; ?>",
        "description": "<?php echo $pageDescription; ?>",
        "publisher": {
            "@type": "Organization",
            "name": "Satta King"
        }
    }
    </script>
    <?php if ($gameName): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "Home", "item": "/"},
            {"@type": "ListItem", "position": 2, "name": "Chart", "item": "/chart.php"},
            {"@type": "ListItem", "position": 3, "name": "<?php echo htmlspecialchars($gameName); ?>", "item": "/chart.php?game=<?php echo urlencode($gameName); ?>"}
        ]
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "What is <?php echo htmlspecialchars($gameName); ?> Satta King Chart?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "<?php echo htmlspecialchars($gameName); ?> Satta King Chart is a complete record of all historical results for this game, organized by month and year."
                }
            },
            {
                "@type": "Question",
                "name": "How to check <?php echo htmlspecialchars($gameName); ?> chart for <?php echo $monthName . ' ' . $selectedYear; ?>?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "You can view the complete <?php echo htmlspecialchars($gameName); ?> chart for <?php echo $monthName . ' ' . $selectedYear; ?> on this page. The chart shows all daily results with dates."
                }
            },
            {
                "@type": "Question",
                "name": "Is <?php echo htmlspecialchars($gameName); ?> chart updated daily?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, our <?php echo htmlspecialchars($gameName); ?> chart is updated daily with the latest results as soon as they are declared."
                }
            }
        ]
    }
    </script>
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
                        <li><a href="index.php">Home</a></li>
                        <li><a href="daily-updates.php">Daily Update</a></li>
                        <li><a href="news.php">News</a></li>
                        <li><a href="chart.php" class="active">Chart</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="chart-header">
            <h2 class="game-chart-title">Satta King Chart</h2>
            <p class="chart-subtitle">Select Game, Month & Year to View Record</p>
            
            <form method="GET" class="filter-form chart-filter-form">
                <div class="filter-group">
                    <label for="game">Game:</label>
                    <select name="game" id="game">
                        <option value="">-- Select Game --</option>
                        <?php foreach ($allGames as $game): ?>
                            <option value="<?php echo htmlspecialchars($game); ?>" <?php echo $game == $gameName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($game); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="month">Month:</label>
                    <select name="month" id="month">
                        <?php foreach ($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $num == $selectedMonth ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="year">Year:</label>
                    <select name="year" id="year">
                        <?php foreach ($availableYears as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">View Chart</button>
            </form>
        </section>

        <?php displayAd($pdo, 'header_ad'); ?>

        <?php if (!empty($gameName)): ?>
        <section class="monthly-chart-section">
            <h3 class="section-title"><?php echo htmlspecialchars($gameName); ?> - <?php echo $monthName . ' ' . $selectedYear; ?></h3>
            
            <div class="results-table-container">
                <table class="monthly-chart-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        for ($day = $daysInMonth; $day >= 1; $day--):
                            $dateStr = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $day);
                            $dayName = date('D', strtotime($dateStr));
                            $result = $resultMap[$dateStr] ?? '--';
                            $isFuture = strtotime($dateStr) > time();
                        ?>
                        <tr class="<?php echo $isFuture ? 'future-date' : ''; ?>">
                            <td class="date-cell"><?php echo sprintf('%02d/%02d/%04d', $day, $selectedMonth, $selectedYear); ?></td>
                            <td class="day-cell"><?php echo $dayName; ?></td>
                            <td class="result-cell <?php echo ($result !== '--' && !$isFuture) ? 'has-result' : ''; ?>">
                                <?php echo $isFuture ? 'XX' : $result; ?>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="chart-stats">
                <div class="stat-box">
                    <span class="stat-label">Total Days</span>
                    <span class="stat-value"><?php echo $daysInMonth; ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Results Available</span>
                    <span class="stat-value"><?php echo count($results); ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Even Numbers</span>
                    <span class="stat-value"><?php echo $evenCount; ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Odd Numbers</span>
                    <span class="stat-value"><?php echo $oddCount; ?></span>
                </div>
            </div>
        </section>

        <section class="chart-content-section" style="background: linear-gradient(145deg, #1f2937 0%, #111827 100%); border-radius: 15px; padding: 25px; margin: 25px 0; border: 1px solid #374151;">
            <h2 style="color: #ffd700; font-size: 1.4rem; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e94560;">
                About <?php echo htmlspecialchars($gameName); ?> Satta King Chart
            </h2>
            <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 15px;">
                Welcome to the official <?php echo htmlspecialchars($gameName); ?> Satta King Chart page. This chart displays the complete record of 
                <?php echo htmlspecialchars($gameName); ?> results for <?php echo $monthName . ' ' . $selectedYear; ?>. Our chart is updated daily 
                with accurate results as soon as they are declared.
            </p>
            <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 15px;">
                The <?php echo htmlspecialchars($gameName); ?> chart helps players track historical patterns and analyze previous results. 
                You can navigate between different months and years using the filters above. All data is sourced from reliable channels 
                and verified for accuracy.
            </p>
            <h3 style="color: #e94560; font-size: 1.1rem; margin: 20px 0 10px;">Key Features of This Chart:</h3>
            <ul style="color: #d1d5db; line-height: 2; padding-left: 20px;">
                <li>Complete daily results for <?php echo $monthName . ' ' . $selectedYear; ?></li>
                <li>Day-wise breakdown with weekday information</li>
                <li>Historical data available for previous months and years</li>
                <li>Mobile-friendly responsive design</li>
                <li>Updated daily with latest results</li>
            </ul>
        </section>

        <?php if (count($results) > 5 && !empty($hotDigits)): ?>
        <section class="chart-analysis-section" style="background: linear-gradient(145deg, #1f2937 0%, #111827 100%); border-radius: 15px; padding: 25px; margin: 25px 0; border: 1px solid #374151;">
            <h2 style="color: #ffd700; font-size: 1.4rem; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e94560;">
                <?php echo htmlspecialchars($gameName); ?> Result Analysis - <?php echo $monthName . ' ' . $selectedYear; ?>
            </h2>
            <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 20px;">
                Based on <?php echo count($results); ?> results from <?php echo $monthName . ' ' . $selectedYear; ?>, here is a statistical analysis 
                of <?php echo htmlspecialchars($gameName); ?> patterns:
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="background: rgba(233, 69, 96, 0.1); padding: 15px; border-radius: 10px; text-align: center;">
                    <div style="color: #9ca3af; font-size: 0.85rem;">Even:Odd Ratio</div>
                    <div style="color: #ffd700; font-size: 1.5rem; font-weight: 700;"><?php echo $evenCount; ?>:<?php echo $oddCount; ?></div>
                </div>
                <div style="background: rgba(233, 69, 96, 0.1); padding: 15px; border-radius: 10px; text-align: center;">
                    <div style="color: #9ca3af; font-size: 0.85rem;">Hot Last Digits</div>
                    <div style="color: #ffd700; font-size: 1.5rem; font-weight: 700;"><?php echo implode(', ', $hotDigits); ?></div>
                </div>
                <div style="background: rgba(233, 69, 96, 0.1); padding: 15px; border-radius: 10px; text-align: center;">
                    <div style="color: #9ca3af; font-size: 0.85rem;">Data Points</div>
                    <div style="color: #ffd700; font-size: 1.5rem; font-weight: 700;"><?php echo count($results); ?></div>
                </div>
            </div>
            <p style="color: #9ca3af; font-size: 0.9rem; font-style: italic;">
                Note: This analysis is for informational purposes only and does not guarantee future results.
            </p>
        </section>
        <?php endif; ?>

        <section class="chart-faq-section" style="background: linear-gradient(145deg, #1f2937 0%, #111827 100%); border-radius: 15px; padding: 25px; margin: 25px 0; border: 1px solid #374151;">
            <h2 style="color: #ffd700; font-size: 1.4rem; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e94560;">
                <?php echo htmlspecialchars($gameName); ?> Chart FAQ
            </h2>
            
            <div style="margin-bottom: 20px;">
                <p style="color: #e94560; font-weight: 600; margin-bottom: 8px;">What is <?php echo htmlspecialchars($gameName); ?> Satta King Chart?</p>
                <p style="color: #d1d5db; line-height: 1.7;"><?php echo htmlspecialchars($gameName); ?> Satta King Chart is a comprehensive record that displays all historical results for the <?php echo htmlspecialchars($gameName); ?> game. It shows daily results organized by date, making it easy to track patterns over time.</p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <p style="color: #e94560; font-weight: 600; margin-bottom: 8px;">How often is the <?php echo htmlspecialchars($gameName); ?> chart updated?</p>
                <p style="color: #d1d5db; line-height: 1.7;">Our <?php echo htmlspecialchars($gameName); ?> chart is updated daily, immediately after results are declared. We ensure all data is accurate and verified before publishing.</p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <p style="color: #e94560; font-weight: 600; margin-bottom: 8px;">Can I view <?php echo htmlspecialchars($gameName); ?> chart for previous months?</p>
                <p style="color: #d1d5db; line-height: 1.7;">Yes, you can view <?php echo htmlspecialchars($gameName); ?> chart for any previous month or year using the dropdown filters at the top of this page. We maintain historical data going back several years.</p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <p style="color: #e94560; font-weight: 600; margin-bottom: 8px;">How many games charts are available?</p>
                <p style="color: #d1d5db; line-height: 1.7;">We provide charts for <?php echo $totalGamesCount; ?>+ Satta King games including popular ones like Gali, Disawar, Faridabad, Ghaziabad, and many more regional games.</p>
            </div>
            
            <div>
                <p style="color: #e94560; font-weight: 600; margin-bottom: 8px;">Is the chart data accurate?</p>
                <p style="color: #d1d5db; line-height: 1.7;">Yes, all chart data is 100% accurate and sourced from reliable channels. We verify each result before adding it to our database to maintain data integrity.</p>
            </div>
        </section>

        <section class="other-games-section" style="background: linear-gradient(145deg, #1f2937 0%, #111827 100%); border-radius: 15px; padding: 25px; margin: 25px 0; border: 1px solid #374151;">
            <h2 style="color: #ffd700; font-size: 1.4rem; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e94560;">
                Other Popular Game Charts
            </h2>
            <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 15px;">
                Explore charts for other popular Satta King games. Click on any game below to view its complete chart for <?php echo $monthName . ' ' . $selectedYear; ?>.
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <?php foreach (array_slice($allGames, 0, 15) as $game): ?>
                    <?php if ($game !== $gameName): ?>
                    <a href="chart.php?game=<?php echo urlencode($game); ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>" 
                       style="background: rgba(233, 69, 96, 0.2); color: #e94560; padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 0.9rem; transition: all 0.3s ease;">
                        <?php echo htmlspecialchars($game); ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="disclaimer-section" style="background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%); border-radius: 15px; padding: 25px; margin: 25px 0; border: 1px solid #e94560;">
            <h2 style="color: #e94560; font-size: 1.2rem; margin-bottom: 15px;">Disclaimer</h2>
            <p style="color: #d1d5db; line-height: 1.8; font-size: 0.9rem;">
                This chart is for informational purposes only. We do not encourage gambling or betting. Satta King is illegal in many parts of India. 
                Please be aware of the laws in your area. We are not responsible for any financial losses. Users must be 18+ years old.
            </p>
            <div style="margin-top: 15px;">
                <a href="/page/disclaimer" style="color: #60a5fa; text-decoration: none; margin-right: 15px;">Full Disclaimer</a>
                <a href="/page/privacy-policy" style="color: #60a5fa; text-decoration: none;">Privacy Policy</a>
            </div>
        </section>

        <?php else: ?>
        <section class="no-game-selected">
            <div class="info-box">
                <h3>Select a Game to View Chart</h3>
                <p>Choose any game from the dropdown above, select month and year, then click "View Chart" to see historical results.</p>
            </div>
            
            <?php if (!empty($allGames)): ?>
            <div class="quick-games">
                <h4>Quick Links - Popular Games</h4>
                <div class="game-links">
                    <?php foreach (array_slice($allGames, 0, 8) as $game): ?>
                    <a href="chart.php?game=<?php echo urlencode($game); ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>" class="game-link-btn">
                        <?php echo htmlspecialchars($game); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

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
            <p>Satta King Chart | Record Chart | Historical Results</p>
        </div>
    </footer>

    <script>
        function toggleMenu() {
            document.getElementById('mainNav').classList.toggle('active');
        }
    </script>
</body>
</html>
