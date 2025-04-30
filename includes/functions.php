<?php
require_once 'db.php';
require_once 'config.php';

/**
 * Detect user's country using IP address
 * 
 * @return string Country code (2-letter ISO code)
 */
function detectUserCountry() {
    // Default country if detection fails
    $default_country = 'US';
    
    // Try to get IP address
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    // Check if we have a valid IP
    if (empty($ip) || $ip == '127.0.0.1' || $ip == '::1') {
        // Use the default country instead of a hardcoded IP
        error_log("Local/invalid IP detected. Using default country: " . $default_country);
        return $default_country;
    }
    
    // Try to get country using free IP geolocation service
    try {
        $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode");
        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['status']) && $data['status'] === 'success' && !empty($data['countryCode'])) {
                error_log("Country detected from IP " . $ip . ": " . $data['countryCode']);
                return $data['countryCode'];
            }
        }
    } catch (Exception $e) {
        error_log("Error detecting country: " . $e->getMessage());
    }
    
    // Fallback to default country if detection fails
    error_log("Country detection failed, using default: " . $default_country);
    return $default_country;
}

// Admin notification functions
function createAdminNotification($title, $message, $type = 'info', $related_id = null, $related_type = null) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("INSERT INTO admin_notifications (title, message, type, related_id, related_type) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $message, $type, $related_id, $related_type]);
        
        // Send email notification if enabled
        sendAdminEmailNotification($title, $message, $type);
        
        return $conn->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating admin notification: " . $e->getMessage());
        return false;
    }
}

function getAdminNotifications($limit = 10, $unread_only = false) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $sql = "SELECT * FROM admin_notifications";
        if ($unread_only) {
            $sql .= " WHERE is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error retrieving admin notifications: " . $e->getMessage());
        return [];
    }
}

function getUnreadNotificationsCount() {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error counting unread notifications: " . $e->getMessage());
        return 0;
    }
}

function markNotificationAsRead($id) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

function markAllNotificationsAsRead() {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->query("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
        
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

function sendAdminEmailNotification($title, $message, $type) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Check if email notifications are enabled
        $stmt = $conn->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'notification_email_enabled'");
        $enabled = $stmt->fetchColumn();
        
        if ($enabled != '1') {
            return false; // Email notifications are disabled
        }
        
        // Get notification email
        $stmt = $conn->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'notification_email'");
        $email = $stmt->fetchColumn();
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false; // Invalid or empty email
        }
        
        // Get app name
        $app_name = getSetting('app_name', APP_NAME);
        
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . $app_name . " <noreply@" . $_SERVER['SERVER_NAME'] . ">" . "\r\n";
        
        // Email subject
        $subject = "[$app_name Admin] $title";
        
        // Email body
        $body = "
        <html>
        <head>
            <title>$title</title>
        </head>
        <body>
            <h2>$title</h2>
            <p>$message</p>
            <p style='margin-top: 20px;'>
                <a href='" . APP_URL . "/admin/' style='padding: 10px 15px; background-color: #4e73df; color: white; text-decoration: none; border-radius: 4px;'>
                    View Admin Dashboard
                </a>
            </p>
            <p style='margin-top: 20px; font-size: 12px; color: #666;'>
                This is an automated notification from your $app_name admin panel.
            </p>
        </body>
        </html>
        ";
        
        // Send email
        return mail($email, $subject, $body, $headers);
    } catch (Exception $e) {
        error_log("Error sending notification email: " . $e->getMessage());
        return false;
    }
}

// Additional notification helper functions
function getNotificationUrl($notification) {
    // Based on notification type and related_id, generate appropriate URL
    switch ($notification['type']) {
        case 'reward':
        case 'redemption':
            if (!empty($notification['related_id'])) {
                return "redemptions.php?id=" . $notification['related_id'];
            }
            return "redemptions.php";
            
        case 'user':
            if (!empty($notification['related_id'])) {
                return "users.php?id=" . $notification['related_id'];
            }
            return "users.php";
            
        case 'transaction':
            return "transactions.php";
            
        case 'system':
        default:
            return "index.php";
    }
}

function getNotificationIcon($type) {
    // Return Font Awesome icon class based on notification type
    switch ($type) {
        case 'reward':
        case 'redemption':
            return "fa-gift";
            
        case 'user':
            return "fa-user";
            
        case 'transaction':
            return "fa-money-bill";
            
        case 'warning':
            return "fa-exclamation-triangle";
            
        case 'error':
            return "fa-times-circle";
            
        case 'success':
            return "fa-check-circle";
            
        case 'system':
        default:
            return "fa-info-circle";
    }
}

function getNotificationIconClass($type) {
    // Return Bootstrap color class based on notification type
    switch ($type) {
        case 'reward':
        case 'redemption':
            return "warning";
            
        case 'user':
            return "primary";
            
        case 'transaction':
            return "success";
            
        case 'warning':
            return "warning";
            
        case 'error':
            return "danger";
            
        case 'success':
            return "success";
            
        case 'system':
        default:
            return "info";
    }
}

// Format date for display
function formatDate($date_string) {
    if (empty($date_string)) {
        return 'N/A';
    }
    
    $date = new DateTime($date_string);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days == 0) {
        // Today
        if ($diff->h == 0) {
            if ($diff->i == 0) {
                return "Just now";
            }
            return $diff->i . " minute" . ($diff->i > 1 ? "s" : "") . " ago";
        }
        return $diff->h . " hour" . ($diff->h > 1 ? "s" : "") . " ago";
    } else if ($diff->days == 1) {
        // Yesterday
        return "Yesterday at " . $date->format('g:i A');
    } else if ($diff->days < 7) {
        // Within a week
        return $date->format('l') . " at " . $date->format('g:i A');
    } else {
        // More than a week
        return $date->format('M j, Y') . " at " . $date->format('g:i A');
    }
}

