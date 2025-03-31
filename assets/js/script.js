document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar for mobile view
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                e.target !== sidebarToggle) {
                sidebar.classList.remove('active');
            }
        });
    }
    
    // Close alerts
    const closeButtons = document.querySelectorAll('.alert .close-btn');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.style.display = 'none';
        });
    });
    
    // Dropdown submenu in sidebar
    const menuItems = document.querySelectorAll('.sidebar-nav > ul > li > a');
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains('submenu')) {
                e.preventDefault();
                
                // Toggle the clicked submenu
                if (window.innerWidth > 768) {
                    // For desktop view
                    submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
                } else {
                    // For mobile view, toggle height instead of display for smoother animation
                    if (submenu.style.height === 'auto' || submenu.style.height === '') {
                        submenu.style.height = submenu.scrollHeight + 'px';
                        setTimeout(() => {
                            submenu.style.height = '0';
                        }, 10);
                    } else {
                        submenu.style.height = submenu.scrollHeight + 'px';
                        setTimeout(() => {
                            submenu.style.height = 'auto';
                        }, 300);
                    }
                }
            }
        });
    });
    
    // Auto-hide flash messages after 5 seconds
    const flashMessages = document.querySelectorAll('.alert');
    flashMessages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });
    
    // Custom file upload
    const fileInput = document.getElementById('evidence_file');
    const fileLabel = document.querySelector('.file-upload-btn');
    const filePreview = document.querySelector('.file-preview');
    
    if (fileInput && fileLabel && filePreview) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                updateFilePreview(file);
            }
        });
        
        // Preview file function
        function updateFilePreview(file) {
            const filePreviewName = document.querySelector('.file-preview-name');
            const filePreviewSize = document.querySelector('.file-preview-size');
            const filePreviewIcon = document.querySelector('.file-preview-icon i');
            
            if (!filePreviewName || !filePreviewSize || !filePreviewIcon) return;
            
            filePreview.style.display = 'block';
            filePreviewName.textContent = file.name;
            
            // Format file size
            let fileSize = (file.size / 1024).toFixed(2) + ' KB';
            if (file.size > 1024 * 1024) {
                fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            }
            filePreviewSize.textContent = fileSize;
            
            // Set icon based on file type
            if (file.type.includes('image')) {
                filePreviewIcon.className = 'fas fa-file-image';
            } else if (file.type.includes('pdf')) {
                filePreviewIcon.className = 'fas fa-file-pdf';
            } else if (file.type.includes('word')) {
                filePreviewIcon.className = 'fas fa-file-word';
            } else if (file.type.includes('excel') || file.type.includes('spreadsheet')) {
                filePreviewIcon.className = 'fas fa-file-excel';
            } else if (file.type.includes('powerpoint') || file.type.includes('presentation')) {
                filePreviewIcon.className = 'fas fa-file-powerpoint';
            } else if (file.type.includes('zip') || file.type.includes('rar') || file.type.includes('archive')) {
                filePreviewIcon.className = 'fas fa-file-archive';
            } else {
                filePreviewIcon.className = 'fas fa-file-alt';
            }
        }
        
        // Remove file button functionality
        const removeFileBtn = document.querySelector('.file-preview-remove');
        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                fileInput.value = '';
                filePreview.style.display = 'none';
            });
        }
        
        // Drag and drop functionality
        const uploadContainer = document.querySelector('.file-upload-container');
        if (uploadContainer) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadContainer.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadContainer.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadContainer.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                uploadContainer.classList.add('highlight');
            }
            
            function unhighlight() {
                uploadContainer.classList.remove('highlight');
            }
            
            uploadContainer.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length) {
                    fileInput.files = files;
                    if (files[0]) {
                        updateFilePreview(files[0]);
                    }
                }
            }
        }
    }
    
    // Toggle between file upload and drive link
    const evidenceTypeRadios = document.querySelectorAll('input[name="evidence_type"]');
    const fileUploadSection = document.getElementById('file_upload_section');
    const driveLinkSection = document.getElementById('drive_link_section');
    
    if (evidenceTypeRadios.length && fileUploadSection && driveLinkSection) {
        evidenceTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'file') {
                    fileUploadSection.style.display = 'block';
                    driveLinkSection.style.display = 'none';
                } else if (this.value === 'drive') {
                    fileUploadSection.style.display = 'none';
                    driveLinkSection.style.display = 'block';
                }
            });
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('form:not(.no-validation)');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    // Add validation message if it doesn't exist
                    const parent = field.parentElement;
                    if (!parent.querySelector('.validation-message')) {
                        const validationMessage = document.createElement('div');
                        validationMessage.className = 'validation-message';
                        validationMessage.textContent = 'This field is required';
                        validationMessage.style.color = '#dc3545';
                        validationMessage.style.fontSize = '12px';
                        validationMessage.style.marginTop = '5px';
                        parent.appendChild(validationMessage);
                    }
                } else {
                    field.classList.remove('is-invalid');
                    
                    // Remove validation message if it exists
                    const validationMessage = field.parentElement.querySelector('.validation-message');
                    if (validationMessage) {
                        validationMessage.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Remove validation styling on input
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                    
                    // Remove validation message if it exists
                    const validationMessage = this.parentElement.querySelector('.validation-message');
                    if (validationMessage) {
                        validationMessage.remove();
                    }
                }
            });
        });
    });
    
    // Tooltips
    const tooltips = document.querySelectorAll('.tooltip');
    tooltips.forEach(tooltip => {
        const tooltipText = tooltip.getAttribute('data-tooltip');
        if (tooltipText) {
            const tooltipElement = document.createElement('span');
            tooltipElement.className = 'tooltip-text';
            tooltipElement.textContent = tooltipText;
            tooltip.appendChild(tooltipElement);
        }
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Check if it's a sidebar submenu toggle
            if (!this.parentElement.querySelector('.submenu')) {
                e.preventDefault();
                
                const targetElement = document.querySelector(href);
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    
    // Initialize particles.js if element exists
    const particlesContainer = document.getElementById('particles-js');
    if (particlesContainer && typeof particlesJS !== 'undefined') {
        particlesJS('particles-js', {
            "particles": {
                "number": {
                    "value": 80,
                    "density": {
                        "enable": true,
                        "value_area": 800
                    }
                },
                "color": {
                    "value": "#4A90E2"
                },
                "shape": {
                    "type": "circle",
                    "stroke": {
                        "width": 0,
                        "color": "#000000"
                    },
                    "polygon": {
                        "nb_sides": 5
                    }
                },
                "opacity": {
                    "value": 0.1,
                    "random": false,
                    "anim": {
                        "enable": false,
                        "speed": 1,
                        "opacity_min": 0.1,
                        "sync": false
                    }
                },
                "size": {
                    "value": 3,
                    "random": true,
                    "anim": {
                        "enable": false,
                        "speed": 40,
                        "size_min": 0.1,
                        "sync": false
                    }
                },
                "line_linked": {
                    "enable": true,
                    "distance": 150,
                    "color": "#808080",
                    "opacity": 0.1,
                    "width": 1
                },
                "move": {
                    "enable": true,
                    "speed": 2,
                    "direction": "none",
                    "random": false,
                    "straight": false,
                    "out_mode": "out",
                    "bounce": false,
                    "attract": {
                        "enable": false,
                        "rotateX": 600,
                        "rotateY": 1200
                    }
                }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": {
                    "onhover": {
                        "enable": true,
                        "mode": "grab"
                    },
                    "onclick": {
                        "enable": false,
                        "mode": "push"
                    },
                    "resize": true
                },
                "modes": {
                    "grab": {
                        "distance": 140,
                        "line_linked": {
                            "opacity": 0.3
                        }
                    },
                    "bubble": {
                        "distance": 400,
                        "size": 40,
                        "duration": 2,
                        "opacity": 8,
                        "speed": 3
                    },
                    "repulse": {
                        "distance": 200,
                        "duration": 0.4
                    },
                    "push": {
                        "particles_nb": 4
                    },
                    "remove": {
                        "particles_nb": 2
                    }
                }
            },
            "retina_detect": true
        });
    }
    
    // Animate stats on scroll
    const statCounts = document.querySelectorAll('.stat-count');
    if (statCounts.length) {
        const animateValue = (element, start, end, duration) => {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                element.textContent = Math.floor(progress * (end - start) + start);
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        };
        
        // Use Intersection Observer API to trigger animation when elements are visible
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const target = entry.target;
                        const endValue = parseInt(target.textContent, 10);
                        animateValue(target, 0, endValue, 1500);
                        observer.unobserve(target);
                    }
                });
            }, { threshold: 0.1 });
            
            statCounts.forEach(counter => {
                observer.observe(counter);
            });
        } else {
            // Fallback for browsers that don't support Intersection Observer
            statCounts.forEach(counter => {
                const endValue = parseInt(counter.textContent, 10);
                animateValue(counter, 0, endValue, 1500);
            });
        }
    }
}); 