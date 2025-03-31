/**
 * Dashboard AJAX refresh functionality
 * CCS Accreditation System
 */

let dashboardRefreshInterval = null;
const REFRESH_INTERVAL = 60000; // Refresh dashboard data every 60 seconds

document.addEventListener('DOMContentLoaded', function() {
    // Setup refresh interval if auto refresh is enabled
    const autoRefresh = document.getElementById('auto-refresh-toggle');
    if (autoRefresh && autoRefresh.checked) {
        startDashboardRefresh();
    }
    
    // Add event listener for toggle change
    if (autoRefresh) {
        autoRefresh.addEventListener('change', function() {
            if (this.checked) {
                startDashboardRefresh();
            } else {
                stopDashboardRefresh();
            }
        });
    }
    
    // Add event listener for manual refresh button
    const refreshButton = document.getElementById('refresh-dashboard');
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            refreshDashboardData();
            
            // Show refresh animation
            this.classList.add('refreshing');
            setTimeout(() => {
                this.classList.remove('refreshing');
            }, 1000);
        });
    }
});

/**
 * Start the dashboard auto-refresh interval
 */
function startDashboardRefresh() {
    if (!dashboardRefreshInterval) {
        dashboardRefreshInterval = setInterval(refreshDashboardData, REFRESH_INTERVAL);
        console.log('Dashboard auto-refresh started');
    }
}

/**
 * Stop the dashboard auto-refresh interval
 */
function stopDashboardRefresh() {
    if (dashboardRefreshInterval) {
        clearInterval(dashboardRefreshInterval);
        dashboardRefreshInterval = null;
        console.log('Dashboard auto-refresh stopped');
    }
}

/**
 * Refresh dashboard data via AJAX
 */
function refreshDashboardData() {
    // Show loading state
    document.querySelectorAll('.dashboard-card').forEach(card => {
        card.classList.add('refreshing');
    });
    
    // Make AJAX request to get updated dashboard data
    fetch('ajax/dashboard-data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Update stats
            updateStats(data.stats);
            
            // Update evidence status
            updateEvidenceStatus(data.evidenceStatus);
            
            // Update recent activities
            updateRecentActivity(data.recentActivity);
            
            // Update recent uploads
            updateRecentUploads(data.recentUploads);
            
            // Remove loading state
            document.querySelectorAll('.dashboard-card').forEach(card => {
                card.classList.remove('refreshing');
            });
            
            // Update last refresh time
            const lastRefreshElement = document.getElementById('last-refresh-time');
            if (lastRefreshElement) {
                const now = new Date();
                lastRefreshElement.textContent = now.toLocaleTimeString();
            }
        })
        .catch(error => {
            console.error('Error refreshing dashboard data:', error);
            
            // Remove loading state
            document.querySelectorAll('.dashboard-card').forEach(card => {
                card.classList.remove('refreshing');
            });
        });
}

/**
 * Update dashboard statistics
 * @param {Object} stats - The updated statistics data
 */
function updateStats(stats) {
    for (const [key, value] of Object.entries(stats)) {
        const element = document.querySelector(`.stat-${key} .stat-count`);
        if (element) {
            // Animate to new value
            const oldValue = parseInt(element.textContent);
            if (oldValue !== value) {
                animateValue(element, oldValue, value, 500);
            }
        }
    }
}

/**
 * Update evidence status section
 * @param {Object} evidenceStatus - The updated evidence status data
 */
function updateEvidenceStatus(evidenceStatus) {
    // Update status counters
    for (const [status, count] of Object.entries(evidenceStatus)) {
        const element = document.querySelector(`.status-${status.toLowerCase()} .status-count`);
        if (element) {
            const oldValue = parseInt(element.textContent);
            if (oldValue !== count) {
                animateValue(element, oldValue, count, 500);
            }
        }
    }
    
    // Refresh chart if it exists
    const chartCanvas = document.getElementById('evidenceStatusChart');
    if (chartCanvas && window.evidenceStatusChart) {
        window.evidenceStatusChart.data.datasets[0].data = [
            evidenceStatus.pending || 0,
            evidenceStatus.approved || 0,
            evidenceStatus.rejected || 0
        ];
        window.evidenceStatusChart.update();
    }
}

