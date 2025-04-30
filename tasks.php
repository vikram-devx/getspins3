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

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Handle task actions
$message = '';
$message_type = '';

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Start a task
    if ($action === 'start' && isset($_GET['offer_id'])) {
        $offer_id = $_GET['offer_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // For debugging
        error_log("Starting task for offer ID: " . $offer_id);
        
        // Record the offer attempt
        $result = recordOfferAttempt($user_id, $offer_id, $ip_address);
        
        if ($result['status'] === 'success') {
            // Initialize task progress tracking
            trackTaskProgress(
                $user_id, 
                $offer_id, 
                'started', 
                5, 
                'Task started - waiting for offer interaction', 
                300 // 5 minutes estimated completion time
            );
            
            // For sample offers (1, 2, 3) use our built-in sample system
            if (in_array($offer_id, ['offer1', 'offer2', 'offer3'])) {
                // When using sample offers, just show a message and direct to dashboard
                $message = 'Task initiated! This is a sample task. In a real environment, you would be redirected to the actual offer. Points will be automatically credited after a moment.';
                $message_type = 'success';
                
                // Auto-credit points for sample offers after a short delay
                // In a real system, this would be handled by the postback URL
                $payout = 0;
                $offer_type = 'cpa'; // Default offer type
                
                switch ($offer_id) {
                    case 'offer1':
                        $payout = 1.5;
                        $offer_type = 'cpi'; // Set correct offer type for offer1
                        break;
                    case 'offer2':
                        $payout = 2.0;
                        $offer_type = 'cpa';
                        break;
                    case 'offer3':
                        $payout = 3.5;
                        $offer_type = 'cpa';
                        break;
                }
                
                if ($payout > 0) {
                    // Complete the offer and credit points
                    $points = (int)($payout * POINTS_CONVERSION_RATE);
                    $description = "Completed sample offer #{$offer_id}";
                    $result = $auth->updatePoints($user_id, $points, 'earn', $description, $offer_id, 'offer');
                    
                    if ($result['status'] === 'success') {
                        // Mark offer as completed
                        $stmt = $conn->prepare("UPDATE user_offers SET completed = 1, points_earned = :points, completed_at = datetime('now'), offer_type = :offer_type, payout = :payout WHERE user_id = :user_id AND offer_id = :offer_id");
                        $stmt->bindValue(':points', $points);
                        $stmt->bindValue(':user_id', $user_id);
                        $stmt->bindValue(':offer_id', $offer_id);
                        $stmt->bindValue(':offer_type', $offer_type);
                        $stmt->bindValue(':payout', $payout);
                        $stmt->execute();
                    } else {
                        error_log("Failed to update points: " . $result['message']);
                        $message = "There was an error processing your task: " . $result['message'];
                        $message_type = 'danger';
                    }
                }
            } else {
                // For real API offers
                $offer_link = '';
                
                // First check if the offer link was passed directly via the form
                if (isset($_GET['offer_link']) && !empty($_GET['offer_link'])) {
                    $offer_link = $_GET['offer_link']; 
                    error_log("Using offer link from form: " . $offer_link);
                } else if (isset($_POST['offer_link']) && !empty($_POST['offer_link'])) {
                    $offer_link = $_POST['offer_link'];
                    error_log("Using offer link from POST form: " . $offer_link);
                } else {
                    // Try to find the offer in our current offers array
                    foreach ($offers as $offer) {
                        if (isset($offer['offerid']) && $offer['offerid'] == $offer_id && isset($offer['link'])) {
                            $offer_link = $offer['link'];
                            error_log("Found offer link in offers array: " . $offer_link);
                            break;
                        }
                    }
                    
                    // If we couldn't find the offer in our cache, try to get it from the API
                    if (empty($offer_link)) {
                        $offer_details = getOfferDetails($offer_id);
                        
                        // Check for 'link' field which is the correct field name in OGAds API
                        if ($offer_details['status'] === 'success' && isset($offer_details['offer']) && 
                            (isset($offer_details['offer']['link']) || isset($offer_details['offer']['tracking_url']))) {
                            
                            // Use whichever field is available
                            if (isset($offer_details['offer']['link'])) {
                                $offer_link = $offer_details['offer']['link'];
                                error_log("Got offer link from API (link field): " . $offer_link);
                            } else {
                                $offer_link = $offer_details['offer']['tracking_url'];
                                error_log("Got offer link from API (tracking_url field): " . $offer_link);
                            }
                        }
                    }
                }
                
                if (!empty($offer_link)) {
                    // Append our postback parameters to the tracking URL
                    $tracking_url = $offer_link;
                    
                    // Add affiliate sub parameter for tracking which user completed the offer
                    // This will be passed back via the postback URL from OGAds
                    // We use aff_sub4 as recommended in OGAds documentation
                    if (strpos($tracking_url, 'aff_sub4=') === false) {
                        $tracking_url .= (strpos($tracking_url, '?') !== false ? '&' : '?') . 'aff_sub4=' . $user_id;
                    }
                    
                    // Also include as aff_sub in case the API uses that parameter instead
                    if (strpos($tracking_url, 'aff_sub=') === false) {
                        $tracking_url .= '&aff_sub=' . $user_id;
                    }
                    
                    error_log("Redirecting to tracking URL: " . $tracking_url);
                    
                    // Only redirect if we're not handling an AJAX request or hidden form submission
                    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && empty($_POST['hidden_form_submission']) && empty($_GET['hidden_form_submission'])) {
                        // Redirect the user to the offer URL
                        header('Location: ' . $tracking_url);
                        exit;
                    } else {
                        // Just return success for hidden form submissions
                        echo json_encode(['success' => true, 'message' => 'Offer tracking recorded']);
                        exit;
                    }
                } else {
                    // Fallback if we couldn't get the tracking URL
                    $message = 'Could not start task. Please try again later.';
                    $message_type = 'warning';
                    error_log("No offer link found for offer ID: " . $offer_id);
                }
            }
        } else {
            $message = $result['message'];
            $message_type = 'danger';
        }
    }
    
    // Track task completion (this would normally be handled by the postback URL)
    if ($action === 'track' && isset($_GET['offer_id']) && isset($_GET['user_id'])) {
        // This is a simplified tracking mechanism
        // In a real implementation, this would redirect to the actual offer URL
        // and the completion would be tracked via the postback URL
        $message = 'You are being redirected to complete the offer...';
        $message_type = 'info';
    }
}

