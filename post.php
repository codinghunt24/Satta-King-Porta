<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/database.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: daily-updates.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ?");
$stmt->execute([$slug]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    echo "Post not found";
    exit;
}

$updateViews = $pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?");
$updateViews->execute([$post['id']]);

$postDate = $post['post_date'];
$gameName = $post['games_included'];

$resultToday = $pdo->prepare("
    SELECT game_name, result, result_time 
    FROM satta_results 
    WHERE result_date = ? AND game_name = ?
");
$resultToday->execute([$postDate, $gameName]);
$todayResult = $resultToday->fetch(PDO::FETCH_ASSOC);

$last7Days = $pdo->prepare("
    SELECT result_date, result 
    FROM satta_results 
    WHERE game_name = ? AND result IS NOT NULL AND result != ''
    ORDER BY result_date DESC 
    LIMIT 7
");
$last7Days->execute([$gameName]);
$weeklyResults = $last7Days->fetchAll(PDO::FETCH_ASSOC);

$relatedPosts = $pdo->prepare("
    SELECT slug, title, post_date FROM posts 
    WHERE games_included = ? AND slug != ?
    ORDER BY post_date DESC LIMIT 5
");
$relatedPosts->execute([$gameName, $slug]);
$relatedPostsList = $relatedPosts->fetchAll(PDO::FETCH_ASSOC);

$otherGamePosts = $pdo->prepare("
    SELECT slug, title, games_included FROM posts 
    WHERE post_date = ? AND slug != ?
    ORDER BY games_included
");
$otherGamePosts->execute([$postDate, $slug]);
$otherGamesList = $otherGamePosts->fetchAll(PDO::FETCH_ASSOC);

$last30Days = $pdo->prepare("
    SELECT result FROM satta_results 
    WHERE game_name = ? AND result IS NOT NULL AND result != '' AND result != 'XX'
    ORDER BY result_date DESC LIMIT 30
");
$last30Days->execute([$gameName]);
$monthlyResults = $last30Days->fetchAll(PDO::FETCH_COLUMN);

$totalGames = $pdo->query("SELECT COUNT(DISTINCT game_name) FROM satta_results WHERE result IS NOT NULL")->fetchColumn();

$resultPatterns = [];
$oddCount = 0;
$evenCount = 0;
foreach ($monthlyResults as $res) {
    $num = intval($res);
    if ($num % 2 == 0) $evenCount++; else $oddCount++;
    $lastDigit = $num % 10;
    if (!isset($resultPatterns[$lastDigit])) $resultPatterns[$lastDigit] = 0;
    $resultPatterns[$lastDigit]++;
}
arsort($resultPatterns);
$hotDigits = array_slice(array_keys($resultPatterns), 0, 3);

$dayName = date('l', strtotime($postDate));
$monthName = date('F', strtotime($postDate));
$year = date('Y', strtotime($postDate));
$formattedDate = date('d F Y', strtotime($postDate));
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($post['meta_description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($post['meta_keywords']); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="/post/<?php echo htmlspecialchars($post['slug']); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($post['meta_description']); ?>">
    <meta property="og:type" content="article">
    <meta property="article:published_time" content="<?php echo date('c', strtotime($post['created_at'])); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .post-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .post-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .post-header h1 {
            color: #e94560;
            font-size: 2rem;
            margin-bottom: 15px;
            line-height: 1.3;
        }
        .post-meta {
            color: #9ca3af;
            font-size: 0.9rem;
        }
        .post-section {
            background: linear-gradient(145deg, #1f2937 0%, #111827 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #374151;
        }
        .post-section h2 {
            color: #ffd700;
            font-size: 1.4rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e94560;
        }
        .post-section h3 {
            color: #e94560;
            font-size: 1.1rem;
            margin: 20px 0 10px;
        }
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        .result-item {
            background: rgba(233, 69, 96, 0.1);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .result-game {
            color: #fff;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .result-number {
            color: #ffd700;
            font-size: 2rem;
            font-weight: 700;
        }
        .result-time {
            color: #9ca3af;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        .faq-item {
            margin-bottom: 20px;
        }
        .faq-question {
            color: #e94560;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .faq-answer {
            color: #d1d5db;
            line-height: 1.7;
        }
        .nav-links {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .nav-link {
            flex: 1;
            min-width: 200px;
            background: linear-gradient(145deg, #1f2937 0%, #111827 100%);
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            border: 1px solid #374151;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            border-color: #e94560;
            transform: translateY(-3px);
        }
        .nav-link-label {
            color: #9ca3af;
            font-size: 0.8rem;
            margin-bottom: 5px;
        }
        .nav-link-title {
            color: #fff;
            font-size: 0.95rem;
        }
        .internal-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .internal-link {
            background: rgba(233, 69, 96, 0.2);
            color: #e94560;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .internal-link:hover {
            background: #e94560;
            color: #fff;
        }
        @media (max-width: 768px) {
            .post-container {
                padding: 15px 10px;
            }
            .post-header {
                margin-bottom: 20px;
            }
            .post-header h1 {
                font-size: 1.3rem;
                line-height: 1.4;
            }
            .post-meta {
                display: flex;
                flex-direction: column;
                gap: 8px;
                align-items: center;
            }
            .post-meta span {
                margin-left: 0 !important;
            }
            .breadcrumb {
                font-size: 0.8rem;
                flex-wrap: wrap;
                text-align: center;
            }
            .toc-box {
                padding: 15px;
            }
            .toc-box h3 {
                font-size: 1rem;
            }
            .toc-box ul li {
                font-size: 0.9rem;
            }
            .post-section {
                padding: 15px;
                margin-bottom: 15px;
            }
            .post-section h2 {
                font-size: 1.1rem;
                margin-bottom: 15px;
            }
            .post-section h3 {
                font-size: 1rem;
            }
            .post-section p {
                font-size: 0.9rem;
                line-height: 1.7;
            }
            .post-section ul {
                padding-left: 15px;
                font-size: 0.9rem;
            }
            .results-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .result-item {
                padding: 10px;
            }
            .result-number {
                font-size: 1.5rem;
            }
            .faq-item {
                margin-bottom: 15px;
            }
            .faq-question {
                font-size: 0.95rem;
            }
            .faq-answer {
                font-size: 0.9rem;
            }
            .internal-links {
                gap: 8px;
            }
            .internal-link {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            .nav-links {
                flex-direction: column;
            }
            .nav-link {
                min-width: 100%;
            }
            .admin-table {
                font-size: 0.85rem;
            }
            .admin-table th,
            .admin-table td {
                padding: 8px 5px;
            }
        }
        @media (max-width: 480px) {
            .post-header h1 {
                font-size: 1.1rem;
            }
            .post-section {
                padding: 12px;
            }
            .post-section h2 {
                font-size: 1rem;
            }
            .toc-box ul li {
                font-size: 0.85rem;
                margin-bottom: 6px;
            }
            .result-number {
                font-size: 1.2rem;
            }
        }
    </style>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": "<?php echo htmlspecialchars($post['title']); ?>",
        "datePublished": "<?php echo date('c', strtotime($post['created_at'])); ?>",
        "dateModified": "<?php echo date('c', strtotime($post['updated_at'])); ?>",
        "description": "<?php echo htmlspecialchars($post['meta_description']); ?>",
        "keywords": "<?php echo htmlspecialchars($post['meta_keywords']); ?>",
        "author": {
            "@type": "Organization",
            "name": "Satta King"
        },
        "publisher": {
            "@type": "Organization",
            "name": "Satta King"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "/"
            },
            {
                "@type": "ListItem",
                "position": 2,
                "name": "Daily Updates",
                "item": "/daily-updates.php"
            },
            {
                "@type": "ListItem",
                "position": 3,
                "name": "<?php echo htmlspecialchars($gameName); ?>",
                "item": "/post/<?php echo htmlspecialchars($post['slug']); ?>"
            }
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
                "name": "What is <?php echo htmlspecialchars($gameName); ?> Satta King result for <?php echo $formattedDate; ?>?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "<?php echo htmlspecialchars($gameName); ?> Satta King result for <?php echo $formattedDate; ?> is <?php echo $todayResult ? htmlspecialchars($todayResult['result']) : 'awaiting declaration'; ?>. Check our website for live updates."
                }
            },
            {
                "@type": "Question",
                "name": "What time is <?php echo htmlspecialchars($gameName); ?> result declared?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "<?php echo htmlspecialchars($gameName); ?> result is declared at <?php echo $todayResult ? date('h:i A', strtotime($todayResult['result_time'])) : 'fixed time'; ?> every day."
                }
            },
            {
                "@type": "Question",
                "name": "Where can I check <?php echo htmlspecialchars($gameName); ?> chart?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "You can check the complete <?php echo htmlspecialchars($gameName); ?> chart on our Chart page with monthly and yearly historical records."
                }
            },
            {
                "@type": "Question",
                "name": "Is <?php echo htmlspecialchars($gameName); ?> result accurate on this website?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, all results on our website are 100% accurate and sourced directly from official channels."
                }
            },
            {
                "@type": "Question",
                "name": "How many Satta games are available?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Our website covers <?php echo $totalGames; ?>+ Satta King games including Gali, Disawar, Faridabad, Ghaziabad and many more."
                }
            }
        ]
    }
    </script>
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
                        <li><a href="/index.php">Home</a></li>
                        <li><a href="/daily-updates.php">Daily Update</a></li>
                        <li><a href="news.php">News</a></li>
                        <li><a href="/chart.php">Chart</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="post-container">
        <nav class="breadcrumb" style="margin-bottom: 20px;">
            <a href="/" style="color: #9ca3af; text-decoration: none;">Home</a>
            <span style="color: #6b7280; margin: 0 10px;">›</span>
            <a href="/daily-updates.php" style="color: #9ca3af; text-decoration: none;">Daily Updates</a>
            <span style="color: #6b7280; margin: 0 10px;">›</span>
            <span style="color: #e94560;"><?php echo htmlspecialchars($gameName); ?></span>
        </nav>

        <article itemscope itemtype="https://schema.org/Article">
            <header class="post-header">
                <h1 itemprop="headline"><?php echo htmlspecialchars($post['title']); ?></h1>
                <p class="post-meta">
                    <span itemprop="datePublished" content="<?php echo date('c', strtotime($post['created_at'])); ?>">
                        Published on <?php echo date('d F Y, h:i A', strtotime($post['created_at'])); ?>
                    </span>
                    <span style="margin-left: 15px; color: #60a5fa;">📖 5 min read</span>
                    <span style="margin-left: 15px; color: #10b981;">👁 <?php echo number_format($post['views'] ?? 0); ?> views</span>
                </p>
            </header>

            <div class="toc-box" style="background: linear-gradient(145deg, #1f2937 0%, #111827 100%); border-radius: 15px; padding: 20px; margin-bottom: 25px; border: 1px solid #374151;">
                <h3 style="color: #ffd700; margin-bottom: 15px; font-size: 1.1rem;">📋 Table of Contents</h3>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="margin-bottom: 8px;"><a href="#result" style="color: #60a5fa; text-decoration: none;">1. Today's Result</a></li>
                    <li style="margin-bottom: 8px;"><a href="#chart" style="color: #60a5fa; text-decoration: none;">2. Last 7 Days Chart</a></li>
                    <li style="margin-bottom: 8px;"><a href="#about" style="color: #60a5fa; text-decoration: none;">3. About <?php echo htmlspecialchars($gameName); ?></a></li>
                    <li style="margin-bottom: 8px;"><a href="#analysis" style="color: #60a5fa; text-decoration: none;">4. Result Analysis</a></li>
                    <li style="margin-bottom: 8px;"><a href="#faq" style="color: #60a5fa; text-decoration: none;">5. FAQ Section</a></li>
                    <li style="margin-bottom: 8px;"><a href="#related" style="color: #60a5fa; text-decoration: none;">6. Related Results</a></li>
                </ul>
            </div>

            <section class="post-section" id="result">
                <h2><?php echo htmlspecialchars($gameName); ?> Result - <?php echo $formattedDate; ?></h2>
                <p style="color: #d1d5db; margin-bottom: 20px; line-height: 1.8;">
                    Welcome to the official <?php echo htmlspecialchars($gameName); ?> Satta King result page for <?php echo $dayName; ?>, <?php echo $formattedDate; ?>. 
                    Here you can check the live <?php echo htmlspecialchars($gameName); ?> result, complete chart history, and get the fastest updates. 
                    Our website provides accurate and reliable Satta King results updated multiple times throughout the day.
                    <?php echo htmlspecialchars($gameName); ?> is one of the most popular Satta games with thousands of players checking results daily.
                </p>
                
                <?php if ($todayResult): ?>
                <div class="result-card-wrapper" style="text-align: center; padding: 20px 10px;">
                    <div class="result-main-card" style="background: linear-gradient(135deg, #e94560 0%, #ff6b6b 50%, #e94560 100%); padding: 30px 40px; border-radius: 20px; display: inline-block; box-shadow: 0 10px 40px rgba(233, 69, 96, 0.4); max-width: 100%; animation: pulse 2s infinite;">
                        <div style="color: rgba(255,255,255,0.9); font-size: 1rem; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 2px;"><?php echo htmlspecialchars($gameName); ?></div>
                        <div class="main-result-number" style="color: #ffd700; font-size: 3.5rem; font-weight: 800; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); line-height: 1;"><?php echo htmlspecialchars($todayResult['result']); ?></div>
                        <div style="color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-top: 10px;">
                            <span style="background: rgba(0,0,0,0.2); padding: 5px 15px; border-radius: 15px;">
                                <?php echo date('h:i A', strtotime($todayResult['result_time'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes pulse {
                        0%, 100% { transform: scale(1); }
                        50% { transform: scale(1.02); }
                    }
                    @media (max-width: 480px) {
                        .main-result-number { font-size: 2.5rem !important; }
                        .result-main-card { padding: 20px 25px !important; }
                    }
                </style>
                <?php else: ?>
                <div style="text-align: center; padding: 30px;">
                    <div style="background: linear-gradient(135deg, #374151, #1f2937); padding: 30px 40px; border-radius: 20px; display: inline-block;">
                        <div style="color: #9ca3af; font-size: 1rem; margin-bottom: 10px;"><?php echo htmlspecialchars($gameName); ?></div>
                        <div style="color: #f59e0b; font-size: 1.5rem; font-weight: 600;">Waiting for Result</div>
                        <div style="color: #6b7280; font-size: 0.9rem; margin-top: 10px;">Result will be updated soon</div>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <?php if (count($weeklyResults) > 1): ?>
            <section class="post-section" id="chart">
                <h2><?php echo htmlspecialchars($gameName); ?> Last 7 Days Chart</h2>
                <table class="admin-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weeklyResults as $wr): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($wr['result_date'])); ?></td>
                            <td style="color: #ffd700; font-weight: 600; font-size: 1.2rem;"><?php echo htmlspecialchars($wr['result']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <?php endif; ?>
            
            <section class="post-section" id="about">
                <h2>About <?php echo htmlspecialchars($gameName); ?> Satta King Game</h2>
                <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 15px;">
                    <?php echo htmlspecialchars($gameName); ?> is a well-known Satta King game that attracts players from across India. 
                    The game has been running for many years and has established itself as one of the most reliable games in the Satta market.
                    Results are declared at fixed times daily, and our website ensures you get the fastest updates as soon as results are announced.
                </p>
                <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 15px;">
                    On <?php echo $dayName; ?>, <?php echo $formattedDate; ?>, thousands of players checked <?php echo htmlspecialchars($gameName); ?> result on our platform.
                    We maintain complete transparency and accuracy in all our result updates. Our team works 24/7 to bring you the latest Satta King results.
                </p>
                <h3 style="color: #e94560; margin: 20px 0 10px;">Key Features of <?php echo htmlspecialchars($gameName); ?></h3>
                <ul style="color: #d1d5db; line-height: 2; padding-left: 20px;">
                    <li>Results declared at fixed time daily</li>
                    <li>One of the oldest running Satta games</li>
                    <li>Large player base across India</li>
                    <li>Consistent and reliable result timing</li>
                    <li>Complete chart history available</li>
                </ul>
            </section>

            <?php if (count($monthlyResults) > 5): ?>
            <section class="post-section" id="analysis">
                <h2><?php echo htmlspecialchars($gameName); ?> Result Analysis - <?php echo $monthName; ?> <?php echo $year; ?></h2>
                <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 20px;">
                    Based on the last 30 days of <?php echo htmlspecialchars($gameName); ?> results, here is a detailed statistical analysis 
                    to help you understand the patterns and trends in this game.
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <div style="background: rgba(233, 69, 96, 0.1); padding: 20px; border-radius: 10px; text-align: center;">
                        <div style="color: #9ca3af; font-size: 0.9rem;">Total Records Analyzed</div>
                        <div style="color: #ffd700; font-size: 2rem; font-weight: 700;"><?php echo count($monthlyResults); ?></div>
                    </div>
                    <div style="background: rgba(233, 69, 96, 0.1); padding: 20px; border-radius: 10px; text-align: center;">
                        <div style="color: #9ca3af; font-size: 0.9rem;">Even Numbers</div>
                        <div style="color: #ffd700; font-size: 2rem; font-weight: 700;"><?php echo $evenCount; ?></div>
                    </div>
                    <div style="background: rgba(233, 69, 96, 0.1); padding: 20px; border-radius: 10px; text-align: center;">
                        <div style="color: #9ca3af; font-size: 0.9rem;">Odd Numbers</div>
                        <div style="color: #ffd700; font-size: 2rem; font-weight: 700;"><?php echo $oddCount; ?></div>
                    </div>
                </div>
                <?php if (!empty($hotDigits)): ?>
                <h3 style="color: #e94560; margin: 20px 0 10px;">Frequently Appearing Last Digits</h3>
                <p style="color: #d1d5db; line-height: 1.8;">
                    In recent <?php echo htmlspecialchars($gameName); ?> results, the most frequently appearing last digits are: 
                    <strong style="color: #ffd700;"><?php echo implode(', ', $hotDigits); ?></strong>. 
                    This analysis is based on the last <?php echo count($monthlyResults); ?> results.
                </p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (count($otherGamesList) > 0): ?>
            <section class="post-section">
                <h2>Other Satta King Games - <?php echo $formattedDate; ?></h2>
                <p style="color: #d1d5db; margin-bottom: 15px; line-height: 1.8;">
                    Check results of other popular Satta King games for <?php echo $formattedDate; ?>. 
                    We cover all major games including Gali, Disawar, Faridabad, Ghaziabad, and <?php echo $totalGames; ?>+ other games.
                </p>
                <div class="internal-links">
                    <?php foreach ($otherGamesList as $og): ?>
                    <a href="/post/<?php echo htmlspecialchars($og['slug']); ?>" class="internal-link"><?php echo htmlspecialchars($og['games_included']); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="post-section" id="faq">
                <h2><?php echo htmlspecialchars($gameName); ?> Satta King - Complete Guide & FAQ</h2>
                <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 20px;">
                    Find answers to frequently asked questions about <?php echo htmlspecialchars($gameName); ?> Satta King game, 
                    result timings, chart, and more. This comprehensive FAQ section covers everything you need to know.
                </p>
                
                <div class="faq-item">
                    <p class="faq-question">What is <?php echo htmlspecialchars($gameName); ?> Satta King result for <?php echo $formattedDate; ?>?</p>
                    <p class="faq-answer">The <?php echo htmlspecialchars($gameName); ?> Satta King result for <?php echo $dayName; ?>, <?php echo $formattedDate; ?> is <strong style="color: #ffd700;"><?php echo $todayResult ? htmlspecialchars($todayResult['result']) : 'awaiting declaration'; ?></strong>. Results are updated live on this page as soon as they are announced. Bookmark this page for instant access to daily results.</p>
                </div>
                
                <div class="faq-item">
                    <p class="faq-question">What time is <?php echo htmlspecialchars($gameName); ?> result declared?</p>
                    <p class="faq-answer"><?php echo htmlspecialchars($gameName); ?> result is declared at <?php echo $todayResult ? date('h:i A', strtotime($todayResult['result_time'])) : 'its scheduled time'; ?> every day. The timing remains consistent, and our website updates results within seconds of the official announcement.</p>
                </div>
                
                <div class="faq-item">
                    <p class="faq-question">Where can I check <?php echo htmlspecialchars($gameName); ?> chart and history?</p>
                    <p class="faq-answer">You can view the complete <?php echo htmlspecialchars($gameName); ?> chart on our Chart page. The chart includes monthly records, yearly data, and complete historical results dating back several years. This helps in analyzing patterns and trends.</p>
                </div>
                
                <div class="faq-item">
                    <p class="faq-question">Is <?php echo htmlspecialchars($gameName); ?> result on this website accurate?</p>
                    <p class="faq-answer">Yes, all results on our website are 100% accurate and sourced directly from official channels. We have been providing Satta King results for years and maintain a reputation for accuracy and speed.</p>
                </div>
                
                <div class="faq-item">
                    <p class="faq-question">How many games results are available on this website?</p>
                    <p class="faq-answer">Our website covers <?php echo $totalGames; ?>+ Satta King games including popular ones like Gali, Disawar, Faridabad, Ghaziabad, Delhi Bazar, and many more. All results are updated in real-time.</p>
                </div>
                
                <div class="faq-item">
                    <p class="faq-question">Can I check <?php echo htmlspecialchars($gameName); ?> result on mobile?</p>
                    <p class="faq-answer">Yes, our website is fully mobile-responsive. You can check <?php echo htmlspecialchars($gameName); ?> result on any device - mobile phone, tablet, or desktop computer. The experience is optimized for all screen sizes.</p>
                </div>
                
                <div class="faq-item">
                    <p class="faq-question">What are the last 7 days results for <?php echo htmlspecialchars($gameName); ?>?</p>
                    <p class="faq-answer">The last 7 days <?php echo htmlspecialchars($gameName); ?> results are displayed in the chart section above. You can also visit our Chart page for complete monthly and yearly records.</p>
                </div>
            </section>

            <section class="post-section">
                <h2>Why Choose Our Website for <?php echo htmlspecialchars($gameName); ?> Results?</h2>
                <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 15px;">
                    Our website has been the trusted source for Satta King results for many years. Here's why thousands of players choose us daily:
                </p>
                <ul style="color: #d1d5db; line-height: 2; padding-left: 20px; margin-bottom: 15px;">
                    <li><strong style="color: #ffd700;">Fastest Updates:</strong> Results appear on our site within seconds of official declaration</li>
                    <li><strong style="color: #ffd700;">100% Accuracy:</strong> We never post incorrect or unverified results</li>
                    <li><strong style="color: #ffd700;"><?php echo $totalGames; ?>+ Games:</strong> All popular Satta games covered in one place</li>
                    <li><strong style="color: #ffd700;">Complete Charts:</strong> Access historical data going back several years</li>
                    <li><strong style="color: #ffd700;">Mobile Friendly:</strong> Check results on any device, anywhere, anytime</li>
                    <li><strong style="color: #ffd700;">Daily Updates:</strong> Fresh content and results updated multiple times daily</li>
                </ul>
                <p style="color: #d1d5db; line-height: 1.8;">
                    Bookmark this page and visit daily to check <?php echo htmlspecialchars($gameName); ?> Satta King result. 
                    Share with friends who are also interested in Satta King results.
                </p>
            </section>

            <section class="post-section" id="related">
                <h2>Related <?php echo htmlspecialchars($gameName); ?> Results</h2>
                <div class="internal-links">
                    <a href="/index.php" class="internal-link">Satta King Home</a>
                    <a href="/daily-updates.php" class="internal-link">All Daily Updates</a>
                    <a href="/chart.php?game=<?php echo urlencode($gameName); ?>" class="internal-link"><?php echo htmlspecialchars($gameName); ?> Chart</a>
                    <?php foreach ($relatedPostsList as $rp): ?>
                    <a href="/post/<?php echo htmlspecialchars($rp['slug']); ?>" class="internal-link"><?php echo date('d M', strtotime($rp['post_date'])); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="post-section" style="background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%); border: 1px solid #e94560;">
                <h2 style="color: #e94560;">⚠️ Important Disclaimer</h2>
                <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 15px;">
                    This website is for <strong>informational and entertainment purposes only</strong>. We do not encourage or promote any form of gambling or betting. 
                    Satta King and similar games are illegal in many parts of India and other countries. Please be aware of the laws in your jurisdiction before participating in any such activities.
                </p>
                <p style="color: #d1d5db; line-height: 1.8; margin-bottom: 15px;">
                    We are not responsible for any financial losses or legal issues that may arise from the use of information provided on this website. 
                    All results displayed are sourced from publicly available information and are provided as-is without any warranty.
                </p>
                <p style="color: #d1d5db; line-height: 1.8;">
                    <strong style="color: #ffd700;">Age Restriction:</strong> This website is intended for users aged 18 years and above. 
                    If you are under 18, please exit this website immediately. Gambling addiction can be harmful - if you or someone you know has a gambling problem, 
                    please seek professional help.
                </p>
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #374151;">
                    <a href="/page/disclaimer" style="color: #60a5fa; text-decoration: none;">Read Full Disclaimer →</a>
                    <span style="margin: 0 10px; color: #6b7280;">|</span>
                    <a href="/page/privacy-policy" style="color: #60a5fa; text-decoration: none;">Privacy Policy →</a>
                    <span style="margin: 0 10px; color: #6b7280;">|</span>
                    <a href="/page/terms-conditions" style="color: #60a5fa; text-decoration: none;">Terms & Conditions →</a>
                </div>
            </section>

            <div style="text-align: center; margin: 30px 0; padding: 20px; background: linear-gradient(145deg, #1f2937 0%, #111827 100%); border-radius: 15px;">
                <p style="color: #9ca3af; margin-bottom: 15px;">Share this result with friends:</p>
                <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                    <a href="https://wa.me/?text=<?php echo urlencode($post['title'] . ' - Check result here!'); ?>" target="_blank" rel="nofollow noopener" 
                       style="background: #25D366; color: #fff; padding: 12px 20px; border-radius: 25px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        WhatsApp
                    </a>
                    <a href="https://telegram.me/share/url?url=&text=<?php echo urlencode($post['title']); ?>" target="_blank" rel="nofollow noopener"
                       style="background: #0088cc; color: #fff; padding: 12px 20px; border-radius: 25px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                        Telegram
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?quote=<?php echo urlencode($post['title']); ?>" target="_blank" rel="nofollow noopener"
                       style="background: #1877F2; color: #fff; padding: 12px 20px; border-radius: 25px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        Facebook
                    </a>
                </div>
            </div>
        </article>
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
            <p>Satta King Fast Results | Daily Updates</p>
        </div>
    </footer>

    <script>
        function toggleMenu() {
            document.getElementById('mainNav').classList.toggle('active');
        }
    </script>
</body>
</html>