/**
 * Update recent activity section
 * @param {Array} activities - The updated recent activities data
 */
function updateRecentActivity(activities) {
    const activityList = document.querySelector('.activity-list');
    if (activityList && activities.length > 0) {
        // Clear existing content
        activityList.innerHTML = '';
        
        // Add new activities
        activities.forEach(activity => {
            activityList.appendChild(createActivityItem(activity));
        });
    }
}

/**
 * Create an activity item element
 * @param {Object} activity - The activity data
 * @returns {HTMLElement} - The created activity item element
 */
function createActivityItem(activity) {
    const div = document.createElement('div');
    div.className = 'activity-item';
    
    let iconClass = 'fas fa-info-circle';
    if (activity.activity_type.includes('create')) {
        iconClass = 'fas fa-plus-circle';
    } else if (activity.activity_type.includes('update')) {
        iconClass = 'fas fa-edit';
    } else if (activity.activity_type.includes('delete')) {
        iconClass = 'fas fa-trash-alt';
    } else if (activity.activity_type.includes('upload')) {
        iconClass = 'fas fa-upload';
    } else if (activity.activity_type.includes('login')) {
        iconClass = 'fas fa-sign-in-alt';
    } else if (activity.activity_type.includes('logout')) {
        iconClass = 'fas fa-sign-out-alt';
    }
    
    div.innerHTML = `
        <div class="activity-icon">
            <i class="${iconClass}"></i>
        </div>
        <div class="activity-content">
            <div class="activity-description">${activity.description}</div>
            <div class="activity-meta">
                <span class="activity-user">${activity.full_name || 'Unknown User'}</span>
                <span class="activity-time">${formatDateTime(activity.created_at)}</span>
            </div>
        </div>
    `;
    
    return div;
}

/**
 * Update recent uploads section
 * @param {Array} uploads - The updated recent uploads data
 */
function updateRecentUploads(uploads) {
    const uploadsList = document.querySelector('.uploads-list');
    if (uploadsList && uploads.length > 0) {
        // Clear existing content
        uploadsList.innerHTML = '';
        
        // Add new uploads
        uploads.forEach(upload => {
            uploadsList.appendChild(createUploadItem(upload));
        });
    }
}

/**
 * Create an upload item element
 * @param {Object} upload - The upload data
 * @returns {HTMLElement} - The created upload item element
 */
function createUploadItem(upload) {
    const div = document.createElement('div');
    div.className = 'upload-item';
    
    let iconClass = 'fa-file-alt';
    if (upload.file_path) {
        const fileExt = upload.file_path.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
            iconClass = 'fa-file-image';
        } else if (fileExt === 'pdf') {
            iconClass = 'fa-file-pdf';
        } else if (['doc', 'docx'].includes(fileExt)) {
            iconClass = 'fa-file-word';
        } else if (['xls', 'xlsx'].includes(fileExt)) {
            iconClass = 'fa-file-excel';
        } else if (['ppt', 'pptx'].includes(fileExt)) {
            iconClass = 'fa-file-powerpoint';
        } else if (['zip', 'rar'].includes(fileExt)) {
            iconClass = 'fa-file-archive';
        }
    } else if (upload.drive_link) {
        iconClass = 'fab fa-google-drive';
    }
    
    div.innerHTML = `
        <div class="upload-icon">
            <i class="fas ${iconClass}"></i>
        </div>
        <div class="upload-content">
            <div class="upload-title">
                <a href="modules/evidence/view.php?id=${upload.id}">${upload.title}</a>
            </div>
            <div class="upload-meta">
                <span class="upload-parameter">${upload.parameter_name}</span>
                <span class="upload-status status-${upload.status.toLowerCase()}">${capitalizeFirstLetter(upload.status)}</span>
                <span class="upload-user">${upload.uploaded_by_name || 'Unknown'}</span>
                <span class="upload-time">${formatDateTime(upload.created_at)}</span>
            </div>
        </div>
    `;
    
    return div;
}

