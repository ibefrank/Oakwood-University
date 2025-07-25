/**
 * Oakwood University Website - Main JavaScript
 * Handles interactive features, form validation, and dynamic content loading
 */

// Global variables
let currentPage = 1;
let currentFilter = 'all';
let newsData = [];
let filteredNews = [];
const itemsPerPage = 6;

// DOM Content Loaded Event
document.addEventListener('DOMContentLoaded', function() {
    initializeNavigation();
    initializeAnimations();
    initializeCounters();
    initializeFilters();
    initializeForms();
    loadNews();
    initializeSearch();
    initializeScrollEffects();
});

/**
 * Navigation functionality
 */
function initializeNavigation() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Mobile menu toggle
    if (hamburger) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }
    
    // Close mobile menu when clicking on a link
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (navMenu.classList.contains('active')) {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            }
        });
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        }
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 100) {
            navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            navbar.style.backdropFilter = 'blur(10px)';
        } else {
            navbar.style.background = '#fff';
            navbar.style.backdropFilter = 'none';
        }
    });
}

/**
 * Initialize scroll animations
 */
function initializeAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, observerOptions);
    
    // Observe all elements that should animate
    document.querySelectorAll('.feature-card, .news-card, .program-card, .value-item, .leader-card, .timeline-item').forEach(el => {
        el.classList.add('fade-in');
        observer.observe(el);
    });
}

/**
 * Initialize counter animations
 */
function initializeCounters() {
    const counters = document.querySelectorAll('.stat-number');
    
    const animateCounter = (counter) => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000; // 2 seconds
        const steps = 60;
        const stepValue = target / steps;
        const stepDuration = duration / steps;
        let current = 0;
        
        const timer = setInterval(() => {
            current += stepValue;
            if (current >= target) {
                counter.textContent = target;
                clearInterval(timer);
            } else {
                counter.textContent = Math.floor(current);
            }
        }, stepDuration);
    };
    
    // Observe counters for animation
    const counterObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            }
        });
    });
    
    counters.forEach(counter => {
        counterObserver.observe(counter);
    });
}

/**
 * Initialize filter functionality
 */
function initializeFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const programCards = document.querySelectorAll('.program-card');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filter programs
            programCards.forEach(card => {
                const categories = card.getAttribute('data-category');
                if (filter === 'all' || (categories && categories.includes(filter))) {
                    card.style.display = 'block';
                    card.classList.add('fade-in');
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    
    // News filter functionality
    const newsFilterButtons = document.querySelectorAll('.news-filter .filter-btn');
    if (newsFilterButtons.length > 0) {
        newsFilterButtons.forEach(button => {
            button.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                
                // Update active button
                newsFilterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Filter news
                filterNews(category);
            });
        });
    }
}

/**
 * Initialize form handling
 */
function initializeForms() {
    // Contact form
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', handleContactForm);
    }
    
    // Newsletter form
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', handleNewsletterForm);
    }
    
    // Form validation
    const formInputs = document.querySelectorAll('input, select, textarea');
    formInputs.forEach(input => {
        input.addEventListener('blur', validateField);
        input.addEventListener('input', clearErrors);
    });
}

/**
 * Handle contact form submission
 */
async function handleContactForm(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    const messageDiv = document.getElementById('formMessage');
    
    // Validate form
    if (!validateContactForm()) {
        return;
    }
    
    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="loading"></span> Sending...';
    
    try {
        const response = await fetch('php/contact-handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            messageDiv.className = 'form-message success';
            messageDiv.textContent = result.message;
            messageDiv.style.display = 'block';
            form.reset();
        } else {
            messageDiv.className = 'form-message error';
            messageDiv.textContent = result.message;
            messageDiv.style.display = 'block';
        }
    } catch (error) {
        messageDiv.className = 'form-message error';
        messageDiv.textContent = 'An error occurred. Please try again later.';
        messageDiv.style.display = 'block';
    } finally {
        submitButton.disabled = false;
        submitButton.innerHTML = 'Send Message';
    }
}

/**
 * Handle newsletter form submission
 */
