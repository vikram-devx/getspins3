<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: ' . url('login'));
    exit;
}

$current_user = $auth->getUser();
$user_id = $current_user['id'];

// Get user stats
$stats = getUserStats($user_id);

// Get recent transactions
$transactions = getUserTransactions($user_id, 5);

// Get user redemptions
$redemptions = getUserRedemptions($user_id);

// Get top users for mini leaderboard (top 5)
$db = Database::getInstance();
$conn = $db->getConnection();
$leaderboard_query = "SELECT id, username, points, 
    (SELECT COUNT(*) FROM users u2 WHERE u2.points > u1.points) + 1 AS rank 
    FROM users u1 
    WHERE is_admin = 0 
    ORDER BY points DESC 
    LIMIT 5";
$leaderboard_result = $conn->query($leaderboard_query);
$top_users = [];
if ($leaderboard_result) {
    while ($row = $leaderboard_result->fetch(PDO::FETCH_ASSOC)) {
        $top_users[] = $row;
    }
}

// Get current user's rank
$rank_query = "SELECT (SELECT COUNT(*) FROM users u2 WHERE u2.points > u1.points) + 1 AS rank 
              FROM users u1 WHERE id = :user_id";
$stmt = $conn->prepare($rank_query);
$stmt->execute(['user_id' => $user_id]);
$user_rank = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="dashboard-container">
    <!-- Dashboard Sidebar - Hidden on mobile, visible on desktop -->
    <div class="dashboard-sidebar d-none d-lg-block">
        <?php include 'includes/sidebar.php'; ?>
    </div>
    
    <!-- Dashboard Content -->
    <div class="dashboard-content w-100">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Dashboard Overview</h5>
            </div>
            <div class="card-body">
                <!-- Main Dashboard Content -->
                    
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-6 col-md-6 col-lg-3 mb-3">
                            <div class="card bg-primary text-white h-100 dashboard-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-coins stat-icon me-2"></i>
                                        <h5 class="card-title mb-0">Available Points</h5>
                                    </div>
                                    <h2 class="mb-0 stat-value"><?php echo formatPoints($current_user['points']); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-3 mb-3">
                            <div class="card bg-success text-white h-100 dashboard-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-chart-line stat-icon me-2"></i>
                                        <h5 class="card-title mb-0">Total Earned</h5>
                                    </div>
                                    <h2 class="mb-0 stat-value"><?php echo formatPoints($stats['total_earned']); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-3 mb-3">
                            <div class="card bg-info text-white h-100 dashboard-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-tasks stat-icon me-2"></i>
                                        <h5 class="card-title mb-0">Completed Offers</h5>
                                    </div>
                                    <h2 class="mb-0 stat-value"><?php echo $stats['completed_offers']; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-3 mb-3">
                            <div class="card bg-warning text-white h-100 dashboard-stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-gift stat-icon me-2"></i>
                                        <h5 class="card-title mb-0">Redeemed Rewards</h5>
                                    </div>
                                    <h2 class="mb-0 stat-value"><?php echo $stats['redeemed_rewards']; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="row mb-4 quick-actions-section">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row quick-actions">
                                        <div class="col-6 col-md-3 mb-3">
                                            <a href="/tasks" class="btn btn-primary d-flex flex-column align-items-center justify-content-center py-3 quick-action-btn">
                                                <i class="fas fa-tasks mb-2 action-icon"></i>
                                                <span>Find Tasks</span>
                                            </a>
                                        </div>
                                        <div class="col-6 col-md-3 mb-3">
                                            <a href="/tasks" class="btn btn-danger d-flex flex-column align-items-center justify-content-center py-3 quick-action-btn">
                                                <i class="fas fa-sync mb-2 action-icon"></i>
                                                <span>Earn Free Spins</span>
                                            </a>
                                        </div>
                                        <div class="col-6 col-md-3 mb-3">
                                            <a href="/rewards" class="btn btn-success d-flex flex-column align-items-center justify-content-center py-3 quick-action-btn">
                                                <i class="fas fa-gift mb-2 action-icon"></i>
                                                <span>View Rewards</span>
                                            </a>
                                        </div>
                                        <div class="col-6 col-md-3 mb-3">
                                            <a href="/profile" class="btn btn-info d-flex flex-column align-items-center justify-content-center py-3 quick-action-btn">
                                                <i class="fas fa-user mb-2 action-icon"></i>
                                                <span>Edit Profile</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dashboard Promo Slider -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Featured Promotions</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="dashboard-promo-container w-100">
                                        <!-- Dashboard Promo Slides -->
                                        <div class="dashboard-slides w-100">
                                            <!-- Slide 1 - Monopoly Go (Blue) -->
                                            <div class="dashboard-slide text-white" id="dash-slide-0" style="background-color: #0d6efd; padding: 2rem; display: flex; flex-direction: column; justify-content: flex-start; text-align: left;">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-dice me-3 fa-2x"></i>
                                                    <h4 class="mb-0">Exchange Points for Monopoly Go Spins</h4>
                                                </div>
                                                <p class="mb-3 ms-5 ps-2">Use your earned points to get free Monopoly Go spins delivered to your account instantly!</p>
                                                <div>
                                                    <a href="/rewards" class="btn btn-light ms-5 ps-2">Redeem Now</a>
                                                </div>
                                            </div>
                                            
                                            <!-- Slide 2 - Coin Master (Green) -->
                                            <div class="dashboard-slide text-white" id="dash-slide-1" style="background-color: #198754; padding: 2rem; display: flex; flex-direction: column; justify-content: flex-start; text-align: left;">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-coins me-3 fa-2x"></i>
                                                    <h4 class="mb-0">Exchange Points for Coin Master Spins</h4>
                                                </div>
                                                <p class="mb-3 ms-5 ps-2">Get more Coin Master spins by exchanging your points. Delivery within 24 hours!</p>
                                                <div>
                                                    <a href="/rewards" class="btn btn-light ms-5 ps-2">Get Spins Now</a>
                                                </div>
                                            </div>
                                            
                                            <!-- Slide 3 - Referrals (Yellow) -->
                                            <div class="dashboard-slide text-dark" id="dash-slide-2" style="background-color: #ffc107; padding: 2rem; display: flex; flex-direction: column; justify-content: flex-start; text-align: left;">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-user-friends me-3 fa-2x"></i>
                                                    <h4 class="mb-0">Refer Friends & Earn 100 Spins</h4>
                                                </div>
                                                <p class="mb-3 ms-5 ps-2">Invite your friends and earn 100 free spins for each friend who joins through your link!</p>
                                                <div>
                                                    <a href="/referrals" class="btn btn-dark ms-5 ps-2">Invite Friends</a>
                                                </div>
                                            </div>
                                            
                                            <!-- Slide 4 - Tasks (Red) -->
                                            <div class="dashboard-slide text-white" id="dash-slide-3" style="background-color: #dc3545; padding: 2rem; display: flex; flex-direction: column; justify-content: flex-start; text-align: left;">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-tasks me-3 fa-2x"></i>
                                                    <h4 class="mb-0">Complete Tasks, Earn Free Spins</h4>
                                                </div>
                                                <p class="mb-3 ms-5 ps-2">Try apps, complete offers, and earn free spins instantly. Easy and fun ways to earn!</p>
                                                <div>
                                                    <a href="<?php echo url('tasks'); ?>" class="btn btn-light ms-5 ps-2">Start Tasks</a>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Slider Controls -->
                                        <div class="dashboard-slider-controls">
                                            <span class="dashboard-slider-dot active" data-slide="0"></span>
                                            <span class="dashboard-slider-dot" data-slide="1"></span>
                                            <span class="dashboard-slider-dot" data-slide="2"></span>
                                            <span class="dashboard-slider-dot" data-slide="3"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="row">
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Leaderboard</h5>
                                    <a href="/leaderboard" class="btn btn-sm btn-outline-primary">Full Leaderboard</a>
                                </div>
                                <div class="card-body px-3">
                                    <?php if (count($top_users) > 0): ?>
                                    <div class="user-rank-highlight mb-3">
                                        <div class="alert alert-primary mb-0 d-flex justify-content-between align-items-center">
                                            <span>Your Rank</span>
                                            <span class="badge bg-primary">#<?php echo $user_rank['rank']; ?></span>
                                        </div>
                                    </div>
                                    <div class="top-users-list px-1">
                                        <?php foreach ($top_users as $index => $user): ?>
                                            <div class="leaderboard-item d-flex justify-content-between align-items-center py-2 <?php echo ($user['id'] == $current_user['id']) ? 'bg-light' : ''; ?>" style="border-bottom: 1px solid #eee;">
                                                <div class="d-flex align-items-center">
                                                    <div class="rank-number me-2 ps-1">
                                                        <?php if ($user['rank'] <= 3): ?>
                                                            <?php if ($user['rank'] == 1): ?>
                                                                <i class="fas fa-trophy text-warning"></i>
                                                            <?php elseif ($user['rank'] == 2): ?>
                                                                <i class="fas fa-medal" style="color: #C0C0C0;"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-medal" style="color: #CD7F32;"></i>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            #<?php echo $user['rank']; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="user-avatar-mini me-2 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 12px;">
                                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                    </div>
                                                    <div class="username">
                                                        <?php echo htmlspecialchars($user['username']); ?>
                                                        <?php if ($user['id'] == $current_user['id']): ?>
                                                            <span class="badge bg-secondary ms-1">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="points pe-1">
                                                    <strong><?php echo formatPoints($user['points']); ?></strong>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-center text-muted my-4">No users on leaderboard yet</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Recent Transactions</h5>
                                    <a href="/transactions" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body px-3">
                                    <?php if (count($transactions) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th class="ps-2">Type</th>
                                                    <th>Points</th>
                                                    <th class="pe-2">Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($transactions as $transaction): ?>
                                                <tr>
                                                    <td class="ps-2">
                                                        <?php if ($transaction['type'] === 'earn'): ?>
                                                            <span class="badge bg-success">Earned</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Spent</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatPoints($transaction['points']); ?></td>
                                                    <td class="pe-2"><?php echo formatDate($transaction['created_at']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-center text-muted my-4">No transactions yet</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-gift me-2"></i>Your Redemptions</h5>
                                    <a href="/redemption_history" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body px-3">
                                    <?php 
                                    // Only show user's own redemptions
                                    $recent_redemptions = array_slice($redemptions, 0, 5);
                                    if (count($recent_redemptions) > 0): 
                                    ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th class="ps-2">Reward</th>
                                                    <th>Status</th>
                                                    <th class="pe-2">Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_redemptions as $redemption): ?>
                                                <tr>
                                                    <td class="ps-2">
                                                        <?php echo htmlspecialchars($redemption['name']); ?>
                                                        <?php 
                                                        // Check if this is a game reward with redemption details
                                                        if (($redemption['reward_id'] == 6 || $redemption['reward_id'] == 7) && !empty($redemption['redemption_details'])):
                                                            $details = json_decode($redemption['redemption_details'], true);
                                                            if ($details && isset($details['username'])):
                                                        ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            Username: <strong><?php echo htmlspecialchars($details['username']); ?></strong>
                                                        </small>
                                                        <?php endif; endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($redemption['status'] === 'pending'): ?>
                                                            <span class="badge bg-warning">Pending</span>
                                                        <?php elseif ($redemption['status'] === 'completed'): ?>
                                                            <span class="badge bg-success">Completed</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Rejected</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="pe-2"><?php echo formatDate($redemption['created_at']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-center text-muted my-4">No redemptions yet</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Mobile optimization for dashboard */
    @media (max-width: 767px) {
        .dashboard-container {
            padding: 0;
            margin: 0;
            width: 100%;
        }
        
        .dashboard-content {
            padding: 0.5rem;
        }
        
        .card-header h5 {
            font-size: 1.1rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
        }
        
        .card-title {
            font-size: 0.9rem;
        }
        
        .dashboard-slide-content h4 {
            font-size: 1.2rem;
        }
        
        .dashboard-slide-content p {
            font-size: 0.9rem;
        }
        
        .leaderboard-title {
            font-size: 1.1rem;
        }
        
        .section-title {
            font-size: 1.1rem;
        }
    }
    
    /* Small desktop and desktop layout fix */
    @media (min-width: 992px) {
        .dashboard-container {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
        }
        
        .dashboard-sidebar {
            flex: 0 0 auto !important;
            width: 250px !important;
        }
        
        .dashboard-content {
            flex: 1 !important;
            max-width: calc(100% - 265px) !important;
        }
    }
</style>

<?php include 'includes/dashboard_footer.php'; ?>
