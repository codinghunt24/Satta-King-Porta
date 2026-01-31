<?php
session_start();

$installed = file_exists(__DIR__ . '/.installed');
if ($installed && !isset($_GET['force'])) {
    die('<!DOCTYPE html><html><head><title>Already Installed</title><style>body{font-family:sans-serif;background:#020d1f;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.box{background:#1e3a5f;padding:40px;border-radius:15px;text-align:center;max-width:400px;}h1{color:#fbbf24;margin-bottom:20px;}a{color:#60a5fa;}</style></head><body><div class="box"><h1>Already Installed</h1><p>The website has already been installed.</p><p><a href="/">Go to Homepage</a> | <a href="/admin.php">Admin Panel</a></p></div></body></html>');
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_connection'])) {
        $dbHost = trim($_POST['db_host']);
        $dbPort = trim($_POST['db_port']) ?: '3306';
        $dbName = trim($_POST['db_name']);
        $dbUser = trim($_POST['db_user']);
        $dbPass = $_POST['db_password'];
        
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $testPdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $testPdo->query("SELECT 1");
            
            $_SESSION['install_db'] = [
                'host' => $dbHost,
                'port' => $dbPort,
                'name' => $dbName,
                'user' => $dbUser,
                'password' => $dbPass
            ];
            
            header('Location: install.php?step=2');
            exit;
        } catch (PDOException $e) {
            $error = "Database connection failed: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['set_admin'])) {
        $adminPassword = trim($_POST['admin_password']);
        $confirmPassword = trim($_POST['confirm_password']);
        
        if (strlen($adminPassword) < 6) {
            $error = "Password must be at least 6 characters long!";
        } elseif ($adminPassword !== $confirmPassword) {
            $error = "Passwords do not match!";
        } else {
            $_SESSION['install_admin'] = $adminPassword;
            header('Location: install.php?step=3');
            exit;
        }
    }
    
    if (isset($_POST['run_install'])) {
        if (!isset($_SESSION['install_db']) || !isset($_SESSION['install_admin'])) {
            header('Location: install.php?step=1');
            exit;
        }
        
        $db = $_SESSION['install_db'];
        $adminPass = $_SESSION['install_admin'];
        
        $databaseConfig = "<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

\$db_host = '{$db['host']}';
\$db_port = '{$db['port']}';
\$db_name = '{$db['name']}';
\$db_user = '{$db['user']}';
\$db_password = '{$db['password']}';

try {
    \$dsn = \"mysql:host=\$db_host;port=\$db_port;dbname=\$db_name;charset=utf8mb4\";
    \$pdo = new PDO(\$dsn, \$db_user, \$db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException \$e) {
    die('Database connection failed: ' . \$e->getMessage());
}
";
        
        try {
            file_put_contents(__DIR__ . '/config/database.php', $databaseConfig);
            
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS satta_results (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    game_name VARCHAR(100) NOT NULL,
                    result VARCHAR(10),
                    result_time TIME,
                    result_date DATE,
                    source_url TEXT,
                    scraped_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_game_date (game_name, result_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS games (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    time_slot TIME NOT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS scrape_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    source_url TEXT NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    message TEXT,
                    records_updated INT DEFAULT 0,
                    scraped_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS posts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL UNIQUE,
                    meta_description TEXT,
                    meta_keywords TEXT,
                    games_included TEXT,
                    post_date DATE,
                    views INT DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ad_placements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    placement_name VARCHAR(50) NOT NULL UNIQUE,
                    ad_code TEXT,
                    is_active TINYINT(1) DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS site_pages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    slug VARCHAR(50) NOT NULL UNIQUE,
                    title VARCHAR(200) NOT NULL,
                    content TEXT,
                    meta_title VARCHAR(200),
                    meta_description TEXT,
                    is_published TINYINT(1) DEFAULT 1,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS site_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS news_posts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL UNIQUE,
                    excerpt TEXT,
                    content TEXT,
                    featured_image VARCHAR(500),
                    meta_title VARCHAR(200),
                    meta_description TEXT,
                    meta_keywords TEXT,
                    status VARCHAR(20) DEFAULT 'draft',
                    views INT DEFAULT 0,
                    published_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $checkGames = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
            if ($checkGames == 0) {
                $games = [
                    ['Gali', '11:30:00'],
                    ['Desawar', '05:00:00'],
                    ['Faridabad', '18:15:00'],
                    ['Ghaziabad', '21:30:00'],
                    ['Delhi Bazaar', '15:00:00'],
                    ['Shri Ganesh', '17:30:00'],
                ];
                $stmt = $pdo->prepare("INSERT INTO games (name, time_slot) VALUES (?, ?)");
                foreach ($games as $game) {
                    $stmt->execute($game);
                }
            }
            
            $checkAds = $pdo->query("SELECT COUNT(*) FROM ad_placements")->fetchColumn();
            if ($checkAds == 0) {
                $placements = ['header_ad', 'after_result', 'sidebar', 'footer_ad', 'between_posts'];
                $stmt = $pdo->prepare("INSERT INTO ad_placements (placement_name, is_active) VALUES (?, 0)");
                foreach ($placements as $p) {
                    $stmt->execute([$p]);
                }
            }
            
            $checkPages = $pdo->query("SELECT COUNT(*) FROM site_pages")->fetchColumn();
            if ($checkPages == 0) {
                $pages = [
                    ['about', 'About Us', 'Welcome to Satta King - your trusted source for fast and accurate Satta King results.', 'About Us - Satta King', 'Learn about Satta King website.'],
                    ['contact', 'Contact Us', 'For any queries or feedback, please contact us through our website.', 'Contact Us - Satta King', 'Contact Satta King for queries.'],
                    ['disclaimer', 'Disclaimer', 'This website is for informational purposes only. We do not promote gambling.', 'Disclaimer - Satta King', 'Read our disclaimer.'],
                    ['privacy-policy', 'Privacy Policy', 'Your privacy is important to us. We collect minimal data.', 'Privacy Policy - Satta King', 'Read our privacy policy.'],
                    ['terms-conditions', 'Terms & Conditions', 'By using this website, you agree to our terms and conditions.', 'Terms & Conditions - Satta King', 'Read our terms.']
                ];
                $stmt = $pdo->prepare("INSERT INTO site_pages (slug, title, content, meta_title, meta_description) VALUES (?, ?, ?, ?, ?)");
                foreach ($pages as $page) {
                    $stmt->execute($page);
                }
            }
            
            $checkSettings = $pdo->query("SELECT COUNT(*) FROM site_settings")->fetchColumn();
            if ($checkSettings == 0) {
                $settings = [
                    ['google_analytics_code', ''],
                    ['meta_verification_google', ''],
                    ['meta_verification_bing', '']
                ];
                $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
                foreach ($settings as $setting) {
                    $stmt->execute($setting);
                }
            }
            
            $envContent = "SESSION_SECRET={$adminPass}\n";
            file_put_contents(__DIR__ . '/.env', $envContent);
            
            file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
            
            unset($_SESSION['install_db']);
            unset($_SESSION['install_admin']);
            
            header('Location: install.php?step=4');
            exit;
            
        } catch (Exception $e) {
            $error = "Installation failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Satta King Website</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #020d1f 0%, #0a1628 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .installer {
            background: linear-gradient(145deg, #1e3a5f 0%, #0f172a 100%);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: #fff; font-size: 2rem; }
        .logo h1 span { color: #fbbf24; }
        .logo p { color: #9ca3af; margin-top: 5px; }
        .steps {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        .step-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-weight: bold;
        }
        .step-dot.active { background: #fbbf24; color: #000; }
        .step-dot.done { background: #34d399; color: #fff; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #fbbf24; margin-bottom: 8px; font-weight: 500; }
        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #374151;
            border-radius: 8px;
            background: #0f172a;
            color: #fff;
            font-size: 14px;
        }
        input:focus { outline: none; border-color: #fbbf24; }
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #000; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(251,191,36,0.4); }
        .btn-success { background: linear-gradient(135deg, #34d399, #10b981); color: #fff; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-error { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid #ef4444; }
        .alert-success { background: rgba(52, 211, 153, 0.2); color: #6ee7b7; border: 1px solid #34d399; }
        .step-title { color: #fff; font-size: 1.3rem; margin-bottom: 5px; text-align: center; }
        .step-desc { color: #9ca3af; text-align: center; margin-bottom: 25px; font-size: 14px; }
        .success-box { text-align: center; }
        .success-box .checkmark { font-size: 80px; margin-bottom: 20px; }
        .success-box h2 { color: #34d399; margin-bottom: 15px; }
        .success-box p { color: #d1d5db; margin-bottom: 20px; }
        .success-box .links { display: flex; gap: 15px; justify-content: center; }
        .success-box a {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        .success-box .link-home { background: #fbbf24; color: #000; }
        .success-box .link-admin { background: #60a5fa; color: #fff; }
        .hint { color: #9ca3af; font-size: 12px; margin-top: 5px; }
        .row { display: flex; gap: 15px; }
        .row .form-group { flex: 1; }
    </style>
</head>
<body>
    <div class="installer">
        <div class="logo">
            <h1>Satta <span>King</span></h1>
            <p>Website Installation Wizard</p>
        </div>
        
        <div class="steps">
            <div class="step-dot <?php echo $step >= 1 ? ($step > 1 ? 'done' : 'active') : ''; ?>">1</div>
            <div class="step-dot <?php echo $step >= 2 ? ($step > 2 ? 'done' : 'active') : ''; ?>">2</div>
            <div class="step-dot <?php echo $step >= 3 ? ($step > 3 ? 'done' : 'active') : ''; ?>">3</div>
            <div class="step-dot <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
        <h3 class="step-title">Database Configuration</h3>
        <p class="step-desc">Enter your MySQL database details</p>
        
        <form method="POST">
            <div class="row">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="text" name="db_port" value="3306">
                </div>
            </div>
            
            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" placeholder="satta_king" required>
            </div>
            
            <div class="form-group">
                <label>Database User</label>
                <input type="text" name="db_user" placeholder="root" required>
            </div>
            
            <div class="form-group">
                <label>Database Password</label>
                <input type="password" name="db_password" placeholder="Enter password">
            </div>
            
            <button type="submit" name="test_connection" class="btn btn-primary">Test Connection & Continue</button>
        </form>
        
        <?php elseif ($step === 2): ?>
        <h3 class="step-title">Admin Password</h3>
        <p class="step-desc">Set a strong password for admin panel access</p>
        
        <form method="POST">
            <div class="form-group">
                <label>Admin Password</label>
                <input type="password" name="admin_password" placeholder="Enter admin password" required>
                <p class="hint">Minimum 6 characters</p>
            </div>
            
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm password" required>
            </div>
            
            <button type="submit" name="set_admin" class="btn btn-primary">Continue</button>
        </form>
        
        <?php elseif ($step === 3): ?>
        <h3 class="step-title">Ready to Install</h3>
        <p class="step-desc">Click the button below to install your website</p>
        
        <div style="background: rgba(59, 130, 246, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 25px;">
            <p style="color: #60a5fa; margin-bottom: 10px;"><strong>This will:</strong></p>
            <ul style="color: #d1d5db; margin-left: 20px; line-height: 1.8;">
                <li>Create database configuration file</li>
                <li>Set up all required database tables</li>
                <li>Add default games and settings</li>
                <li>Create footer pages</li>
                <li>Configure admin password</li>
            </ul>
        </div>
        
        <form method="POST">
            <button type="submit" name="run_install" class="btn btn-success">Install Now</button>
        </form>
        
        <?php elseif ($step === 4): ?>
        <div class="success-box">
            <div class="checkmark">✅</div>
            <h2>Installation Complete!</h2>
            <p>Your Satta King website has been successfully installed. You can now access your website and admin panel.</p>
            <div class="links">
                <a href="/" class="link-home">Visit Website</a>
                <a href="/admin.php" class="link-admin">Admin Panel</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
