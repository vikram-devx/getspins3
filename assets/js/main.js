$(document).ready(function() {
    // Fixed Header Scroll Effect
    const fixedHeader = $('.fixed-header');
    
    if (fixedHeader.length) {
        // Listen for scroll events
        $(window).on('scroll', function() {
            if ($(this).scrollTop() > 10) {
                fixedHeader.addClass('scrolled');
            } else {
                fixedHeader.removeClass('scrolled');
            }
        });
        
        // Call on page load to set initial state
        if ($(window).scrollTop() > 10) {
            fixedHeader.addClass('scrolled');
        }
    }
    // Promo Slider Functionality
    if ($('.promo-slider').length) {
        var currentSlide = 0;
        var slides = $('.promo-slide');
        var totalSlides = slides.length;
        var slideWidth = 100; // percentage
        var autoSlideInterval;
        
        // Initialize slider if multiple slides exist
        if (totalSlides > 1) {
            // Start auto-sliding
            startAutoSlide();
            
            // Handle dot click
            $(document).on('click', '.slider-dot', function() {
                var index = $(this).data('index');
                goToSlide(index);
                resetAutoSlide();
            });
            
            // Handle arrow clicks
            $('.slider-arrow-left').on('click', function() {
                goToSlide(currentSlide - 1);
                resetAutoSlide();
            });
            
            $('.slider-arrow-right').on('click', function() {
                goToSlide(currentSlide + 1);
                resetAutoSlide();
            });
        }
        
        // Function to go to a specific slide
        function goToSlide(slideIndex) {
            // Handle circular navigation
            if (slideIndex < 0) {
                slideIndex = totalSlides - 1;
            } else if (slideIndex >= totalSlides) {
                slideIndex = 0;
            }
            
            // Update currentSlide
            currentSlide = slideIndex;
            
            // Move to the correct slide
            $('.promo-slides').css('transform', 'translateX(-' + (slideIndex * slideWidth) + '%)');
            
            // Update active dot
            $('.slider-dot').removeClass('active');
            $('.slider-dot[data-index="' + slideIndex + '"]').addClass('active');
        }
        
        // Auto-slide functionality
        function startAutoSlide() {
            autoSlideInterval = setInterval(function() {
                goToSlide(currentSlide + 1);
            }, 5000); // Change slide every 5 seconds
        }
        
        function resetAutoSlide() {
            clearInterval(autoSlideInterval);
            startAutoSlide();
        }
    }
    
    
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Task progress tracking
    function loadTaskProgress() {
        $.ajax({
            url: '/get_task_progress.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.progress && response.progress.length) {
                    updateTaskProgressUI(response.progress);
                } else {
                    // No tasks in progress
                    $('#no-tasks-message').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading task progress:', error);
            }
        });
    }
    
    function updateTaskProgressUI(progressData) {
        // Clear containers
        var activeContainer = $('#task-progress-container');
        var completedContainer = $('#completed-tasks-container');
        activeContainer.empty();
        completedContainer.empty();
        
        // Track newly completed tasks to show notifications
        var newlyCompletedTasks = [];
        
        // Separate active and completed tasks
        var activeTasks = [];
        var completedTasks = [];
        
        if (progressData && progressData.length > 0) {
            progressData.forEach(function(task) {
                // Check if this is a completed task
                if (task.status === 'completed') {
                    completedTasks.push(task);
                    
                    // Check if we've already notified about this task
                    var taskKey = 'task_completed_' + task.offer_id;
                    if (!localStorage.getItem(taskKey)) {
                        // Add to our notification list
                        newlyCompletedTasks.push({
                            id: task.offer_id,
                            name: task.offer_name || `Task #${task.offer_id}`,
                            points: task.points_earned || 0
                        });
                        
                        // Mark as notified
                        localStorage.setItem(taskKey, 'true');
                    }
                } 
                // Add to active tasks if not completed and not failed
                else if (task.status !== 'failed') {
                    activeTasks.push(task);
                }
            });
        }
        
        // Handle active tasks
        if (activeTasks.length === 0) {
            activeContainer.html('<p class="text-center text-muted" id="no-tasks-message">You don\'t have any active tasks.</p>');
        } else {
            // Generate HTML for each active task
            activeTasks.forEach(function(task) {
                var progressPercent = task.current_progress || task.progress_percent || 0;
                var statusClass = 'info';
                var statusIcon = 'fas fa-sync-alt fa-spin';
                var statusText = task.status.replace('_', ' ').toUpperCase();
                
                // Set appropriate classes based on status
                switch(task.status) {
                    case 'started':
                        statusClass = 'warning';
                        statusIcon = 'fas fa-hourglass-start';
                        break;
                    case 'in_progress':
                        statusClass = 'info';
                        statusIcon = 'fas fa-spinner fa-spin';
                        break;
                }
                
                // Always show Cancel button for active tasks
                var actionButtonsHTML = `
                    <button class="btn btn-sm btn-outline-danger cancel-task-btn" data-offer-id="${task.offer_id}">
                        <i class="fas fa-times-circle me-1"></i> Cancel
                    </button>
                `;
                
                // Create progress item HTML
                var progressHtml = `
                    <div class="task-progress-item mb-4" data-offer-id="${task.offer_id}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="task-name fw-bold">${task.offer_name || 'Offer #' + task.offer_id}</span>
                            <span class="badge bg-${statusClass}"><i class="${statusIcon} me-1"></i> ${statusText}</span>
                        </div>
                        <div class="progress mb-2" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped bg-${statusClass}" role="progressbar" 
                                style="width: ${progressPercent}%" 
                                aria-valuenow="${progressPercent}" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="text-muted">${task.progress_message || 'Processing...'}</span>
                            <span class="text-muted">${progressPercent}%</span>
                        </div>
                        <div class="text-end">${actionButtonsHTML}</div>
                    </div>
                `;
                
                activeContainer.append(progressHtml);
            });
            
            // Hide no tasks message
            $('#no-tasks-message').hide();
            
            // Attach event listeners for Cancel buttons
            $('.cancel-task-btn').on('click', function() {
                var offerId = $(this).data('offer-id');
                cancelTask(offerId);
            });
        }
        
        // Handle completed tasks - show only the 3 most recent
        const recentCompleted = completedTasks.sort((a, b) => {
            // Sort by completion time if available, otherwise by last_updated
            const aTime = a.completion_time || a.last_updated;
            const bTime = b.completion_time || b.last_updated;
            return new Date(bTime) - new Date(aTime); // Newest first
        }).slice(0, 3); // Take only the 3 most recent
        
        if (recentCompleted.length === 0) {
            completedContainer.html('<p class="text-center text-muted" id="no-completed-tasks-message">You haven\'t completed any tasks recently.</p>');
        } else {
            // Generate HTML for each completed task
            recentCompleted.forEach(function(task) {
                // Get completion time
                const completionTime = task.completion_time || task.last_updated;
                const formattedTime = new Date(completionTime).toLocaleString();
                
                // Get points earned
                const pointsEarned = task.points_earned || 0;
                
                // Create completed task item HTML
                var completedHtml = `
                    <div class="completed-task-item mb-3 p-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="task-name fw-bold">${task.offer_name || 'Offer #' + task.offer_id}</span>
                                <div class="text-muted small">Completed on ${formattedTime}</div>
                            </div>
                            <div class="text-success fw-bold">
                                +${pointsEarned} points
                            </div>
                        </div>
                    </div>
                `;
                
                completedContainer.append(completedHtml);
            });
            
            // Hide no completed tasks message
            $('#no-completed-tasks-message').hide();
            
            // Add "Show All" link if there are more than 3 completed tasks
            if (completedTasks.length > 3) {
                completedContainer.append(`
                    <div class="text-center mt-3">
                        <a href="completed_tasks.php" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-list me-1"></i> Show All (${completedTasks.length} tasks)
                        </a>
                    </div>
                `);
            }
        }
        
        // Show notifications for newly completed tasks - with a large, more noticeable popup
        if (newlyCompletedTasks.length > 0) {
            newlyCompletedTasks.forEach(function(task) {
                // Always show a more noticeable notification for completed tasks
                const notification = `
                    <div class="task-completion-popup">
                        <div class="task-completion-header">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Task Completed!
                        </div>
                        <div class="task-completion-body">
                            <p><strong>${task.name}</strong> has been completed successfully!</p>
                            <p class="points-earned">You earned <span>${task.points} points</span></p>
                        </div>
                    </div>
                `;
                
                // Create and append the notification element
                const $notification = $(notification);
                $('body').append($notification);
                
                // Show with animation
                setTimeout(() => {
                    $notification.addClass('show');
                    
                    // Remove after 5 seconds
                    setTimeout(() => {
                        $notification.removeClass('show');
                        setTimeout(() => {
                            $notification.remove();
                        }, 500);
                    }, 5000);
                }, 100);
                
                // Also show a regular notification
                showNotification(
                    'success', 
                    'Task Completed!', 
                    `Congratulations! You've earned ${task.points} points for completing ${task.name}.`
                );
            });
        }
    }
    
    // Function to cancel a task
    function cancelTask(offerId) {
        $.ajax({
            url: '/task_action.php',
            type: 'POST',
            data: {
                action: 'cancel',
                offer_id: offerId
            },
            dataType: 'json',
            beforeSend: function() {
                // Disable the cancel button and show loading state
                $(`.cancel-task-btn[data-offer-id="${offerId}"]`).prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin me-1"></i> Canceling...');
            },
            success: function(response) {
                if (response.status === 'success') {
                    // Show success notification
                    showNotification('success', 'Task Canceled', response.message);
                    
                    // Refresh task progress UI if task_progress data is returned
                    if (response.task_progress) {
                        updateTaskProgressUI(response.task_progress);
                    } else {
                        // Otherwise, just reload task progress data
                        loadTaskProgress();
                    }
                } else {
                    // Show error notification
                    showNotification('error', 'Error', response.message);
                    
                    // Re-enable the button
                    $(`.cancel-task-btn[data-offer-id="${offerId}"]`).prop('disabled', false)
                        .html('<i class="fas fa-times-circle me-1"></i> Cancel');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error canceling task:', error);
                showNotification('error', 'Error', 'Failed to cancel task. Please try again.');
                
                // Re-enable the button
                $(`.cancel-task-btn[data-offer-id="${offerId}"]`).prop('disabled', false)
                    .html('<i class="fas fa-times-circle me-1"></i> Cancel');
            }
        });
    }
    
    // Function to resume a task
    function resumeTask(offerId) {
        $.ajax({
            url: '/task_action.php',
            type: 'POST',
            data: {
                action: 'resume',
                offer_id: offerId
            },
            dataType: 'json',
            beforeSend: function() {
                // Disable the resume button and show loading state
                $(`.resume-task-btn[data-offer-id="${offerId}"]`).prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin me-1"></i> Resuming...');
            },
            success: function(response) {
                if (response.status === 'success') {
                    // Show success notification
                    showNotification('success', 'Task Resumed', response.message);
                    
                    // Refresh task progress UI if task_progress data is returned
                    if (response.task_progress) {
                        updateTaskProgressUI(response.task_progress);
                    } else {
                        // Otherwise, just reload task progress data
                        loadTaskProgress();
                    }
                } else {
                    // Show error notification
                    showNotification('error', 'Error', response.message);
                    
                    // Re-enable the button
                    $(`.resume-task-btn[data-offer-id="${offerId}"]`).prop('disabled', false)
                        .html('<i class="fas fa-play-circle me-1"></i> Resume');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error resuming task:', error);
                showNotification('error', 'Error', 'Failed to resume task. Please try again.');
                
                // Re-enable the button
                $(`.resume-task-btn[data-offer-id="${offerId}"]`).prop('disabled', false)
                    .html('<i class="fas fa-play-circle me-1"></i> Resume');
            }
        });
    }
    
    // simulateTaskProgress function has been removed
    
    // Load initial task progress if on tasks page
    if ($('#task-progress-container').length) {
        loadTaskProgress();
        
        // Poll for updates every 5 seconds
        setInterval(loadTaskProgress, 5000);
    }
    
    // Copy Postback URL Button
    $('.copy-btn').on('click', function() {
        var textToCopy = $(this).data('copy');
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(textToCopy).select();
        document.execCommand('copy');
        tempInput.remove();
        
        // Show copied feedback
        var originalText = $(this).text();
        $(this).text('Copied!');
        
        var btn = $(this);
        setTimeout(function() {
            btn.text(originalText);
        }, 2000);
    });
    
    // Form validation for register form
    $('#registerForm').on('submit', function(e) {
        var password = $('#password').val();
        var confirmPassword = $('#confirm_password').val();
        
        if (password !== confirmPassword) {
            e.preventDefault();
            $('#password-error').text('Passwords do not match').show();
            return false;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            $('#password-error').text('Password must be at least 6 characters').show();
            return false;
        }
        
        return true;
    });
    
    // Task details modal
    $('.view-task').on('click', function() {
        var taskId = $(this).data('id');
        var taskTitle = $(this).data('title');
        var taskDescription = $(this).data('description');
        var taskRequirements = $(this).data('requirements');
        var taskAdCopy = $(this).data('adcopy');
        var taskPoints = $(this).data('points');
        var taskLink = $(this).data('link');
        var taskImage = $(this).data('image');
        var taskType = $(this).data('type');
        var taskDevice = $(this).data('device');
        
        // Populate modal with task details
        $('#taskModalLabel').text(taskTitle);
        $('#taskDescription').text(taskDescription);
        $('#taskRequirements').html(taskRequirements);
        
        // Get instructions content
        // For CPI offers, check if instructions field exists in the future
        var instructionsContent = taskAdCopy || '';
        
        // Check if we need to hide the Instructions section
        if (!instructionsContent || (instructionsContent && taskDescription && instructionsContent.trim() === taskDescription.trim())) {
            // Hide the entire Instructions section completely when content is empty or matches description
            $('#instructionsSection').hide();
        } else {
            // Show Instructions section and set content
            $('#instructionsSection').show();
            $('#taskAdCopy').html(instructionsContent);
        }
        
        $('#taskPoints').text(taskPoints);
        
        // Handle device compatibility icons
        if (taskDevice) {
            var deviceIcons = '';
            var deviceString = taskDevice.toLowerCase();
            
            if (deviceString.includes('android')) {
                deviceIcons += '<span class="device-badge device-android me-2"><i class="fab fa-android"></i></span>';
            }
            
            if (deviceString.includes('iphone') || deviceString.includes('ipad') || deviceString.includes('ios')) {
                deviceIcons += '<span class="device-badge device-ios me-2"><i class="fab fa-apple"></i></span>';
            }
            
            if (deviceString.includes('desktop')) {
                deviceIcons += '<span class="device-badge me-2"><i class="fas fa-desktop"></i></span>';
            }
            
            $('#taskDeviceCompat').html(deviceIcons);
        } else {
            $('#taskDeviceCompat').html('');
        }
        
        // Handle task image
        if (taskImage && taskImage !== '') {
            $('#taskImage').attr('src', taskImage).show();
            $('#taskImageCol').show();
            
            // Set the task type badge
            var typeLabel, typeIcon;
            
            if (taskType.toLowerCase() === 'cpi') {
                typeLabel = 'APP INSTALL';
                typeIcon = '<i class="fas fa-mobile-alt me-1"></i>';
            } else if (taskType.toLowerCase() === 'cpa') {
                typeLabel = 'SURVEY OFFER';
                typeIcon = '<i class="fas fa-poll me-1"></i>';
            } else {
                typeLabel = taskType.toUpperCase();
                typeIcon = '<i class="fas fa-tasks me-1"></i>';
            }
            
            $('#taskTypeTag').html(typeIcon + ' ' + typeLabel).removeClass().addClass('task-type-tag').addClass('offer-type-' + taskType.toLowerCase());
        } else {
            $('#taskImageCol').hide();
        }
        
        // Set the offer ID in the form's hidden input
        $('#taskForm').find('input[name="offer_id"]').val(taskId);
        
        // Store the offer link if available (for tracking purposes)
        if (taskLink) {
            // Make sure the form goes to tasks.php for tracking
            $('#taskForm').attr('action', 'tasks.php');
            
            // Set the offer link value in the hidden input
            $('#taskForm').find('input[name="offer_link"]').val(taskLink);
            
            // Set up direct link
            $('#directTaskLink').attr('href', taskLink).text('Start Task').removeClass('disabled');
            
            // Log to console for debugging
            console.log("Set offer link:", taskLink);
        } else {
            console.log("No offer link available for this task");
            $('#directTaskLink').attr('href', '#').text('No Task Link Available').addClass('disabled');
        }
        
        // Show the modal
        var taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
        taskModal.show();
    });
    
    // Reward details modal
    $('.view-reward').on('click', function() {
        var rewardId = $(this).data('id');
        var rewardName = $(this).data('name');
        var rewardDescription = $(this).data('description');
        var rewardPoints = $(this).data('points');
        
        // Populate modal with reward details
        $('#rewardModalLabel').text(rewardName);
        $('#rewardDescription').text(rewardDescription);
        $('#rewardPoints').text(rewardPoints + ' points');
        // Make sure we use the correct path regardless of environment
        $('#redeemForm').attr('action', '/redeem.php');
        // Set the reward_id as a hidden input field instead of in the URL
        // This ensures compatibility with both URL formats
        $('#redeemForm input[name="reward_id"]').val(rewardId);
        
        // Always show game details fields for CoinMaster and Monopoly spin rewards
        if (rewardId == 6 || rewardId == 7) { // IDs for CoinMaster and Monopoly spin rewards
            $('#gameDetailsFields').show();
            $('#gameUsername').attr('required', true);
            
            // Set the placeholder text based on the game type
            if (rewardId == 6) {
                $('#gameUsername').attr('placeholder', 'Enter your CoinMaster username');
                $('.game-username-label').text('CoinMaster Username:');
                $('.game-invite-label').text('CoinMaster Invite Link (Optional):');
            } else {
                $('#gameUsername').attr('placeholder', 'Enter your Monopoly username');
                $('.game-username-label').text('Monopoly Username:');
                $('.game-invite-label').text('Monopoly Invite Link (Optional):');
            }
        } else {
            $('#gameDetailsFields').hide();
            $('#gameUsername').attr('required', false);
        }
        
        // Show the modal
        var rewardModal = new bootstrap.Modal(document.getElementById('rewardModal'));
        rewardModal.show();
    });
    
    // Admin reward form
    $('#rewardForm').on('submit', function(e) {
        var pointsRequired = $('#points_required').val();
        
        if (isNaN(pointsRequired) || pointsRequired <= 0) {
            e.preventDefault();
            $('#points-error').text('Points must be a positive number').show();
            return false;
        }
        
        return true;
    });
    
    // Confirm deletion
    $('.delete-btn').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
            return false;
        }
        return true;
    });
    
    // Tab navigation
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var targetTab = $(e.target).attr('href');
        // Update URL with tab ID
        if (history.pushState) {
            history.pushState(null, null, targetTab);
        }
    });
    
    // Activate tab based on URL hash
    var hash = window.location.hash;
    if (hash) {
        $('.nav-tabs a[href="' + hash + '"]').tab('show');
    }
    
    // Handle task start action - using the direct link approach
    $('#directTaskLink').on('click', function(e) {
        // Don't prevent default behavior - let the link naturally open in a new tab
        
        // Show loading state briefly
        $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...');
        $(this).addClass('disabled');
        
        // Get the offer link
        var offerLink = $(this).attr('href');
        
        if (offerLink && offerLink !== '#') {
            console.log("Opening offer link:", offerLink);
            
            // Submit the hidden form for tracking via AJAX
            setTimeout(function() {
                var formData = $('#taskForm').serialize();
                
                // Use AJAX to submit the form in the background
                $.ajax({
                    url: '/tasks.php',
                    data: formData,
                    type: 'GET',
                    success: function(response) {
                        console.log('Task tracking recorded');
                        
                        // After successful tracking, refresh the task progress display
                        if ($('#task-progress-container').length) {
                            loadTaskProgress();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error recording task tracking:', error);
                    }
                });
                
                // Close the modal after a short delay to ensure the new tab has opened
                setTimeout(function() {
                    // Hide the task modal
                    $('#taskModal').modal('hide');
                    
                    // Reset the button state after the modal is hidden (in case user views details again)
                    setTimeout(function() {
                        $('#directTaskLink').html('<i class="fas fa-play-circle me-2"></i> Start Task').removeClass('disabled');
                    }, 300);
                }, 500);
                
            }, 100);
        } else {
            console.log("No valid offer link found");
            e.preventDefault(); // Prevent navigation for disabled links
        }
    });
    
    // Redemption confirmation
    $('.redeem-btn').on('click', function(e) {
        var userPoints = parseInt($(this).data('user-points'));
        var requiredPoints = parseInt($(this).data('required-points'));
        
        if (userPoints < requiredPoints) {
            e.preventDefault();
            showNotification('error', 'Insufficient Points', 'You do not have enough points to redeem this reward.');
            return false;
        }
        
        var gameUsername = $('#gameUsername').val();
        var gameRequired = $('#gameUsername').prop('required');
        
        if (gameRequired && (!gameUsername || gameUsername.trim() === '')) {
            e.preventDefault();
            showNotification('error', 'Missing Information', 'Please enter your game username.');
            $('#gameUsername').focus();
            return false;
        }
        
        var confirmText = $('#confirmRedeem').val();
        if (confirmText !== 'CONFIRM') {
            e.preventDefault();
            showNotification('error', 'Confirmation Required', 'Please type "CONFIRM" to proceed with redemption.');
            $('#confirmRedeem').focus();
            return false;
        }
        
        return true;
    });
    
    // Custom notification system
    function showNotification(type, title, message) {
        // Remove any existing notifications
        $('.custom-notification').remove();
        
        // Set icon and color based on type
        var icon, bgColor, borderColor;
        switch(type) {
            case 'success':
                icon = 'fa-check-circle';
                bgColor = '#d4edda';
                borderColor = '#c3e6cb';
                break;
            case 'error':
                icon = 'fa-times-circle';
                bgColor = '#f8d7da';
                borderColor = '#f5c6cb';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                bgColor = '#fff3cd';
                borderColor = '#ffeeba';
                break;
            case 'info':
            default:
                icon = 'fa-info-circle';
                bgColor = '#d1ecf1';
                borderColor = '#bee5eb';
                break;
        }
        
        // Create notification HTML
        var notificationHtml = `
            <div class="custom-notification" style="display: none;">
                <div class="notification-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <div class="notification-message">${message}</div>
                </div>
                <div class="notification-close">
                    <i class="fas fa-times"></i>
                </div>
            </div>
        `;
        
        // Append notification to body
        $('body').append(notificationHtml);
        
        // Set colors
        $('.custom-notification').css({
            'background-color': bgColor,
            'border-color': borderColor
        });
        
        // Show notification with animation
        $('.custom-notification').slideDown(300);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.custom-notification').slideUp(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Close on click
        $('.notification-close').on('click', function() {
            $(this).parent().slideUp(300, function() {
                $(this).remove();
            });
        });
    }
    
    // Dashboard Promo Slider Functionality
    if ($('.dashboard-promo-slider').length) {
        var dashCurrentSlide = 0;
        var dashSlides = $('.dashboard-slide');
        var dashTotalSlides = dashSlides.length;
        var dashAutoSlideInterval;
        
        console.log("Found " + dashTotalSlides + " slides in the dashboard promo slider");
        
        // Initial styling to ensure all slides are visible and fix white slides issue
        $('.dashboard-slide').css({
            'visibility': 'visible',
            'opacity': '1',
            'display': 'block',
            'transform': 'translateZ(0)',
            'position': 'relative'
        });
        
        // Important: Make sure slide content is properly styled
        $('.dashboard-slide-content').css({
            'height': '100%',
            'width': '100%',
            'box-sizing': 'border-box'
        });
        
        // Additional slider container styling for smoother transitions
        $('.dashboard-slider').css({
            'display': 'flex',
            'flex-direction': 'row',
            'flex-wrap': 'nowrap',
            'overflow': 'hidden',
            'transition': 'transform 0.5s ease-in-out',
            'transform': 'translateZ(0)',
            'width': '100%'
        });
        
        // Make sure wrapper doesn't hide slides
        $('.dashboard-slider-wrapper').css({
            'overflow': 'hidden',
            'position': 'relative',
            'width': '100%'
        });
        
        // Function to go to a specific slide
        function dashGoToSlide(slideIndex) {
            // Handle circular navigation
            if (slideIndex < 0) {
                slideIndex = dashTotalSlides - 1;
            } else if (slideIndex >= dashTotalSlides) {
                slideIndex = 0;
            }
            
            console.log("Moving to slide " + slideIndex);
            
            // Update currentSlide
            dashCurrentSlide = slideIndex;
            
            // Make sure all slides remain visible in the DOM (important!)
            dashSlides.css({
                'visibility': 'visible',
                'opacity': '1',
                'display': 'block'
            });
            
            // Use transform for smooth sliding on all devices
            $('.dashboard-slider').css({
                'transform': 'translateX(-' + (slideIndex * 100) + '%)'
            });
            
            // Force background colors to be visible
            $('.dashboard-slide').each(function(i) {
                if (i === slideIndex) {
                    $(this).css('z-index', '5');
                } else {
                    $(this).css('z-index', '1');
                }
            });
            
            // Update active dot indicator
            $('.dashboard-slider-dot').removeClass('active');
            $('.dashboard-slider-dot[data-slide="' + slideIndex + '"]').addClass('active');
        }
        
        // Auto-slide functionality
        function dashStartAutoSlide() {
            dashAutoSlideInterval = setInterval(function() {
                dashGoToSlide(dashCurrentSlide + 1);
            }, 4000); // Change slide every 4 seconds
        }
        
        function dashResetAutoSlide() {
            clearInterval(dashAutoSlideInterval);
            dashStartAutoSlide();
        }
        
        // Initialize slider if multiple slides exist
        if (dashTotalSlides > 1) {
            console.log("Initializing slider with multiple slides");
            
            // Explicitly set dimensions for each slide to ensure proper display
            dashSlides.each(function(index) {
                $(this).css({
                    'width': '100%',
                    'flex': '0 0 100%',
                    'min-width': '100%',
                    'background-color': (index === 0) ? '#0d6efd' : (index === 1) ? '#198754' : (index === 2) ? '#ffc107' : '#dc3545'
                });
                
                // Force slide content to match the slide's data index
                var slideContent = $(this).find('.dashboard-slide-content');
                if (slideContent.length) {
                    slideContent.css({
                        'display': 'flex',
                        'width': '100%',
                        'height': '100%',
                        'z-index': '2',
                        'background-color': (index === 0) ? '#0d6efd !important' : 
                                          (index === 1) ? '#198754 !important' : 
                                          (index === 2) ? '#ffc107 !important' : 
                                          '#dc3545 !important'
                    });
                }
                
                // Verify slide is ready
                console.log("Slide " + index + " prepared with proper styling");
            });
            
            // Immediately go to the first slide to ensure proper initialization
            dashGoToSlide(0);
            
            // Start auto-sliding after a delay to ensure DOM is fully loaded
            setTimeout(function() {
                dashStartAutoSlide();
            }, 1000);
            
            // Handle dot click
            $(document).on('click', '.dashboard-slider-dot', function() {
                var index = $(this).data('slide');
                
                // Use transition for all devices
                dashGoToSlide(index);
                dashResetAutoSlide();
            });
            
            // No arrow navigation, just auto-sliding
        }
    }
    
    // Mobile Slide Menu Functionality
    if ($('.mobile-slide-menu').length) {
        // Show mobile menu when hamburger is clicked
        $('#mobileMenuToggle').on('click', function(e) {
            e.preventDefault();
            $('.mobile-slide-menu').addClass('active');
            $('.menu-backdrop').addClass('active');
            $('body').css('overflow', 'hidden'); // Prevent scrolling when menu is open
        });
        
        // Hide mobile menu when close button or backdrop is clicked
        $('.mobile-menu-close, .menu-backdrop').on('click', function(e) {
            e.preventDefault();
            $('.mobile-slide-menu').removeClass('active');
            $('.menu-backdrop').removeClass('active');
            $('body').css('overflow', ''); // Restore scrolling
        });
        
        // Close menu when clicking a link (for better mobile experience)
        $('.mobile-menu-link').on('click', function() {
            // Small timeout to allow the click to register before closing the menu
            setTimeout(function() {
                $('.mobile-slide-menu').removeClass('active');
                $('.menu-backdrop').removeClass('active');
                $('body').css('overflow', '');
            }, 150);
        });
    }
});