// Function to get available offers from OGAds API
function getOffers($ip = null, $user_agent = null, $offer_type = null, $max = null, $min = null, $country = null) {
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get API settings from database
    $settings = [];
    try {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'ogads_%'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        error_log("Error retrieving API settings: " . $e->getMessage());
    }
    
    // Use settings from database or fallback to constants
    $api_key = isset($settings['ogads_api_key']) && !empty($settings['ogads_api_key']) 
        ? $settings['ogads_api_key'] 
        : OGADS_API_KEY;
    
    $api_url = OGADS_API_URL; // Always use the URL from constants
    
    // If IP is not provided, get the user's IP
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Check if the IP is a private/local IP address
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            // This is a private/local IP, just log it but use it as-is
            // The OGAds API will handle geolocation based on their own logic
            error_log("Private/local IP detected: " . $ip . " - Using actual IP for API request");
        }
    }
    
    // If no country is provided but we have a country from the user detection,
    // we should use that instead of relying only on the IP geolocation from the OGAds API
    if (!$country) {
        // Get the detected country from our function
        $detected_country = detectUserCountry();
        if ($detected_country) {
            $country = $detected_country;
            error_log("Using detected country for offer filtering: " . $country);
        }
    }
    
    // If user agent is not provided, get the user's user agent
    if (!$user_agent) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
    }
    
    // Setup API request data
    $data = [
        'ip' => $ip,                   // Client IP (REQUIRED)
        'user_agent' => $user_agent,   // Client User Agent (REQUIRED)
    ];
    
    // When the IP is from a mobile network (which often causes "Unable to find geo data for IP"),
    // explicitly set the country to 'IN' for India
    // Check if the IP is a mobile/private IP (starting with 10., 172., 192., etc.)
    if (preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/', $ip)) {
        $data['country'] = 'IN'; // Force country to India for these IPs
        error_log("Mobile/Private IP detected: $ip - Using fallback country: IN");
    } else {
        // Otherwise let the API determine country
        error_log("Using standard IP for geolocation: $ip");
    }
    
    // Use settings from database if available
    if (!$offer_type && isset($settings['ogads_ctype']) && !empty($settings['ogads_ctype'])) {
        $data['ctype'] = (int)$settings['ogads_ctype'];
    } elseif ($offer_type) {
        $data['ctype'] = $offer_type;
    }
    
    if (!$max && isset($settings['ogads_max_offers']) && !empty($settings['ogads_max_offers'])) {
        $data['max'] = (int)$settings['ogads_max_offers'];
    } elseif ($max) {
        $data['max'] = $max;
    }
    
    if (!$min && isset($settings['ogads_min_offers']) && !empty($settings['ogads_min_offers'])) {
        $data['min'] = (int)$settings['ogads_min_offers'];
    } elseif ($min) {
        $data['min'] = $min;
    }
    
    // Log the API request parameters for debugging
    error_log("OGAds API Request Parameters: " . print_r($data, true));
    
    // Set up cURL with GET method as required by OGAds API v2
    $ch = curl_init();
    
    // Create query string for GET request
    $query_params = http_build_query($data);
    $get_url = $api_url . '?' . $query_params;
    
    // Set CURL options for GET request
    curl_setopt_array($ch, [
        CURLOPT_URL            => $get_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,  // Enable SSL certificate verification
        CURLOPT_SSL_VERIFYHOST => 2,     // Verify the certificate's name against host
        CURLOPT_TIMEOUT        => 30,    // Set a reasonable timeout
    ]);
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'status' => 'error',
            'message' => 'API Request Error: ' . $error
        ];
    }
    
    curl_close($ch);
    
    // Decode JSON response
    $data = json_decode($response, true);
    
    // Check if response is valid
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid API Response: " . $response);
        return [
            'status' => 'error',
            'message' => 'Invalid API Response'
        ];
    }
    
    // Log the full API response structure for debugging
    error_log("Full API Response Structure: " . print_r($data, true));
    
    // Check if the API response indicates success
    if (isset($data['success']) && $data['success'] === true) {
        return [
            'status' => 'success',
            'offers' => $data
        ];
    } else if (isset($data['error']) && !empty($data['error'])) {
        return [
            'status' => 'error',
            'message' => 'API Error: ' . $data['error']
        ];
    } else {
        return [
            'status' => 'success',
            'offers' => $data
        ];
    }
}

