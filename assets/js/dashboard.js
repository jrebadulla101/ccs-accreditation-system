/**
 * Dashboard-specific JavaScript
 * CCS Accreditation System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Evidence Status Chart
    initEvidenceStatusChart();
    
    // Animate stat counts on scroll
    initStatCountAnimation();
    
    // Initialize dashboard card animations
    initDashboardCardAnimations();
});

/**
 * Initialize the Evidence Status chart using Chart.js
 */
function initEvidenceStatusChart() {
    const chartCanvas = document.getElementById('evidenceStatusChart');
    if (!chartCanvas) return;
    
    // Get data from hidden input fields or data attributes
    const pendingCount = parseInt(document.querySelector('.status-pending .status-count').innerText) || 0;
    const approvedCount = parseInt(document.querySelector('.status-approved .status-count').innerText) || 0;
    const rejectedCount = parseInt(document.querySelector('.status-rejected .status-count').innerText) || 0;
    
    // Create chart
    new Chart(chartCanvas, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Approved', 'Rejected'],
            datasets: [{
                data: [pendingCount, approvedCount, rejectedCount],
                backgroundColor: ['#f8c200', '#34c759', '#ff3b30'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
}

/**
 * Animate stat count numbers on scroll
 */
function initStatCountAnimation() {
    const statCounts = document.querySelectorAll('.stat-count');
    if (!statCounts.length) return;
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCountUp(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    statCounts.forEach(stat => {
        observer.observe(stat);
    });
}

/**
 * Animate a number counting up to its target value
 * @param {HTMLElement} element - The element containing the number to animate
 */
function animateCountUp(element) {
    const targetValue = parseInt(element.innerText);
    const duration = 1500; // ms
    const startTime = performance.now();
    
    function updateCount(currentTime) {
        const elapsedTime = currentTime - startTime;
        if (elapsedTime > duration) {
            element.innerText = targetValue;
            return;
        }
        
        const progress = elapsedTime / duration;
        // Use easeOutQuad for smoother animation
        const easedProgress = -progress * (progress - 2);
        const currentValue = Math.floor(easedProgress * targetValue);
        element.innerText = currentValue;
        
        requestAnimationFrame(updateCount);
    }
    
    requestAnimationFrame(updateCount);
}

/**
 * Initialize dashboard card animations
 */
function initDashboardCardAnimations() {
    const cards = document.querySelectorAll('.dashboard-card');
    if (!cards.length) return;
    
    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('card-visible');
                cardObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    cards.forEach((card, index) => {
        // Add animation delay classes
        card.style.animationDelay = `${index * 0.1}s`;
        cardObserver.observe(card);
    });
}

/**
 * Toggle dashboard card expand/collapse
 * @param {HTMLElement} cardHeader - The card header element that was clicked
 */
function toggleCardExpand(cardHeader) {
    const card = cardHeader.closest('.dashboard-card');
    card.classList.toggle('card-expanded');
    
    const cardBody = card.querySelector('.card-body');
    if (card.classList.contains('card-expanded')) {
        cardBody.style.maxHeight = cardBody.scrollHeight + 'px';
    } else {
        cardBody.style.maxHeight = '';
    }
}

// Add event listeners to card headers for expand/collapse functionality
document.querySelectorAll('.card-header').forEach(header => {
    header.addEventListener('click', function(e) {
        // Only toggle if clicking on the header itself or the card title, not on buttons or other elements
        if (e.target === this || e.target.classList.contains('card-title') || e.target.tagName === 'I') {
            toggleCardExpand(this);
        }
    });
}); 