async function handleNewsletterForm(e) {
    e.preventDefault();
    
    const form = e.target;
    const email = form.querySelector('#newsletterEmail').value;
    const submitButton = form.querySelector('button[type="submit"]');
    const messageDiv = document.getElementById('newsletterMessage');
    
    if (!validateEmail(email)) {
        showNewsletterMessage('Please enter a valid email address.', 'error');
        return;
    }
    
    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="loading"></span> Subscribing...';
    
    try {
        const response = await fetch('php/contact-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'newsletter',
                email: email
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNewsletterMessage(result.message, 'success');
            form.reset();
        } else {
            showNewsletterMessage(result.message, 'error');
        }
    } catch (error) {
        showNewsletterMessage('An error occurred. Please try again later.', 'error');
    } finally {
        submitButton.disabled = false;
        submitButton.innerHTML = 'Subscribe';
    }
}

/**
 * Show newsletter message
 */
function showNewsletterMessage(message, type) {
    const messageDiv = document.getElementById('newsletterMessage');
    messageDiv.className = `newsletter-message ${type}`;
    messageDiv.textContent = message;
    messageDiv.style.display = 'block';
    
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 5000);
}

/**
 * Validate contact form
 */
function validateContactForm() {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const subject = document.getElementById('subject').value;
    const message = document.getElementById('message').value.trim();
    
    let isValid = true;
    
    // Validate name
    if (!name || name.length < 2) {
        showFieldError('name', 'Please enter a valid name (at least 2 characters).');
        isValid = false;
    }
    
    // Validate email
    if (!validateEmail(email)) {
        showFieldError('email', 'Please enter a valid email address.');
        isValid = false;
    }
    
    // Validate subject
    if (!subject) {
        showFieldError('subject', 'Please select a subject.');
        isValid = false;
    }
    
    // Validate message
    if (!message || message.length < 10) {
        showFieldError('message', 'Please enter a message (at least 10 characters).');
        isValid = false;
    }
    
    return isValid;
}

/**
 * Validate individual field
 */
function validateField(e) {
    const field = e.target;
    const value = field.value.trim();
    
    switch (field.id) {
        case 'name':
            if (!value || value.length < 2) {
                showFieldError('name', 'Please enter a valid name (at least 2 characters).');
            } else {
                clearFieldError('name');
            }
            break;
        case 'email':
            if (!validateEmail(value)) {
                showFieldError('email', 'Please enter a valid email address.');
            } else {
                clearFieldError('email');
            }
            break;
        case 'phone':
            if (value && !validatePhone(value)) {
                showFieldError('phone', 'Please enter a valid phone number.');
            } else {
                clearFieldError('phone');
            }
            break;
        case 'message':
            if (!value || value.length < 10) {
                showFieldError('message', 'Please enter a message (at least 10 characters).');
            } else {
                clearFieldError('message');
            }
            break;
    }
}

/**
 * Clear errors on input
 */
function clearErrors(e) {
    const field = e.target;
    clearFieldError(field.id);
}

/**
 * Show field error
 */
function showFieldError(fieldId, message) {
    const errorElement = document.getElementById(fieldId + 'Error');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}

/**
 * Clear field error
 */
function clearFieldError(fieldId) {
    const errorElement = document.getElementById(fieldId + 'Error');
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }
}

/**
 * Validate email format
 */
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validate phone format
 */
function validatePhone(phone) {
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
}

/**
 * Load news data
 */
async function loadNews() {
    try {
        const response = await fetch('php/news-handler.php?action=get_news');
        const data = await response.json();
        
        if (data.success) {
            newsData = data.news;
            filteredNews = newsData;
            displayNews();
            displayFeaturedNews();
        } else {
            console.error('Failed to load news:', data.message);
            displayNewsError();
        }
    } catch (error) {
        console.error('Error loading news:', error);
        displayNewsError();
    }
}

/**
 * Display news error message
 */
function displayNewsError() {
    const newsGrid = document.getElementById('news-grid');
    const newsList = document.getElementById('newsList');
    
    if (newsGrid) {
        newsGrid.innerHTML = '<div class="error-message">Unable to load news at this time. Please try again later.</div>';
    }
    
    if (newsList) {
        newsList.innerHTML = '<div class="error-message">Unable to load news at this time. Please try again later.</div>';
    }
}

/**
 * Display featured news
 */