// Function to get specific offer details from OGAds API
function getOfferDetails($offer_id) {
    error_log("Getting offer details for offer ID: " . $offer_id);
    
    if (empty($offer_id)) {
        error_log("Error: Empty offer ID");
        return [
            'status' => 'error',
            'message' => 'Invalid offer ID'
        ];
    }
    
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get API settings from database
    $settings = [];
    try {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'ogads_%'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        error_log("Error retrieving API settings: " . $e->getMessage());
    }
    
    // Use settings from database or fallback to constants
    $api_key = isset($settings['ogads_api_key']) && !empty($settings['ogads_api_key']) 
        ? $settings['ogads_api_key'] 
        : OGADS_API_KEY;
    
    $api_url = OGADS_API_URL; // Always use the URL from constants
    
    // Get the IP address
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // For v2 API, we need to use GET method as POST is not supported
    $data = [
        'offer_id' => $offer_id,
        'ip' => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ];
    
    // When the IP is from a mobile network (which often causes "Unable to find geo data for IP"),
    // explicitly set the country to 'IN' for India
    // Check if the IP is a mobile/private IP (starting with 10., 172., 192., etc.)
    if (preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/', $ip)) {
        $data['country'] = 'IN'; // Force country to India for these IPs
        error_log("Mobile/Private IP detected: $ip - Using fallback country: IN for offer details");
    } else {
        // Otherwise let the API determine country
        error_log("Using standard IP for geolocation in offer details: $ip");
    }
    
    // Set up cURL with GET method
    $ch = curl_init();
    
    // Create query string for GET request
    $query_params = http_build_query($data);
    $get_url = $api_url . '?' . $query_params;
    
    // Set CURL options for GET request
    curl_setopt_array($ch, [
        CURLOPT_URL            => $get_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,  // Enable SSL certificate verification
        CURLOPT_SSL_VERIFYHOST => 2,     // Verify the certificate's name against host
        CURLOPT_TIMEOUT        => 30,    // Set a reasonable timeout
    ]);
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'status' => 'error',
            'message' => 'API Request Error: ' . $error
        ];
    }
    
    curl_close($ch);
    
    // Decode JSON response
    $data = json_decode($response, true);
    
    // Check if response is valid
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid API Response: " . $response);
        return [
            'status' => 'error',
            'message' => 'Invalid API Response'
        ];
    }
    
    return [
        'status' => 'success',
        'offer' => $data
    ];
}

// Function to get user stats
function getUserStats($user_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Get total earnings
        $stmt = $conn->prepare("SELECT SUM(points) as total_earned FROM transactions WHERE user_id = :user_id AND type = 'earn'");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_earned = $result['total_earned'] ?: 0;
        
        // Get total spent
        $stmt = $conn->prepare("SELECT SUM(points) as total_spent FROM transactions WHERE user_id = :user_id AND type = 'spend'");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_spent = $result['total_spent'] ?: 0;
        
        // Get completed offers count
        $stmt = $conn->prepare("SELECT COUNT(*) as completed_offers FROM user_offers WHERE user_id = :user_id AND completed = 1");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $completed_offers = $result['completed_offers'] ?: 0;
        
        // Get redeemed rewards count
        $stmt = $conn->prepare("SELECT COUNT(*) as redeemed_rewards FROM redemptions WHERE user_id = :user_id AND status = 'completed'");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $redeemed_rewards = $result['redeemed_rewards'] ?: 0;
        
        // Get current points
        $stmt = $conn->prepare("SELECT points FROM users WHERE id = :user_id");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_points = $result['points'] ?: 0;
        
        // Get referral count
        $stmt = $conn->prepare("SELECT COUNT(*) as total_referrals FROM referrals WHERE referrer_id = :user_id");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_referrals = $result['total_referrals'] ?: 0;
        
        // Get referral income
        $stmt = $conn->prepare("SELECT SUM(points) as referral_income FROM transactions 
                               WHERE user_id = :user_id AND type = 'earn' AND description LIKE 'Referral bonus%'");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $referral_income = $result['referral_income'] ?: 0;
        
        return [
            'total_earned' => $total_earned,
            'total_spent' => $total_spent,
            'completed_offers' => $completed_offers,
            'redeemed_rewards' => $redeemed_rewards,
            'current_points' => $current_points,
            'total_referrals' => $total_referrals,
            'referral_income' => $referral_income
        ];
    } catch (PDOException $e) {
        error_log("Error getting user stats: " . $e->getMessage());
        return [
            'total_earned' => 0,
            'total_spent' => 0,
            'completed_offers' => 0,
            'redeemed_rewards' => 0,
            'current_points' => 0,
            'total_referrals' => 0,
            'referral_income' => 0
        ];
    }
}

// Function to get available rewards
function getRewards() {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $query = "SELECT * FROM rewards WHERE is_active = 1 ORDER BY points_required ASC";
        $result = $db->query($query);
        
        $rewards = [];
        if ($result) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $rewards[] = $row;
            }
        }
        
        return $rewards;
    } catch (PDOException $e) {
        error_log("Error getting rewards: " . $e->getMessage());
        return [];
    }
}

// Function to get reward by ID
function getRewardById($reward_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM rewards WHERE id = :reward_id");
        $stmt->bindValue(':reward_id', $reward_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error getting reward by ID: " . $e->getMessage());
        return null;
    }
}

