<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/config/database.php';

$posts = $pdo->query("
    SELECT id, title, slug, meta_description, games_included, post_date, created_at 
    FROM posts 
    ORDER BY post_date DESC, created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$gradients = [
    'linear-gradient(135deg, #e94560 0%, #ff6b6b 100%)',
    'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
    'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
    'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
    'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
    'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)'
];
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Satta King Results | Satta King Fast Updates</title>
    <meta name="description" content="Get daily Satta King results updates. Check Gali, Disawar, Faridabad, Ghaziabad and all game results with Satta King charts and live updates.">
    <meta name="keywords" content="satta king daily update, satta king result today, gali disawar result, satta king fast, daily satta king">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="/daily-updates.php">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .post-card {
            background: linear-gradient(145deg, #1f2937 0%, #111827 100%);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid #374151;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(233, 69, 96, 0.3);
        }
        .post-thumbnail {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            border-radius: 15px 15px 0 0;
        }
        .post-thumbnail-text {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .post-content {
            padding: 20px;
        }
        .post-date {
            color: #9ca3af;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        .post-title {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        .post-title a {
            color: inherit;
            text-decoration: none;
        }
        .post-title a:hover {
            color: #e94560;
        }
        .post-games {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .game-tag {
            background: rgba(233, 69, 96, 0.2);
            color: #e94560;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
        }
        .page-header {
            text-align: center;
            padding: 40px 0;
        }
        .page-header h1 {
            color: #e94560;
            font-size: 2.2rem;
            margin-bottom: 15px;
        }
        .page-header p {
            color: #9ca3af;
            font-size: 1.1rem;
        }
        .no-posts {
            text-align: center;
            color: #9ca3af;
            padding: 60px 20px;
            background: linear-gradient(145deg, #1f2937 0%, #111827 100%);
            border-radius: 15px;
        }
        @media (max-width: 768px) {
            .posts-grid {
                grid-template-columns: 1fr;
            }
            .page-header h1 {
                font-size: 1.6rem;
            }
        }
    </style>
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
                        <li><a href="daily-updates.php" class="active">Daily Update</a></li>
                        <li><a href="news.php">News</a></li>
                        <li><a href="chart.php">Chart</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="page-header">
            <h1>Daily Satta King Updates</h1>
            <p>Check daily Satta King results for Gali, Disawar, Faridabad, Ghaziabad and more games</p>
        </div>

        <?php if (count($posts) > 0): ?>
        <div class="posts-grid">
            <?php foreach ($posts as $index => $post): 
                $gradient = $gradients[$index % count($gradients)];
                $games = explode(',', $post['games_included']);
            ?>
            <article class="post-card">
                <div class="post-thumbnail" style="background: <?php echo $gradient; ?>">
                    <div class="post-thumbnail-text">
                        <?php echo date('d M Y', strtotime($post['post_date'])); ?>
                    </div>
                </div>
                <div class="post-content">
                    <div class="post-date"><?php echo date('d F Y, h:i A', strtotime($post['created_at'])); ?></div>
                    <h2 class="post-title">
                        <a href="post/<?php echo htmlspecialchars($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                    </h2>
                    <div class="post-games">
                        <?php foreach (array_slice($games, 0, 4) as $game): ?>
                            <span class="game-tag"><?php echo htmlspecialchars(trim($game)); ?></span>
                        <?php endforeach; ?>
                        <?php if (count($games) > 4): ?>
                            <span class="game-tag">+<?php echo count($games) - 4; ?> more</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-posts">
            <h3>No Posts Yet</h3>
            <p>Daily updates will appear here once published from admin panel.</p>
        </div>
        <?php endif; ?>
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
