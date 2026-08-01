// Mobile Sidebar Toggle
const hamburger = document.querySelector('.hamburger');
const mobileSidebar = document.querySelector('.mobile-sidebar');
const sidebarOverlay = document.querySelector('.sidebar-overlay');

if (hamburger && mobileSidebar) {
    hamburger.addEventListener('click', function() {
        hamburger.classList.toggle('active');
        mobileSidebar.classList.toggle('active');
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('active');
        }
    });

    // Close sidebar when a link is clicked
    mobileSidebar.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.addEventListener('click', function() {
            hamburger.classList.remove('active');
            mobileSidebar.classList.remove('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
        });
    });

    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            hamburger.classList.remove('active');
            mobileSidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }
}

// Set active nav link
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.nav-menu a').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // Also mark active in sidebar
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// Add scroll animation for elements
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -100px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.animation = 'fadeIn 0.6s ease-out forwards';
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

document.querySelectorAll('.feature-card, .product-card, .value-card, .why-card').forEach(el => {
    observer.observe(el);
});