// Function to redeem a reward
function redeemReward($user_id, $reward_id, $redemption_details = null) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $auth = new Auth();
    
    // Get reward details
    $reward = getRewardById($reward_id);
    
    if (!$reward) {
        return [
            'status' => 'error',
            'message' => 'Reward not found'
        ];
    }
    
    // Check if user has enough points
    $user = $auth->getUser();
    
    if ($user['points'] < $reward['points_required']) {
        return [
            'status' => 'error',
            'message' => 'Not enough points to redeem this reward'
        ];
    }
    
    try {
        // Create redemption record without transaction initially
        if ($redemption_details) {
            $stmt = $conn->prepare("INSERT INTO redemptions (user_id, reward_id, points_used, status, redemption_details) VALUES (:user_id, :reward_id, :points_used, 'pending', :redemption_details)");
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':reward_id', $reward_id);
            $stmt->bindValue(':points_used', $reward['points_required']);
            $stmt->bindValue(':redemption_details', $redemption_details);
        } else {
            $stmt = $conn->prepare("INSERT INTO redemptions (user_id, reward_id, points_used, status) VALUES (:user_id, :reward_id, :points_used, 'pending')");
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':reward_id', $reward_id);
            $stmt->bindValue(':points_used', $reward['points_required']);
        }
        $stmt->execute();
        $redemption_id = $conn->lastInsertId();
        
        // Deduct points from user
        $description = "Redeemed " . $reward['name'];
        $result = $auth->updatePoints($user_id, $reward['points_required'], 'spend', $description, $redemption_id, 'redemption');
        
        if ($result) {
            // Create admin notification for the new redemption
            $username = $user['username'];
            $reward_name = $reward['name'];
            $points = $reward['points_required'];
            
            $notification_title = "New Reward Redemption";
            $notification_message = "User {$username} has redeemed {$reward_name} for {$points} points.";
            
            // Create the notification
            createAdminNotification(
                $notification_title,
                $notification_message,
                'reward',
                $redemption_id,
                'redemption'
            );
            
            return [
                'status' => 'success',
                'message' => 'Reward redeemed successfully',
                'redemption_id' => $redemption_id
            ];
        } else {
            // If point update failed, delete the redemption record
            $deleteStmt = $conn->prepare("DELETE FROM redemptions WHERE id = :id");
            $deleteStmt->bindValue(':id', $redemption_id);
            $deleteStmt->execute();
            
            return [
                'status' => 'error',
                'message' => 'Failed to update user points'
            ];
        }
    } catch (Exception $e) {
        error_log("Error in redeemReward: " . $e->getMessage());
        
        return [
            'status' => 'error',
            'message' => 'Failed to redeem reward: ' . $e->getMessage()
        ];
    }
}

// Function to get user transactions
function getUserTransactions($user_id, $limit = 10) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Handle null or invalid limit
        if ($limit === null) {
            $sql = "SELECT * FROM transactions WHERE user_id = :user_id ORDER BY created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':user_id', $user_id);
        } else {
            $sql = "SELECT * FROM transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        $transactions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = $row;
        }
        
        return $transactions;
    } catch (PDOException $e) {
        error_log("Error getting user transactions: " . $e->getMessage());
        return [];
    }
}

// Function to get user redemptions
function getUserRedemptions($user_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT r.*, rw.name, rw.points_required 
            FROM redemptions r
            JOIN rewards rw ON r.reward_id = rw.id
            WHERE r.user_id = :user_id
            ORDER BY r.created_at DESC
        ");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->execute();
        
        $redemptions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $redemptions[] = $row;
        }
        
        return $redemptions;
    } catch (PDOException $e) {
        error_log("Error getting user redemptions: " . $e->getMessage());
        return [];
    }
}

// Function to generate a tracking URL for an offer
function generateTrackingUrl($offer_id, $user_id) {
    $base_url = APP_URL . "/tasks.php?action=track&offer_id={$offer_id}&user_id={$user_id}";
    return $base_url;
}

// Function to record user offer attempt
function recordOfferAttempt($user_id, $offer_id, $ip_address) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Debug logging
    error_log("Recording offer attempt for user: $user_id, offer: $offer_id, IP: $ip_address");
    
    try {
        // Check if the user has already attempted this offer
        $stmt = $conn->prepare("SELECT id FROM user_offers WHERE user_id = :user_id AND offer_id = :offer_id");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':offer_id', $offer_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // User has already attempted this offer
            // During testing, we'll allow multiple attempts
            error_log("User has already attempted this offer, but allowing it for testing purposes");
            
            // Update the existing record
            $stmt = $conn->prepare("UPDATE user_offers SET completed = 0, completed_at = NULL WHERE user_id = :user_id AND offer_id = :offer_id");
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':offer_id', $offer_id);
            $stmt->execute();
            
            return [
                'status' => 'success',
                'message' => 'Offer attempt updated for testing'
            ];
        }
        
        // Record the offer attempt
        $stmt = $conn->prepare("INSERT INTO user_offers (user_id, offer_id, ip_address) VALUES (:user_id, :offer_id, :ip_address)");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':offer_id', $offer_id);
        $stmt->bindValue(':ip_address', $ip_address);
        
        if ($stmt->execute()) {
            return [
                'status' => 'success',
                'message' => 'Offer attempt recorded',
                'user_offer_id' => $conn->lastInsertId()
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to record offer attempt'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error recording offer attempt: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Failed to record offer attempt: Database error'
        ];
    }
}

// Function to generate a unique postback URL for tracking offer completions
function generatePostbackUrl($user_id) {
    $token = md5(uniqid($user_id, true));
    
    // Format the postback URL according to OGAds expectations
    // We use aff_sub4 to store the user_id as per OGAds best practices
    $postback_url = APP_URL . "/postback.php?user_id={$user_id}&offer_id={offer_id}&payout={payout}&ip={session_ip}&aff_sub4={$user_id}&token={$token}";
    
    return $postback_url;
}

// Validate postback token
function validatePostbackToken($user_id, $token) {
    // In a real application, you would store and validate tokens
    // For this example, we'll just return true
    return true;
}

// Function to create or update task progress
/**
 * Cancel a task in progress
 * 
 * @param int $user_id The user ID
 * @param int $offer_id The offer ID
 * @return array Status and message
 */
