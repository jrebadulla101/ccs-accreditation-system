/**
 * Main JavaScript file for the CCS Accreditation System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar on mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Toggle dropdown menus
    const dropdownToggle = document.querySelector('.dropdown-toggle');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    
    if (dropdownToggle && dropdownMenu) {
        dropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (dropdownMenu.classList.contains('active') && !dropdownToggle.contains(e.target)) {
                dropdownMenu.classList.remove('active');
            }
        });
    }
    
    // Toggle submenu for sidebar
    const submenuLinks = document.querySelectorAll('.nav-link.has-submenu');
    submenuLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const navItem = this.parentElement;
            const submenu = navItem.querySelector('.submenu');
            
            if (submenu) {
                if (window.innerWidth <= 992) {
                    navItem.classList.toggle('active');
                } else {
                    if (submenu.style.maxHeight) {
                        submenu.style.maxHeight = null;
                        navItem.classList.remove('active');
                    } else {
                        submenu.style.maxHeight = submenu.scrollHeight + "px";
                        navItem.classList.add('active');
                    }
                }
            }
        });
    });
}); 