/**
 * Animate a number value change
 * @param {HTMLElement} element - The element to update
 * @param {number} start - The starting value
 * @param {number} end - The ending value
 * @param {number} duration - Animation duration in milliseconds
 */
function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        element.textContent = value;
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

/**
 * Format a date string to a readable format
 * @param {string} dateString - The date string to format
 * @returns {string} - The formatted date string
 */
function formatDateTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = (now - date) / 1000; // difference in seconds
    
    if (diff < 60) {
        return 'Just now';
    } else if (diff < 3600) {
        const minutes = Math.floor(diff / 60);
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else if (diff < 86400) {
        const hours = Math.floor(diff / 3600);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else if (diff < 172800) { // less than 2 days
        return 'Yesterday';
    } else {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            hour12: true
        });
    }
}

/**
 * Capitalize the first letter of a string
 * @param {string} string - The string to capitalize
 * @returns {string} - The capitalized string
 */
function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

/**
 * Check if two arrays of objects are different by comparing a specific property
 * @param {Array} arr1 - First array
 * @param {Array} arr2 - Second array
 * @param {string} idProperty - The property to compare for identification
 * @returns {boolean} - True if arrays are different, false otherwise
 */
function arraysAreDifferent(arr1, arr2, idProperty) {
    if (arr1.length !== arr2.length) return true;
    
    const arr1Ids = new Set(arr1.map(item => item[idProperty]));
    const arr2Ids = arr2.map(item => item[idProperty]);
    
    for (const id of arr2Ids) {
        if (!arr1Ids.has(id)) return true;
    }
    
    return false;
}

/**
 * Handle the auto-refresh toggle button UI state
 * @param {boolean} enabled - Whether auto-refresh is enabled
 */
function updateAutoRefreshUI(enabled) {
    const toggle = document.getElementById('auto-refresh-toggle');
    const status = document.getElementById('auto-refresh-status');
    
    if (toggle) toggle.checked = enabled;
    
    if (status) {
        status.textContent = enabled ? 'Auto-refresh is ON' : 'Auto-refresh is OFF';
        status.className = enabled ? 'status-on' : 'status-off';
    }
}

/**
 * Save user preference for auto-refresh to localStorage
 * @param {boolean} enabled - Whether auto-refresh is enabled
 */
function saveAutoRefreshPreference(enabled) {
    localStorage.setItem('dashboard_auto_refresh', enabled ? 'enabled' : 'disabled');
}

/**
 * Load user preference for auto-refresh from localStorage
 * @returns {boolean} - Whether auto-refresh should be enabled
 */
function loadAutoRefreshPreference() {
    const preference = localStorage.getItem('dashboard_auto_refresh');
    return preference === null ? true : preference === 'enabled';
}

// Initialize auto-refresh based on saved preference
document.addEventListener('DOMContentLoaded', function() {
    const shouldAutoRefresh = loadAutoRefreshPreference();
    updateAutoRefreshUI(shouldAutoRefresh);
    
    if (shouldAutoRefresh) {
        startDashboardRefresh();
    }
    
    // Add event listener for auto-refresh toggle
    const toggle = document.getElementById('auto-refresh-toggle');
    if (toggle) {
        toggle.addEventListener('change', function() {
            if (this.checked) {
                startDashboardRefresh();
            } else {
                stopDashboardRefresh();
            }
            saveAutoRefreshPreference(this.checked);
            updateAutoRefreshUI(this.checked);
        });
    }
}); 