// Get database connection to check for API key
$db = Database::getInstance();
$conn = $db->getConnection();

// Get API settings
$settings = [];
try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'ogads_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error retrieving API settings: " . $e->getMessage());
}

// Check if API key is configured
$api_key = isset($settings['ogads_api_key']) && !empty($settings['ogads_api_key']) 
    ? $settings['ogads_api_key'] 
    : OGADS_API_KEY;

// Get additional settings
$max_offers = isset($settings['ogads_max_offers']) ? (int)$settings['ogads_max_offers'] : 10;
$min_offers = isset($settings['ogads_min_offers']) ? (int)$settings['ogads_min_offers'] : 3;
$ctype = isset($settings['ogads_ctype']) ? (int)$settings['ogads_ctype'] : null;

// Detect the user's device type
$device_info = detectDeviceType();
$device_type = $device_info['device_type'];

// Log device type detection for debugging
error_log("Device detected as: " . $device_type);

// For mobile devices, prioritize CPI offers (if the setting doesn't force a specific type)
if ($device_info['is_mobile'] && !isset($settings['ogads_ctype'])) {
    $ctype = 'cpi'; // Force CPI offers for mobile devices
    error_log("Mobile device detected - prioritizing CPI offers");
}

// Detect user's country for country-specific offers
$user_country = detectUserCountry();
error_log("Detected user country: " . $user_country);

// Get available offers from the API, filtered by user's country
$offers_result = getOffers(null, null, $ctype, $max_offers, $min_offers, $user_country);

// For debugging purposes, log the API response
error_log("OGAds API Response: " . print_r($offers_result, true));

// Prepare offers for display
$offers = [];

