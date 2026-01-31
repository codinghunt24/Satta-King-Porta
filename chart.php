<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/ads.php';

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
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $gameName ? htmlspecialchars($gameName) . ' - ' : ''; ?>Satta King Chart | Record Chart</title>
    <meta name="description" content="Satta King Chart - View monthly record chart for all Satta King games. Check Gali, Disawar, Faridabad, Ghaziabad historical results.">
    <link rel="stylesheet" href="css/style.css">
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
