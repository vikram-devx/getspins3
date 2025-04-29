/**
 * Dashboard Promotional Slider Functionality
 * - Handles slide transitions with fade effect
 * - Auto-slides every 4 seconds with manual controls
 * - Touch-enabled for mobile swipe
 */
$(document).ready(function() {
    // Initialize dashboard slider if it exists
    initDashboardSlider();
    
    function initDashboardSlider() {
        if ($('.dashboard-slides').length) {
            var currentDashSlide = 0;
            var dashSlides = $('.dashboard-slide');
            var totalDashSlides = dashSlides.length;
            var dashAutoSlideInterval;
            
            console.log("Found " + totalDashSlides + " dashboard slides");
            
            // Initial setup - show first slide
            dashSlides.eq(0).addClass('active');
            
            // Function to show a specific slide
            function showDashSlide(index) {
                // Handle circular navigation
                if (index < 0) {
                    index = totalDashSlides - 1;
                } else if (index >= totalDashSlides) {
                    index = 0;
                }
                
                console.log("Moving to dashboard slide " + index);
                
                // Update current slide
                currentDashSlide = index;
                
                // Hide all slides and show the current one
                dashSlides.removeClass('active');
                dashSlides.eq(index).addClass('active');
                
                // Update dot indicators
                $('.dashboard-slider-dot').removeClass('active');
                $('.dashboard-slider-dot[data-slide="' + index + '"]').addClass('active');
            }
            
            // Auto-slide functionality
            function startDashAutoSlide() {
                dashAutoSlideInterval = setInterval(function() {
                    showDashSlide(currentDashSlide + 1);
                }, 4000); // Change slide every 4 seconds
            }
            
            function resetDashAutoSlide() {
                clearInterval(dashAutoSlideInterval);
                startDashAutoSlide();
            }
            
            // Start auto-sliding
            startDashAutoSlide();
            
            // Handle dot click
            $(document).on('click', '.dashboard-slider-dot', function() {
                var index = parseInt($(this).data('slide'));
                showDashSlide(index);
                resetDashAutoSlide();
            });
            
            // Add touch swipe functionality for mobile
            var touchStartX = 0;
            var touchEndX = 0;
            
            $('.dashboard-slides').on('touchstart', function(e) {
                touchStartX = e.originalEvent.touches[0].clientX;
            });
            
            $('.dashboard-slides').on('touchend', function(e) {
                touchEndX = e.originalEvent.changedTouches[0].clientX;
                handleDashSwipe();
            });
            
            function handleDashSwipe() {
                var swipeThreshold = 50;
                if (touchEndX < touchStartX - swipeThreshold) {
                    // Swiped left, go to next slide
                    showDashSlide(currentDashSlide + 1);
                    resetDashAutoSlide();
                } else if (touchEndX > touchStartX + swipeThreshold) {
                    // Swiped right, go to previous slide
                    showDashSlide(currentDashSlide - 1);
                    resetDashAutoSlide();
                }
            }
        }
    }
});