// Check if we got a valid response
if ($offers_result['status'] === 'success') {
    // API returned a response (might have offers or an error message)
    // Based on the documentation, we need to check for:
    // { "success": true, "error": null, "offers": [ ... array of offers ... ] }
    
    // Debug log the full response structure
    error_log("Full API Response Structure in tasks.php: " . print_r($offers_result, true));
    
    if (isset($offers_result['offers']['error']) && !empty($offers_result['offers']['error'])) {
        // API returned an error
        $error_message = $offers_result['offers']['error'];
        $message = "No offers available: " . $error_message;
        $message_type = "info";
        
        // Don't use sample offers for production
        $use_sample_offers = false;
    } elseif (isset($offers_result['offers']['success']) && $offers_result['offers']['success'] === true && 
              isset($offers_result['offers']['offers']) && is_array($offers_result['offers']['offers']) && 
              !empty($offers_result['offers']['offers'])) {
        // API returned valid offers as per documentation { "success": true, "error": null, "offers": [ ... array of offers ... ] }
        $offers = $offers_result['offers']['offers'];
        $use_sample_offers = false;
        
        // For debugging, log the first offer to see its structure
        if (isset($offers[0])) {
            error_log("First OGAds Offer Structure: " . print_r($offers[0], true));
        }
    } else {
        // Check if we have a direct array of offers (without the success/error wrapper)
        if (isset($offers_result['offers']) && is_array($offers_result['offers']) && 
            !isset($offers_result['offers']['success']) && !isset($offers_result['offers']['error'])) {
            
            $offers = $offers_result['offers'];
            $use_sample_offers = false;
            
            // For debugging, log the first offer
            if (isset($offers[0])) {
                error_log("First OGAds Direct Offer Structure: " . print_r($offers[0], true));
            }
        } else {
            // API returned success but no offers (empty array or null)
            $message = "No offers available for your region at this time.";
            $message_type = "info";
            $use_sample_offers = false;
        }
    }
} else {
    // API request failed completely
    $error_message = isset($offers_result['message']) ? $offers_result['message'] : "Connection issue";
    
    if (empty($api_key)) {
        $message = "API Key not configured. Please set up your OGAds API Key in the admin panel.";
    } else {
        $message = "API Error: " . $error_message;
    }
    $message_type = "info";
    
    // Don't use sample offers for production
    $use_sample_offers = false;
    
    // Store debug info for display
    $debug_info = "Detected Country: " . ($user_country ?: 'Unknown') . " | IP: " . $_SERVER['REMOTE_ADDR'] . " | Device: " . $device_type;
}

// Initialize $offers as an empty array if it's not set
if (!isset($offers)) {
    $offers = [];
}

