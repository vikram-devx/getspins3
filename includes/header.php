<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

$auth = new Auth();
$current_user = $auth->getUser();

// Determine if this is a public page (no logged-in user required)
$current_page = basename($_SERVER['PHP_SELF']);
// Also check for pretty URLs (without .php extension)
$request_uri = $_SERVER['REQUEST_URI'];
$request_path = parse_url($request_uri, PHP_URL_PATH);
$request_path = trim($request_path, '/');
$is_public_page = !$auth->isLoggedIn() || 
                  in_array($current_page, ['index.php', 'login.php', 'register.php']) || 
                  in_array($request_path, ['', 'index', 'login', 'register']);

// Get app name and logo from settings
$app_name = getSetting('app_name', APP_NAME);
$app_logo = getSetting('app_logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $app_name; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/new-promo-slider.css" rel="stylesheet">
    
    <!-- EmailJS SDK -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js"></script>
    
    <!-- EmailJS Credentials -->
    <script type="text/javascript">
        <?php
        // Retrieve EmailJS settings directly from the database for more reliable access
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $emailjs_settings = [];
        
        try {
            $stmt = $conn->query("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key IN ('emailjs_user_id', 'emailjs_service_id', 'emailjs_template_id')");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $emailjs_settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            // Silently fail - table may not exist yet
        }
        ?>
        
        // Set EmailJS credentials as global variables
        window.EMAILJS_USER_ID = '<?php echo isset($emailjs_settings['emailjs_user_id']) ? $emailjs_settings['emailjs_user_id'] : getenv("EMAILJS_USER_ID"); ?>';
        window.EMAILJS_SERVICE_ID = '<?php echo isset($emailjs_settings['emailjs_service_id']) ? $emailjs_settings['emailjs_service_id'] : getenv("EMAILJS_SERVICE_ID"); ?>';
        window.EMAILJS_TEMPLATE_ID = '<?php echo isset($emailjs_settings['emailjs_template_id']) ? $emailjs_settings['emailjs_template_id'] : getenv("EMAILJS_TEMPLATE_ID"); ?>';
        
        // Log to console if EmailJS is properly configured
        <?php if ($auth->isAdmin()): ?>
        console.log('Admin EmailJS status:', window.EMAILJS_USER_ID ? 'Configured' : 'Not configured');
        <?php else: ?>
        console.log('EmailJS status:', window.EMAILJS_USER_ID ? 'Configured' : 'Not configured');
        <?php endif; ?>
    </script>
</head>
<?php
// Determine the current page for body class
$current_page_name = basename($_SERVER['PHP_SELF'], '.php');
$body_class = '';

// Add specific classes for pages that need extra padding
if ($current_page_name === 'dashboard') {
    $body_class = 'dashboard-page';
} elseif ($current_page_name === 'tasks') {
    $body_class = 'tasks-page';
} elseif ($current_page_name === 'rewards') {
    $body_class = 'rewards-page';
} elseif ($current_page_name === 'redeem') {
    $body_class = 'rewards-page redeem-page'; // Use rewards-page class for consistent styling
} elseif ($current_page_name === 'referrals') {
    $body_class = 'referrals-page';
} elseif ($current_page_name === 'leaderboard') {
    $body_class = 'leaderboard-page';
} elseif ($current_page_name === 'profile') {
    $body_class = 'profile-page';
}
?>
<body class="<?php echo $body_class; ?>">
    <!-- Fixed Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-header">
        <div class="container px-3">
            <a class="navbar-brand" href="/">
                <?php if ($is_public_page && !empty($app_logo)): ?>
                <img src="<?php echo $app_logo; ?>" alt="<?php echo htmlspecialchars($app_name); ?>" height="30" class="d-inline-block align-text-top">
                <?php else: ?>
                <?php echo htmlspecialchars($app_name); ?>
                <?php endif; ?>
            </a>
            
            <!-- Spacer div to push mobile elements to the right -->
            <div class="flex-grow-1 d-lg-none"></div>
            
            <!-- Mobile menu controls - different styles for logged in vs non-logged in users -->
            <?php if ($auth->isLoggedIn()): ?>
            <!-- For logged-in users: avatar toggle for slide menu (mobile only) -->
            <button class="navbar-toggler d-lg-none user-avatar-toggle" type="button" id="mobileMenuToggle" aria-label="Toggle mobile menu">
                <span class="user-avatar-icon"><?php echo strtoupper(substr($current_user['username'], 0, 1)); ?></span>
            </button>
            <!-- No hamburger toggle for desktop screens - navbar is always visible -->
            <?php else: ?>
            <!-- For non-logged in users: simplified navigation with icons only -->
            <div class="d-flex align-items-center non-logged-nav">
                <a href="/login" class="nav-action-btn me-2" title="Login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span class="d-none d-sm-inline ms-1">Login</span>
                </a>
                <a href="/register" class="nav-action-btn btn-register" title="Register">
                    <i class="fas fa-user-plus"></i>
                    <span class="d-none d-sm-inline ms-1">Register</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Navigation menu items - always visible on desktop, collapse on mobile -->
            <?php if ($auth->isLoggedIn()): ?>
            <div class="navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/tasks">Earn Points</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/rewards">Rewards</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/leaderboard">Leaderboard</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <?php if ($auth->isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/">Admin Panel</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-coins"></i> <?php echo formatPoints($current_user['points']); ?> Points
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="/dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="/profile"><i class="fas fa-user-cog"></i> My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/login?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <?php if ($auth->isLoggedIn()): ?>
    <!-- New Mobile Slide Menu - Only visible on mobile devices -->
    <div class="menu-backdrop d-lg-none"></div>
    <div class="mobile-slide-menu d-lg-none">
        <div class="mobile-menu-header">
            <button class="mobile-menu-close" aria-label="Close menu">
                <i class="fas fa-times"></i>
            </button>
            <div class="mobile-user-avatar">
                <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
            </div>
            <div class="mobile-user-info">
                <div class="mobile-user-name">
                    <?php echo htmlspecialchars($current_user['username']); ?>
                    <?php if ($auth->isAdmin()): ?>
                    <span class="mobile-admin-badge">Admin</span>
                    <?php endif; ?>
                </div>
                <div class="mobile-user-points">
                    <i class="fas fa-coins"></i> <?php echo formatPoints($current_user['points']); ?> Points
                </div>
            </div>
        </div>
        
        <ul class="mobile-menu-list">
            <li class="mobile-menu-item">
                <a href="/dashboard" class="mobile-menu-link <?php echo ($current_page === 'dashboard.php' || $request_path === 'dashboard') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="mobile-menu-item">
                <a href="/tasks" class="mobile-menu-link <?php echo ($current_page === 'tasks.php' || $request_path === 'tasks') ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i> Earn Points
                </a>
            </li>
            <li class="mobile-menu-item">
                <a href="/rewards" class="mobile-menu-link <?php echo ($current_page === 'rewards.php' || $request_path === 'rewards') ? 'active' : ''; ?>">
                    <i class="fas fa-gift"></i> Rewards
                </a>
            </li>
            <li class="mobile-menu-item">
                <a href="/referrals" class="mobile-menu-link <?php echo ($current_page === 'referrals.php' || $request_path === 'referrals') ? 'active' : ''; ?>">
                    <i class="fas fa-user-plus"></i> Refer Friends
                </a>
            </li>
            <li class="mobile-menu-item">
                <a href="/leaderboard" class="mobile-menu-link <?php echo ($current_page === 'leaderboard.php' || $request_path === 'leaderboard') ? 'active' : ''; ?>">
                    <i class="fas fa-trophy"></i> Leaderboard
                </a>
            </li>
            <li class="mobile-menu-item">
                <a href="/profile" class="mobile-menu-link <?php echo ($current_page === 'profile.php' || $request_path === 'profile') ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i> My Profile
                </a>
            </li>
            <?php if ($auth->isAdmin()): ?>
            <li class="mobile-menu-item">
                <a href="/admin/" class="mobile-menu-link">
                    <i class="fas fa-tools"></i> Admin Panel
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="mobile-menu-footer">
            <a href="/login?action=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <div class="text-center mt-3">
                <small class="text-muted">Version 1.0 &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($app_name); ?></small>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Main Content Container with spacing for fixed header -->
    <div class="<?php 
        if (($current_page === 'index.php' || $request_path === '' || $request_path === 'index') && !$auth->isLoggedIn()) {
            echo 'full-width-container';
        } else {
            echo $auth->isLoggedIn() ? 'container py-4 content-with-fixed-header' : 'container py-4';
        }
    ?>"><?php 
    /* Add content-with-fixed-header class only for authenticated users */
    ?>
