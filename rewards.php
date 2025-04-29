<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$current_user = $auth->getUser();
$user_id = $current_user['id'];

// Get available rewards
$rewards = getRewards();

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="mb-3">Redeem Your Points</h2>
                <p class="lead">You have <strong><?php echo formatPoints($current_user['points']); ?></strong> points available</p>
                <div class="progress mb-3">
                    <?php 
                    // Show progress towards next reward
                    $next_reward = null;
                    foreach ($rewards as $reward) {
                        if ($reward['points_required'] > $current_user['points']) {
                            $next_reward = $reward;
                            break;
                        }
                    }
                    
                    if ($next_reward) {
                        $progress = min(100, ($current_user['points'] / $next_reward['points_required']) * 100);
                    } else {
                        $progress = 100;
                    }
                    ?>
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $progress; ?>%" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <?php if ($next_reward): ?>
                <small class="text-muted">You need <?php echo formatPoints($next_reward['points_required'] - $current_user['points']); ?> more points to redeem <?php echo htmlspecialchars($next_reward['name']); ?></small>
                <?php else: ?>
                <small class="text-muted">You have enough points to redeem any reward!</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Available Rewards</h5>
            </div>
            <div class="card-body">
                <?php if (empty($rewards)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No rewards available at the moment. Please check back later.
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($rewards as $reward): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 reward-card">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-gift fa-3x text-primary"></i>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($reward['name']); ?></h5>
                                <p class="reward-points"><?php echo formatPoints($reward['points_required']); ?> points</p>
                                <p class="card-text"><?php echo htmlspecialchars($reward['description']); ?></p>
                                <div class="d-grid gap-2">
                                    <?php if ($current_user['points'] >= $reward['points_required']): ?>
                                    <button type="button" class="btn btn-primary view-reward" 
                                        data-id="<?php echo $reward['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($reward['name']); ?>"
                                        data-description="<?php echo htmlspecialchars($reward['description']); ?>"
                                        data-points="<?php echo $reward['points_required']; ?>">
                                        Redeem Now
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary" disabled>
                                        Not Enough Points
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reward Redemption Modal -->
<div class="modal fade" id="rewardModal" tabindex="-1" aria-labelledby="rewardModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rewardModalLabel">Reward Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="rewardDescription"></p>
                
                <div class="alert alert-info">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-info-circle fa-2x"></i>
                        </div>
                        <div>
                            <p class="mb-0">This will deduct <strong id="rewardPoints"></strong> from your account.</p>
                        </div>
                    </div>
                </div>
                
                <form id="redeemForm" method="post" action="/redeem.php">
                    <input type="hidden" name="reward_id" value="">
                    <div id="gameDetailsFields" style="display: none;">
                        <div class="mb-3">
                            <label for="gameUsername" class="form-label game-username-label">Game Username:</label>
                            <input type="text" class="form-control" id="gameUsername" name="game_username" placeholder="Enter your game username">
                            <div class="form-text">Your correct game username is required to receive spins.</div>
                        </div>
                        <div class="mb-3">
                            <label for="gameInviteLink" class="form-label game-invite-label">Invite Link (Optional):</label>
                            <input type="text" class="form-control" id="gameInviteLink" name="game_invite_link" placeholder="Paste invite link here">
                            <div class="form-text">Add your invite link to receive additional bonuses.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirmRedeem" class="form-label">Type "CONFIRM" to proceed:</label>
                        <input type="text" class="form-control" id="confirmRedeem" name="confirm" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary redeem-btn" data-user-points="<?php echo $current_user['points']; ?>" data-required-points="">
                            Confirm Redemption
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/dashboard_footer.php'; ?>