function cancelTask($user_id, $offer_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Debug
        error_log("cancelTask called for user_id: $user_id, offer_id: $offer_id");
        
        // Check if the task exists and is not already completed or canceled
        $stmt = $conn->prepare("SELECT id, status FROM task_progress WHERE user_id = :user_id AND offer_id = :offer_id");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':offer_id', $offer_id);
        $stmt->execute();
        
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug
        error_log("Task found: " . json_encode($task));
        
        if (!$task) {
            error_log("Task not found in database");
            
            // Create a new "failed" status task instead (which is allowed by the constraint)
            $insertStmt = $conn->prepare("INSERT INTO task_progress 
                (user_id, offer_id, status, progress_percent, progress_message, last_updated) 
                VALUES 
                (:user_id, :offer_id, 'failed', 0, 'Task canceled by user', CURRENT_TIMESTAMP)");
            
            $insertStmt->bindValue(':user_id', $user_id);
            $insertStmt->bindValue(':offer_id', $offer_id);
            
            if ($insertStmt->execute()) {
                return ['status' => 'success', 'message' => 'Task canceled successfully'];
            } else {
                error_log("Failed to insert canceled task");
                return ['status' => 'error', 'message' => 'Failed to insert canceled task'];
            }
        }
        
        if ($task['status'] === 'completed') {
            return ['status' => 'error', 'message' => 'Cannot cancel a completed task'];
        }
        
        if ($task['status'] === 'failed') {
            return ['status' => 'error', 'message' => 'Task is already canceled'];
        }
        
        // Update the task status to failed (since 'canceled' is not allowed by the constraint)
        // We use 'failed' status but mark it as user-canceled to distinguish from naturally failed tasks
        $updateStmt = $conn->prepare("UPDATE task_progress SET 
                                    status = 'failed', 
                                    progress_percent = 0, 
                                    progress_message = 'Task canceled by user',
                                    is_user_canceled = 1,
                                    last_updated = CURRENT_TIMESTAMP 
                                    WHERE id = :id");
        
        $updateStmt->bindValue(':id', $task['id']);
        $result = $updateStmt->execute();
        
        if ($result) {
            return ['status' => 'success', 'message' => 'Task canceled successfully'];
        } else {
            error_log("Failed to update task status");
            return ['status' => 'error', 'message' => 'Failed to update task status'];
        }
    } catch (PDOException $e) {
        error_log("Error canceling task: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Resume a canceled task
 * 
 * @param int $user_id The user ID
 * @param int $offer_id The offer ID
 * @return array Status and message
 */
function resumeTask($user_id, $offer_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Debug
        error_log("resumeTask called for user_id: $user_id, offer_id: $offer_id");
        
        // Check if the task exists and has 'failed' status (our replacement for canceled)
        $stmt = $conn->prepare("SELECT id, status FROM task_progress WHERE user_id = :user_id AND offer_id = :offer_id");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':offer_id', $offer_id);
        $stmt->execute();
        
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug
        error_log("Task found: " . json_encode($task));
        
        if (!$task) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }
        
        if ($task['status'] !== 'failed') {
            return ['status' => 'error', 'message' => 'Only canceled tasks can be resumed'];
        }
        
        // Update task status to started and reset the is_user_canceled flag
        $updateStmt = $conn->prepare("UPDATE task_progress SET 
                                    status = 'started', 
                                    progress_percent = 5, 
                                    progress_message = 'Task resumed - waiting for offer interaction',
                                    is_user_canceled = 0,
                                    last_updated = CURRENT_TIMESTAMP 
                                    WHERE id = :id");
                                    
        $updateStmt->bindValue(':id', $task['id']);
        $result = $updateStmt->execute();
        
        if ($result) {
            return ['status' => 'success', 'message' => 'Task resumed successfully'];
        } else {
            error_log("Failed to update task status");
            return ['status' => 'error', 'message' => 'Failed to resume task']; 
        }
    } catch (PDOException $e) {
        error_log("Error resuming task: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function trackTaskProgress($user_id, $offer_id, $status, $progress_percent = 0, $message = '', $estimated_time = null) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Check if a record already exists for this user and offer
        $stmt = $conn->prepare("SELECT id, status FROM task_progress WHERE user_id = :user_id AND offer_id = :offer_id");
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':offer_id', $offer_id);
        $stmt->execute();
        
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record
            $sql = "UPDATE task_progress SET 
                    status = :status, 
                    progress_percent = :progress_percent, 
                    progress_message = :message,
                    estimated_completion_time = :est_time,
                    last_updated = CURRENT_TIMESTAMP";
            
            // If the status is 'completed', set the completion time
            if ($status === 'completed') {
                $sql .= ", completion_time = CURRENT_TIMESTAMP";
            }
            
            $sql .= " WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $existing['id']);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':progress_percent', $progress_percent);
            $stmt->bindValue(':message', $message);
            $stmt->bindValue(':est_time', $estimated_time);
            $stmt->execute();
            
            return $existing['id'];
        } else {
            // Create new record
            $stmt = $conn->prepare("INSERT INTO task_progress 
                (user_id, offer_id, status, progress_percent, progress_message, estimated_completion_time, is_user_canceled) 
                VALUES 
                (:user_id, :offer_id, :status, :progress_percent, :message, :est_time, 0)");
            
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':offer_id', $offer_id);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':progress_percent', $progress_percent);
            $stmt->bindValue(':message', $message);
            $stmt->bindValue(':est_time', $estimated_time);
            $stmt->execute();
            
            return $conn->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log("Error tracking task progress: " . $e->getMessage());
        return false;
    }
}

/**
 * Detect if the current device is a mobile device
 * 
 * @return array Information about the device type
 */
