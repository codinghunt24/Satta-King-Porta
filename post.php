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
            .post-header h1 {
                font-size: 1.5rem;
            }
            .results-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .nav-links {
                flex-direction: column;
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
        "keywords": "<?php echo htmlspecialchars($post['meta_keywords']); ?>"
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
        <article>
            <header class="post-header">
                <h1><?php echo htmlspecialchars($post['title']); ?></h1>
                <p class="post-meta">Published on <?php echo date('d F Y, h:i A', strtotime($post['created_at'])); ?></p>
            </header>

            <section class="post-section">
                <h2><?php echo htmlspecialchars($gameName); ?> Result - <?php echo date('d F Y', strtotime($postDate)); ?></h2>
                <p style="color: #d1d5db; margin-bottom: 20px;">
                    Check <?php echo htmlspecialchars($gameName); ?> Satta King result for <?php echo date('d F Y', strtotime($postDate)); ?>. 
                    Get live <?php echo htmlspecialchars($gameName); ?> result, chart, and fast updates.
                </p>
                
                <?php if ($todayResult): ?>
                <div style="text-align: center; padding: 30px;">
                    <div style="background: linear-gradient(135deg, #e94560, #ff6b6b); padding: 40px; border-radius: 20px; display: inline-block;">
                        <div style="color: #fff; font-size: 1.2rem; margin-bottom: 10px;"><?php echo htmlspecialchars($gameName); ?></div>
                        <div style="color: #ffd700; font-size: 4rem; font-weight: 700;"><?php echo htmlspecialchars($todayResult['result']); ?></div>
                        <div style="color: #fff; font-size: 1rem; margin-top: 10px;"><?php echo date('h:i A', strtotime($todayResult['result_time'])); ?></div>
                    </div>
                </div>
                <?php else: ?>
                <p style="color: #9ca3af; text-align: center;">Result will be updated soon.</p>
                <?php endif; ?>
            </section>

            <?php if (count($weeklyResults) > 1): ?>
            <section class="post-section">
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
            
            <?php if (count($otherGamesList) > 0): ?>
            <section class="post-section">
                <h2>Other Games - <?php echo date('d M Y', strtotime($postDate)); ?></h2>
                <div class="internal-links">
                    <?php foreach ($otherGamesList as $og): ?>
                    <a href="/post/<?php echo htmlspecialchars($og['slug']); ?>" class="internal-link"><?php echo htmlspecialchars($og['games_included']); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="post-section">
                <h2><?php echo htmlspecialchars($gameName); ?> FAQ</h2>
                
                <div class="faq-item">
                    <p class="faq-question">What is <?php echo htmlspecialchars($gameName); ?> Satta King result for <?php echo date('d F Y', strtotime($postDate)); ?>?</p>
                    <p class="faq-answer"><?php echo htmlspecialchars($gameName); ?> Satta King result for <?php echo date('d F Y', strtotime($postDate)); ?> is <?php echo $todayResult ? htmlspecialchars($todayResult['result']) : 'not yet declared'; ?>. Check the result section above for live updates.</p>
                </div>
                
                <div class="faq-item">
                    <p class="faq-question">When is <?php echo htmlspecialchars($gameName); ?> result declared?</p>
                    <p class="faq-answer"><?php echo htmlspecialchars($gameName); ?> result is declared at <?php echo $todayResult ? date('h:i A', strtotime($todayResult['result_time'])) : 'fixed time'; ?> daily. Check our website for fast and accurate results.</p>
                </div>
                
                <div class="faq-item">
                    <p class="faq-question">How to check <?php echo htmlspecialchars($gameName); ?> chart?</p>
                    <p class="faq-answer">You can check the complete <?php echo htmlspecialchars($gameName); ?> chart on our website. Visit the Chart section to see monthly records with all past results.</p>
                </div>
            </section>

            <section class="post-section">
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
