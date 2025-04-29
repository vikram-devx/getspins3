    <!-- Mobile Bottom Navigation for Dashboard -->
    <div class="mobile-bottom-nav">
        <div class="nav-items">
            <a href="/dashboard" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="/tasks" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i>
                <span>Tasks</span>
            </a>
            <a href="/rewards" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'rewards.php' || basename($_SERVER['PHP_SELF']) == 'redeem.php') ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i>
                <span>Rewards</span>
            </a>
            <a href="/referrals" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'referrals.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span>Refer</span>
            </a>
            <a href="/leaderboard" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-trophy"></i>
                <span>Ranks</span>
            </a>
        </div>
    </div>

    <!-- Required scripts for logged-in pages -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/emailjs-com@3/dist/email.min.js"></script>
    <script src="/assets/js/emailjs-config.js"></script>
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/promo-slider.js"></script>
</body>
</html>