function detectDeviceType() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Initialize result array
    $result = [
        'is_mobile' => false,
        'is_tablet' => false,
        'is_desktop' => true,
        'device_type' => 'desktop'
    ];
    
    // Check for mobile devices
    $mobile_patterns = [
        '/Mobile/i',
        '/Android/i',
        '/iPhone/i',
        '/iPod/i',
        '/BlackBerry/i',
        '/webOS/i'
    ];
    
    // Check for tablets
    $tablet_patterns = [
        '/iPad/i',
        '/tablet/i',
        '/RIM Tablet/i',
        '/Kindle/i'
    ];
    
    // Check if device matches mobile patterns
    $is_mobile = false;
    foreach ($mobile_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            $is_mobile = true;
            break;
        }
    }
    
    // Check if device matches tablet patterns
    $is_tablet = false;
    foreach ($tablet_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            $is_tablet = true;
            break;
        }
    }
    
    // If it's a tablet, it's not considered a mobile phone
    if ($is_tablet) {
        $is_mobile = false;
    }
    
    // Update result based on detection
    if ($is_mobile) {
        $result = [
            'is_mobile' => true,
            'is_tablet' => false,
            'is_desktop' => false,
            'device_type' => 'mobile'
        ];
    } elseif ($is_tablet) {
        $result = [
            'is_mobile' => false,
            'is_tablet' => true,
            'is_desktop' => false,
            'device_type' => 'tablet'
        ];
    }
    
    return $result;
}

/**
 * Sort offers based on device type
 * 
 * @param array $offers Array of offers to sort
 * @param string $device_type Type of device (mobile, tablet, desktop)
 * @return array Sorted offers array
 */
function sortOffersByDeviceType($offers, $device_type) {
    if (empty($offers)) {
        return $offers;
    }
    
    $cpi_offers = [];
    $cpa_offers = [];
    $other_offers = [];
    
    // Categorize offers by type
    foreach ($offers as $offer) {
        $offer_type = isset($offer['ctype']) ? strtolower($offer['ctype']) : 
                    (isset($offer['type']) ? strtolower($offer['type']) : 'cpa');
        
        if ($offer_type === 'cpi') {
            $cpi_offers[] = $offer;
        } elseif ($offer_type === 'cpa') {
            $cpa_offers[] = $offer;
        } else {
            $other_offers[] = $offer;
        }
    }
    
    // Sort based on device type
    if ($device_type === 'mobile') {
        // Mobile devices: prioritize CPI offers, then CPA offers, then others
        return array_merge($cpi_offers, $cpa_offers, $other_offers);
    } else {
        // Desktop and tablets: mix CPI and CPA offers, with a slight preference for CPA
        $result = [];
        $cpi_count = count($cpi_offers);
        $cpa_count = count($cpa_offers);
        $max_count = max($cpi_count, $cpa_count);
        
        // Intersperse CPA and CPI offers
        for ($i = 0; $i < $max_count; $i++) {
            // Add CPA offer first (if available)
            if ($i < $cpa_count) {
                $result[] = $cpa_offers[$i];
            }
            
            // Add CPI offer next (if available)
            if ($i < $cpi_count) {
                $result[] = $cpi_offers[$i];
            }
        }
        
        // Add any other offers at the end
        return array_merge($result, $other_offers);
    }
}

// Function to get task progress for a user
function getTaskProgress($user_id, $offer_id = null) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $sql = "SELECT tp.*, 
                uo.completed as task_completed,
                uo.points_earned,
                CASE 
                    WHEN uo.completed = 1 THEN 100 
                    ELSE tp.progress_percent 
                END as current_progress
                FROM task_progress tp
                LEFT JOIN user_offers uo ON tp.user_id = uo.user_id AND tp.offer_id = uo.offer_id
                WHERE tp.user_id = :user_id";
        
        $params = [':user_id' => $user_id];
        
        if ($offer_id) {
            $sql .= " AND tp.offer_id = :offer_id";
            $params[':offer_id'] = $offer_id;
        }
        
        $sql .= " ORDER BY tp.last_updated DESC";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $results = $offer_id ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add task names
        if ($results) {
            // If single result
            if ($offer_id && is_array($results) && !isset($results[0])) {
                // We need to add a title for this task
                $task_id = $results['offer_id'];
                $customName = getCustomTaskName($task_id);
                $results['offer_name'] = $customName;
                $results['offer_name_short'] = $customName;
            } 
            // If multiple results
            else if (is_array($results)) {
                foreach ($results as &$task) {
                    $task_id = $task['offer_id'];
                    $customName = getCustomTaskName($task_id);
                    $task['offer_name'] = $customName;
                    $task['offer_name_short'] = $customName;
                }
            }
        }
        
        return $results;
    } catch (PDOException $e) {
        error_log("Error getting task progress: " . $e->getMessage());
        return false;
    }
}

// Helper function to get a custom task name
function getCustomTaskName($task_id) {
    global $db;
    
    // First try to get the real offer name from the database
    try {
        $conn = $db->getConnection();
        $sql = "SELECT offer_name FROM user_offers WHERE offer_id = :offer_id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':offer_id', $task_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['offer_name'])) {
            return $result['offer_name'];
        }
    } catch (PDOException $e) {
        // Continue to fallback if database lookup fails
    }
    
    // These are common task names from OGAds (fallback mapping)
    $taskNames = [
        '61182' => 'Kroger Gift Card',
        '60878' => 'MyPoints Rewards',
        '60559' => 'Tetris Party',
        '60557' => 'Evony Game Install',
        '60552' => 'Target Gift Card',
        '60444' => 'Amazon Card',
        '60442' => 'Google Play Card',
        '60088' => 'Quiz Game',
        '59817' => 'Walmart Gift Card',
        '59469' => 'Burger King Offer',
        '43399' => 'Raid Shadow Legends',
        '50521' => 'State of Survival',
        '62170' => 'NFL Shop Gift Card',
        '61822' => 'DisneyPlus Subscription',
        '58462' => 'Rise of Kingdoms'
    ];
    
    // Return custom name if available, otherwise return a better default
    return $taskNames[$task_id] ?? 'Offer #' . $task_id;
}