function displayFeaturedNews() {
    const featuredNewsContainer = document.getElementById('featuredNews');
    const newsGrid = document.getElementById('news-grid');
    
    if (!newsData || newsData.length === 0) {
        return;
    }
    
    // Get featured news (first 3 items)
    const featuredNews = newsData.slice(0, 3);
    
    const container = featuredNewsContainer || newsGrid;
    if (!container) return;
    
    container.innerHTML = '';
    
    featuredNews.forEach(news => {
        const newsCard = createNewsCard(news);
        container.appendChild(newsCard);
    });
}

/**
 * Display news with pagination
 */
function displayNews() {
    const newsList = document.getElementById('newsList');
    if (!newsList) return;
    
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedNews = filteredNews.slice(startIndex, endIndex);
    
    newsList.innerHTML = '';
    
    if (paginatedNews.length === 0) {
        newsList.innerHTML = '<div class="no-results">No news articles found.</div>';
        return;
    }
    
    paginatedNews.forEach(news => {
        const newsCard = createNewsCard(news);
        newsList.appendChild(newsCard);
    });
    
    updatePagination();
}

/**
 * Create news card element
 */
function createNewsCard(news) {
    const card = document.createElement('div');
    card.className = 'news-card fade-in';
    card.setAttribute('data-category', news.category);
    
    card.innerHTML = `
        <div class="news-card-image">
            <i class="fas fa-newspaper"></i>
        </div>
        <div class="news-card-content">
            <h3>${escapeHtml(news.title)}</h3>
            <div class="news-date">${formatDate(news.date)}</div>
            <p class="news-excerpt">${escapeHtml(news.excerpt)}</p>
        </div>
    `;
    
    return card;
}

/**
 * Filter news by category
 */
function filterNews(category) {
    currentFilter = category;
    currentPage = 1;
    
    if (category === 'all') {
        filteredNews = newsData;
    } else {
        filteredNews = newsData.filter(news => news.category === category);
    }
    
    displayNews();
}

/**
 * Update pagination controls
 */
function updatePagination() {
    const pagination = document.getElementById('pagination');
    if (!pagination) return;
    
    const totalPages = Math.ceil(filteredNews.length / itemsPerPage);
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let paginationHTML = '';
    
    // Previous button
    if (currentPage > 1) {
        paginationHTML += `<button onclick="changePage(${currentPage - 1})">Previous</button>`;
    }
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        const activeClass = i === currentPage ? 'active' : '';
        paginationHTML += `<button class="${activeClass}" onclick="changePage(${i})">${i}</button>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        paginationHTML += `<button onclick="changePage(${currentPage + 1})">Next</button>`;
    }
    
    pagination.innerHTML = paginationHTML;
}

/**
 * Change page
 */
function changePage(page) {
    currentPage = page;
    displayNews();
    
    // Scroll to top of news section
    const newsSection = document.querySelector('.all-news');
    if (newsSection) {
        newsSection.scrollIntoView({ behavior: 'smooth' });
    }
}

/**
 * Initialize search functionality
 */
function initializeSearch() {
    const searchInput = document.getElementById('newsSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        if (searchTerm === '') {
            filterNews(currentFilter);
        } else {
            filteredNews = newsData.filter(news => 
                news.title.toLowerCase().includes(searchTerm) ||
                news.excerpt.toLowerCase().includes(searchTerm)
            );
            currentPage = 1;
            displayNews();
        }
    });
}

/**
 * Initialize scroll effects
 */
function initializeScrollEffects() {
    // Parallax effect for hero section
    window.addEventListener('scroll', function() {
        const hero = document.querySelector('.hero');
        if (hero) {
            const scrolled = window.pageYOffset;
            const parallax = scrolled * 0.5;
            hero.style.transform = `translateY(${parallax}px)`;
        }
    });
    
    // Show/hide scroll to top button
    const scrollToTopBtn = document.getElementById('scrollToTop');
    if (scrollToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollToTopBtn.style.display = 'block';
            } else {
                scrollToTopBtn.style.display = 'none';
            }
        });
        
        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
}

/**
 * Utility functions
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', options);
}

// Make functions available globally for onclick handlers
window.changePage = changePage;