// If we need to use sample offers, set them up
if ($use_sample_offers) {
    
    // Provide sample offers for testing
    $sample_offers = [
        [
            'id' => 'offer1',
            'offerid' => 'offer1', // API compatibility
            'name' => 'Final Fantasy XV',
            'name_short' => 'Final Fantasy XV',
            'description' => 'Be the hero of your own Final Fantasy XV adventure in the brand new mobile strategy game Final Fantasy XV: A New Empire! Build your own kingdom, discover powerful magic, and dominate the realm alongside all of your friends!',
            'adcopy' => 'Download, install and complete the tutorial to unlock this content.',
            'requirements' => 'Download, install, and complete the tutorial of the app.',
            'payout' => 2.5,
            'type' => 'cpi',
            'ctype' => 'cpi', // API compatibility
            'picture' => 'https://media.go2speed.org/brand/files/ogmobi/9164/thumbnails_100/Final.Fantasy.Animated.gif',
            'device' => 'Android,iOS',
            'country' => 'US'
        ],
        [
            'id' => 'offer2',
            'offerid' => 'offer2', // API compatibility
            'name' => '$100 Amazon Gift Card',
            'name_short' => '$100 Amazon Gift Card',
            'description' => 'Enter your details to have a chance to WIN a $100 Amazon Gift Card now!',
            'adcopy' => 'Complete this short survey for a chance to win a Gift Card.',
            'requirements' => 'Complete the entire survey honestly.',
            'payout' => 2.0,
            'type' => 'cpa',
            'ctype' => 'cpa', // API compatibility
            'picture' => 'https://cdn.unlockcontent.net/img/offer/62613',
            'device' => 'Desktop',
            'country' => 'US'
        ],
        [
            'id' => 'offer3',
            'offerid' => 'offer3', // API compatibility
            'name' => 'Castle Clash',
            'name_short' => 'Castle Clash',
            'description' => 'Build and battle your way to glory in Castle Clash! With over 100 million clashers worldwide, the heat is on in the most addictive game ever!',
            'adcopy' => 'Download and install this app then run it for 30 seconds to unlock this content.',
            'requirements' => 'Install the app and play for at least 5 minutes.',
            'payout' => 3.0,
            'type' => 'cpi',
            'ctype' => 'cpi', // API compatibility
            'picture' => 'https://media.go2speed.org/brand/files/ogmobi/2993/thumbnails_100/20171005125303-castleclashbravesquadsnew.png',
            'device' => 'Android',
            'country' => 'US'
        ]
    ];
    
    // Rearrange sample offers based on device type
    // Get completed offers to filter them out
    $completed_offers = [];
    try {
        $stmt = $conn->prepare("SELECT offer_id FROM user_offers WHERE user_id = :user_id AND completed = 1");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($result) {
            foreach ($result as $row) {
                $completed_offers[] = $row['offer_id'];
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching completed offers: " . $e->getMessage());
    }
    
    // Filter out completed offers from sample offers
    $filtered_offers = [];
    foreach ($sample_offers as $offer) {
        $offer_id = isset($offer['offerid']) ? $offer['offerid'] : '';
        
        // Skip if this offer has been completed
        if (in_array($offer_id, $completed_offers)) {
            continue;
        }
        
        $filtered_offers[] = $offer;
    }
    
    // Replace with filtered sample offers
    $sample_offers = $filtered_offers;
    
    if ($device_info['is_mobile']) {
        // For mobile, put CPI offers first
        $offers = sortOffersByDeviceType($sample_offers, 'mobile');
        error_log("Using sample offers, sorted for mobile device");
    } else {
        // For desktop/tablet, mix CPA and CPI
        $offers = sortOffersByDeviceType($sample_offers, $device_type);
        error_log("Using sample offers, sorted for " . $device_type);
    }
} else {
    // Filter out completed offers if we have real offers
    if (!empty($offers)) {
        // Get completed offers to filter them out
        $completed_offers = [];
        try {
            $stmt = $conn->prepare("SELECT offer_id FROM user_offers WHERE user_id = :user_id AND completed = 1");
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($result) {
                foreach ($result as $row) {
                    $completed_offers[] = $row['offer_id'];
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching completed offers: " . $e->getMessage());
        }
        
        // Filter out completed offers
        $filtered_offers = [];
        foreach ($offers as $offer) {
            $offer_id = isset($offer['offerid']) ? $offer['offerid'] : '';
            
            // Skip if this offer has been completed
            if (in_array($offer_id, $completed_offers)) {
                continue;
            }
            
            $filtered_offers[] = $offer;
        }
        
        // Replace offers with filtered list
        $offers = $filtered_offers;
        
        // Sort offers by device type
        $offers = sortOffersByDeviceType($offers, $device_type);
        error_log("Filtered and sorted " . count($offers) . " offers for " . $device_type);
    }
}

// Postback URL generation removed as per requirements

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Task Progress Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Your Active Tasks</h5>
            </div>
            <div class="card-body">
                <div id="task-progress-container">
                    <p class="text-center text-muted" id="no-tasks-message">You don't have any active tasks.</p>
                    <!-- Task progress items will be added here dynamically -->
                </div>
            </div>
        </div>
        
        <!-- Recently Completed Tasks Section -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Recently Completed Tasks</h5>
            </div>
            <div class="card-body">
                <div id="completed-tasks-container">
                    <p class="text-center text-muted" id="no-completed-tasks-message">You haven't completed any tasks recently.</p>
                    <!-- Recently completed task items will be added here dynamically -->
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Available Tasks</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                
                <?php if (strpos($message, 'API Error') !== false): ?>
                <!-- Always show debug info when API error occurs, to help users provide information for support -->
                <div class="alert alert-info mb-3">
                    <strong>Debug Info:</strong> Detected Country: <?php echo htmlspecialchars($user_country); ?> | 
                    IP: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?> | 
                    Device: <?php echo htmlspecialchars($device_type); ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php 
                // Debug information - Show only when debugging is enabled (even if no error)
                $show_debug_info = defined('SHOW_DEBUG_INFO') ? SHOW_DEBUG_INFO : false;
                if ($show_debug_info && !isset($debug_info_displayed)): 
                ?>
                <div class="alert alert-info mb-3">
                    <strong>Debug Info:</strong> Detected Country: <?php echo htmlspecialchars($user_country); ?> | 
                    IP: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?> | 
                    Device: <?php echo htmlspecialchars($device_type); ?>
                </div>
                <?php $debug_info_displayed = true; endif; ?>
                
                <?php if (empty($offers)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No tasks available at the moment. Please check back later.
                </div>
                <?php else: ?>
                
                <div class="row">
                    <?php 
                    // Process and display the offers from OGAds API
                    foreach ($offers as $offer):
                        // Extract relevant information from the offer based on OGAds API structure
                        $offer_id = isset($offer['offerid']) ? $offer['offerid'] : '';
                        // Prioritize short name as requested
                        $offer_name = isset($offer['name_short']) ? $offer['name_short'] : '';
                        if (empty($offer_name) && isset($offer['name'])) {
                            $offer_name = $offer['name'];
                        }
                        // Use description if available
                        $offer_description = isset($offer['description']) ? $offer['description'] : '';
                        
                        // For CPI offers, check if there's an instructions field first, otherwise use adcopy
                        $offer_type = isset($offer['ctype']) ? strtolower($offer['ctype']) : 'cpa';
                        if ($offer_type === 'cpi' && isset($offer['instructions'])) {
                            $offer_adcopy = $offer['instructions']; // Use instructions for CPI offers if available
                        } else {
                            $offer_adcopy = isset($offer['adcopy']) ? $offer['adcopy'] : '';
                        }
                        
                        // If description is empty, use adcopy as the description but clear adcopy to avoid duplication
                        if (empty($offer_description) && !empty($offer_adcopy)) {
                            $offer_description = $offer_adcopy;
                            $offer_adcopy = '';
                        }
                        
                        // If description and adcopy are the same, clear adcopy to avoid duplication
                        if ($offer_description === $offer_adcopy) {
                            $offer_adcopy = '';
                        }
                        $offer_requirements = 'Complete all offer requirements to earn points.';
                        $offer_payout = isset($offer['payout']) ? (float)$offer['payout'] : 0;
                        
                        // Determine offer type (defaulting to CPA if not specified)
                        $offer_type = isset($offer['ctype']) ? strtolower($offer['ctype']) : 'cpa';
                        
                        // Calculate points based on payout
                        $points = (int)($offer_payout * POINTS_CONVERSION_RATE);
                    ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 task-card">
                            <div class="card-body">
                                <div class="d-flex">
                                    <?php 
                                    // Get offer image if available
                                    $offer_image = isset($offer['picture']) ? $offer['picture'] : '';
                                    ?>
                                    <div class="task-icon me-3">
                                        <?php if (!empty($offer_image)): ?>
                                            <img src="<?php echo htmlspecialchars($offer_image); ?>" class="icon-image" alt="<?php echo htmlspecialchars($offer_name); ?>">
                                        <?php else: ?>
                                            <div class="icon-placeholder <?php echo strtolower($offer_type) === 'cpa' ? 'icon-placeholder-cpa' : ''; ?>">
                                                <?php if (strtolower($offer_type) === 'cpi'): ?>
                                                    <i class="fas fa-mobile-alt fa-2x"></i>
                                                <?php elseif (strtolower($offer_type) === 'cpa'): ?>
                                                    <i class="fas fa-poll fa-2x"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-tasks fa-2x"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="task-content">
                                        <h5 class="card-title"><?php echo !empty($offer_name) ? htmlspecialchars($offer_name) : 'No title'; ?></h5>
                                        
                                        <div class="task-type-badges mb-2">
                                            <span class="offer-type offer-type-<?php echo $offer_type; ?>">
                                                <?php if (strtolower($offer_type) === 'cpi'): ?>
                                                    <i class="fas fa-mobile-alt me-1"></i> APP INSTALL
                                                <?php elseif (strtolower($offer_type) === 'cpa'): ?>
                                                    <i class="fas fa-poll me-1"></i> SURVEY OFFER
                                                <?php else: ?>
                                                    <i class="fas fa-tasks me-1"></i> <?php echo strtoupper($offer_type); ?>
                                                <?php endif; ?>
                                            </span>
                                            
                                            <?php
                                            // Display device badges if available and app install type
                                            if (strtolower($offer_type) === 'cpi' && isset($offer['device'])):
                                                $devices = explode(',', $offer['device']);
                                                foreach ($devices as $device):
                                                    $device = trim($device);
                                                    if (stripos($device, 'android') !== false):
                                            ?>
                                                <span class="device-badge device-android">
                                                    <i class="fab fa-android me-1"></i> Android
                                                </span>
                                            <?php 
                                                    elseif (stripos($device, 'ios') !== false || stripos($device, 'iphone') !== false || stripos($device, 'ipad') !== false):
                                            ?>
                                                <span class="device-badge device-ios">
                                                    <i class="fab fa-apple me-1"></i> iOS
                                                </span>
                                            <?php
                                                    endif;
                                                endforeach;
                                            endif;
                                            ?>
                                        </div>
                                        
                                        <p class="card-text task-description"><?php echo !empty($offer_description) ? htmlspecialchars($offer_description) : 'No description'; ?></p>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="task-payout">
                                        <span class="badge bg-success points-badge">
                                            <i class="fas fa-coins me-1"></i> <?php echo formatPoints($points); ?> points
                                        </span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary view-task" 
                                        data-id="<?php echo $offer_id; ?>"
                                        data-title="<?php echo !empty($offer_name) ? htmlspecialchars($offer_name) : 'No title'; ?>"
                                        data-description="<?php echo !empty($offer_description) ? htmlspecialchars($offer_description) : 'No description'; ?>"
                                        data-requirements="<?php echo htmlspecialchars($offer_requirements); ?>"
                                        data-adcopy="<?php echo !empty($offer_adcopy) ? htmlspecialchars($offer_adcopy) : ''; ?>"
                                        data-payout="$<?php echo number_format($offer_payout, 2); ?>"
                                        data-points="<?php echo formatPoints($points); ?>"
                                        data-link="<?php echo isset($offer['link']) ? htmlspecialchars($offer['link']) : ''; ?>"
                                        data-image="<?php echo !empty($offer_image) ? htmlspecialchars($offer_image) : ''; ?>"
                                        data-type="<?php echo htmlspecialchars($offer_type); ?>"
                                        data-device="<?php echo isset($offer['device']) ? htmlspecialchars($offer['device']) : ''; ?>">
                                        View Details
                                    </button>
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
    
    <div class="col-lg-4">
        <?php if ($device_info['is_mobile']): ?>
        <div class="card mb-4 bg-primary text-white">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-mobile-alt me-2"></i> Mobile User Benefits</h5>
            </div>
            <div class="card-body">
                <p>As a mobile user, you can earn points faster by installing apps! Mobile app install tasks typically offer:</p>
                <ul>
                    <li>Higher point rewards</li>
                    <li>Faster completion times</li>
                    <li>Simple requirements</li>
                </ul>
                <p class="mb-0">Look for the <i class="fas fa-mobile-alt"></i> APP INSTALL tag to find the best tasks for your device.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Tips for Completing Tasks</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Complete all requirements fully
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Use authentic information
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Disable ad blockers during tasks
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Allow notifications if required
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Points are credited after verification
                    </li>
                    <?php if ($device_info['is_mobile']): ?>
                    <li class="list-group-item bg-light">
                        <i class="fas fa-mobile-alt text-primary me-2"></i>
                        <strong>App installs earn more spins!</strong>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Task Details Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalLabel">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 mb-3 mb-md-0" id="taskImageCol">
                        <div class="task-modal-image-container">
                            <img src="" id="taskImage" class="img-fluid rounded mb-3" alt="Task image">
                            <div id="taskTypeTag" class="task-type-tag"></div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="task-details-container">
                            <div class="mb-3">
                                <div id="taskDeviceCompat" class="task-device-compatibility mb-2 d-flex"></div>
                                <h6 class="fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i>Description</h6>
                                <p id="taskDescription" class="ms-4"></p>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="fw-bold"><i class="fas fa-check-circle me-2 text-success"></i>Requirements</h6>
                                <p id="taskRequirements" class="ms-4"></p>
                            </div>
                            
                            <div id="instructionsSection" class="mb-3">
                                <h6 class="fw-bold"><i class="fas fa-list-check me-2 text-warning"></i>Instructions</h6>
                                <p id="taskAdCopy" class="ms-4"></p>
                            </div>
                            
                            <div class="mt-4 mb-3 text-center">
                                <div class="reward-box p-3 rounded bg-light">
                                    <h6 class="mb-1">Points Reward</h6>
                                    <p id="taskPoints" class="mb-0 fw-bold fs-4 text-primary"></p>
                                </div>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <a href="#" id="directTaskLink" class="btn btn-primary btn-lg start-task-btn" target="_blank">
                                    <i class="fas fa-play-circle me-2"></i> Start Task
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden form that records the task start in our database -->
                <form id="taskForm" method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" style="display:none;">
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="offer_id" value="">
                    <input type="hidden" name="offer_link" value="">
                    <input type="hidden" name="hidden_form_submission" value="1">
                    <button type="submit" id="hiddenSubmitButton">Submit</button>
                </form>
            </div>
            <!-- Modal footer removed as requested -->
        </div>
    </div>
</div>

<?php include 'includes/dashboard_footer.php'; ?>