// Function to complete user offer
function completeUserOffer($user_id, $offer_id, $payout) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $auth = new Auth();
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Calculate points based on payout
        $points = (int)($payout * POINTS_CONVERSION_RATE);
        
        // Update user offer record
        $stmt = $conn->prepare("
            UPDATE user_offers 
            SET completed = 1, points_earned = :points, completed_at = datetime('now') 
            WHERE user_id = :user_id AND offer_id = :offer_id
        ");
        $stmt->bindValue(':points', $points);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':offer_id', $offer_id);
        $stmt->execute();
        
        // Check if any rows were affected (SQLite doesn't have affected_rows, we need to query)
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM user_offers WHERE user_id = :user_id AND offer_id = :offer_id");
        $checkStmt->bindValue(':user_id', $user_id);
        $checkStmt->bindValue(':offer_id', $offer_id);
        $checkStmt->execute();
        $count = (int)$checkStmt->fetchColumn();
        
        if ($count === 0) {
            // Create a record if it doesn't exist
            $stmt = $conn->prepare("
                INSERT INTO user_offers (user_id, offer_id, completed, points_earned, completed_at) 
                VALUES (:user_id, :offer_id, 1, :points, datetime('now'))
            ");
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':offer_id', $offer_id);
            $stmt->bindValue(':points', $points);
            $stmt->execute();
            $user_offer_id = $conn->lastInsertId();
        } else {
            // Get the id of the updated record
            $idStmt = $conn->prepare("SELECT id FROM user_offers WHERE user_id = :user_id AND offer_id = :offer_id");
            $idStmt->bindValue(':user_id', $user_id);
            $idStmt->bindValue(':offer_id', $offer_id);
            $idStmt->execute();
            $user_offer_id = $idStmt->fetchColumn();
        }
        
        // Add points to user
        $description = "Completed offer: " . $offer_id;
        $pointsResult = $auth->updatePoints($user_id, $points, 'earn', $description, $user_offer_id, 'offer');
        
        if ($pointsResult['status'] !== 'success') {
            error_log("Failed to add points to user: " . $pointsResult['message']);
            throw new Exception("Failed to add points to user: " . $pointsResult['message']);
        }
        
        // Commit transaction
        $conn->commit();
        
        return [
            'status' => 'success',
            'message' => 'Offer completed successfully',
            'points_earned' => $points
        ];
    } catch (Exception $e) {
        // Roll back transaction on error
        $conn->rollBack();
        error_log("Error completing offer: " . $e->getMessage());
        
        return [
            'status' => 'error',
            'message' => 'Failed to complete offer: ' . $e->getMessage()
        ];
    }
}

// Admin Functions

// Get all users
function getAllUsers() {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $query = "SELECT * FROM users ORDER BY id DESC";
        $result = $db->query($query);
        
        $users = [];
        if ($result) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $users[] = $row;
            }
        }
        
        return $users;
    } catch (PDOException $e) {
        error_log("Error getting all users: " . $e->getMessage());
        return [];
    }
}

// Get username by user ID
function getUsernameById($user_id) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = :user_id");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['username'] : 'Unknown User';
    } catch (PDOException $e) {
        error_log("Error getting username by ID: " . $e->getMessage());
        return 'Unknown User';
    }
}

// Get all rewards
function getAllRewards() {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $query = "SELECT * FROM rewards ORDER BY points_required ASC";
        $result = $db->query($query);
        
        $rewards = [];
        if ($result) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $rewards[] = $row;
            }
        }
        
        return $rewards;
    } catch (PDOException $e) {
        error_log("Error getting all rewards: " . $e->getMessage());
        return [];
    }
}

// Add a new reward
function addReward($name, $description, $points_required, $is_active = 1) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->prepare("INSERT INTO rewards (name, description, points_required, is_active) VALUES (:name, :description, :points_required, :is_active)");
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':points_required', $points_required);
        $stmt->bindValue(':is_active', $is_active);
        
        if ($stmt->execute()) {
            return [
                'status' => 'success',
                'message' => 'Reward added successfully',
                'reward_id' => $conn->lastInsertId()
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to add reward'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error adding reward: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Failed to add reward: Database error'
        ];
    }
}

// Update a reward
function updateReward($reward_id, $name, $description, $points_required, $is_active) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->prepare("UPDATE rewards SET name = :name, description = :description, points_required = :points_required, is_active = :is_active WHERE id = :reward_id");
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':points_required', $points_required);
        $stmt->bindValue(':is_active', $is_active);
        $stmt->bindValue(':reward_id', $reward_id);
        
        if ($stmt->execute()) {
            return [
                'status' => 'success',
                'message' => 'Reward updated successfully'
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to update reward'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error updating reward: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Failed to update reward: Database error'
        ];
    }
}

// Delete a reward
function deleteReward($reward_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Check if any redemptions exist for this reward
        $stmt = $conn->prepare("SELECT COUNT(*) FROM redemptions WHERE reward_id = :reward_id");
        $stmt->bindValue(':reward_id', $reward_id);
        $stmt->execute();
        $count = (int)$stmt->fetchColumn();
        
        if ($count > 0) {
            return [
                'status' => 'error',
                'message' => 'Cannot delete reward: It has been redeemed by users'
            ];
        }
        
        $stmt = $conn->prepare("DELETE FROM rewards WHERE id = :reward_id");
        $stmt->bindValue(':reward_id', $reward_id);
        
        if ($stmt->execute()) {
            return [
                'status' => 'success',
                'message' => 'Reward deleted successfully'
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to delete reward'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error deleting reward: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Failed to delete reward: Database error'
        ];
    }
}

// Get all redemptions
function getAllRedemptions() {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $query = "
            SELECT r.*, u.username, rw.name as reward_name 
            FROM redemptions r
            JOIN users u ON r.user_id = u.id
            JOIN rewards rw ON r.reward_id = rw.id
            ORDER BY r.created_at DESC
        ";
        $result = $conn->query($query);
        
        $redemptions = [];
        if ($result) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $redemptions[] = $row;
            }
        }
        
        return $redemptions;
    } catch (PDOException $e) {
        error_log("Error getting all redemptions: " . $e->getMessage());
        return [];
    }
}

// Update redemption status
function updateRedemptionStatus($redemption_id, $status) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // First get redemption details to create accurate notification
        $stmt = $conn->prepare("
            SELECT r.*, rw.name AS reward_name, u.username, u.email
            FROM redemptions r
            JOIN rewards rw ON r.reward_id = rw.id
            JOIN users u ON r.user_id = u.id
            WHERE r.id = :redemption_id
        ");
        $stmt->bindValue(':redemption_id', $redemption_id);
        $stmt->execute();
        $redemption = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$redemption) {
            return [
                'status' => 'error',
                'message' => 'Redemption not found'
            ];
        }
        
        // Update the redemption status
        $stmt = $conn->prepare("UPDATE redemptions SET status = :status WHERE id = :redemption_id");
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':redemption_id', $redemption_id);
        
        if ($stmt->execute()) {
            // Create admin notification about the status change
            $notification_title = "Redemption Status Updated";
            $notification_message = "Redemption #{$redemption_id} for {$redemption['reward_name']} by {$redemption['username']} has been {$status}.";
            
            createAdminNotification(
                $notification_title,
                $notification_message,
                'redemption',
                $redemption_id,
                'redemption_status'
            );
            
            // If there's user email and the app is configured to send user emails,
            // we could send an email to the user here about their redemption status
            
            return [
                'status' => 'success',
                'message' => 'Redemption status updated successfully'
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to update redemption status'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error updating redemption status: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Failed to update redemption status: Database error'
        ];
    }
}

// Get all transactions
function getAllTransactions() {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $query = "
            SELECT t.*, u.username 
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            ORDER BY t.created_at DESC
            LIMIT 1000
        ";
        $result = $conn->query($query);
        
        $transactions = [];
        if ($result) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $transactions[] = $row;
            }
        }
        
        return $transactions;
    } catch (PDOException $e) {
        error_log("Error getting all transactions: " . $e->getMessage());
        return [];
    }
}

// Format points as currency
function formatPoints($points) {
    // Ensure we have a valid number before formatting
    if ($points === null || $points === '') {
        return '0';
    }
    
    // Convert to integer to ensure clean formatting
    $pointsValue = intval($points);
    return number_format($pointsValue);
}

// Function to get a setting value
function getSetting($key, $default = null) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = :key");
        $stmt->bindValue(':key', $key);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $value = $result['setting_value'];
            
            // For image settings, ensure they have absolute paths
            if (in_array($key, ['app_logo', 'auth_card_logo', 'favicon']) && !empty($value)) {
                // If the path doesn't start with http or / make it absolute
                if (strpos($value, 'http') !== 0 && strpos($value, '/') !== 0) {
                    $value = '/' . $value;
                }
            }
            
            return $value;
        } else {
            return $default;
        }
    } catch (PDOException $e) {
        error_log("Error getting setting: " . $e->getMessage());
        return $default;
    }
}

// Function to save a setting
function saveSetting($key, $value) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Check if the setting already exists
        $stmt = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = :key");
        $stmt->bindValue(':key', $key);
        $stmt->execute();
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Update existing setting
            $stmt = $conn->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
        } else {
            // Insert new setting
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)");
        }
        
        $stmt->bindValue(':key', $key);
        $stmt->bindValue(':value', $value);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log("Error saving setting: " . $e->getMessage());
        return false;
    }
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

/**
 * Generate URL with proper format (with or without .php extension)
 * @param string $page The page name (with or without .php)
 * @param array $params Optional query parameters
 * @param bool $absolute Whether to return an absolute URL
 * @return string The formatted URL
 */
function url($page, $params = [], $absolute = false) {
    // Remove .php extension if present
    $page = preg_replace('/\.php$/', '', $page);
    
    // Build the query string
    $query = !empty($params) ? '?' . http_build_query($params) : '';
    
    // Get the base path of the application (might be in a subdirectory)
    $base_path = '';
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $original_base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        
        // Fix: Remove "/dashboard" if it's in the path when it shouldn't be
        // This addresses the issue where $base_path incorrectly adds /dashboard to URLs
        $base_path = preg_replace('|/dashboard$|', '', $original_base_path);
        
        // If we're in the root directory, $base_path will be empty or '/'
        if ($base_path === '/') {
            $base_path = '';
        }
    }
    
    // For absolute URLs (if needed)
    if ($absolute) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host . $base_path . '/' . $page . $query;
    }
    
    // For relative URLs - ensure no double slashes
    return $base_path . '/' . $page . $query;
}

?>
