/**
 * Catlaq AI Theme - Main JavaScript File
 * Handles theme interactivity, dark mode, and plugin integration
 */

(function() {
    'use strict';

    const CatlaqTheme = {
        /**
         * Initialize the theme
         */
        init() {
            this.setupDarkMode();
            this.setupEventListeners();
            this.initializeComponents();
            this.setupResponsive();
        },

        /**
         * Dark Mode Management
         */
        setupDarkMode() {
            const toggle = document.querySelector('.catlaq-theme-toggle');
            if (!toggle) return;

            // Load saved theme preference
            const savedTheme = localStorage.getItem('catlaq-theme') || 'light';
            this.setTheme(savedTheme);

            // Toggle event
            toggle.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                this.setTheme(newTheme);
                localStorage.setItem('catlaq-theme', newTheme);
            });

            // Detect system preference
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                if (!localStorage.getItem('catlaq-theme')) {
                    this.setTheme('dark');
                }
            }
        },

        /**
         * Set theme
         */
        setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.body.setAttribute('data-theme', theme);
            
            const toggle = document.querySelector('.catlaq-theme-toggle');
            if (toggle) {
                toggle.textContent = theme === 'light' ? '🌙' : '☀️';
            }
        },

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', (e) => {
                    const target = document.querySelector(anchor.getAttribute('href'));
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });

            // Plan card interactions
            this.setupPlanCards();

            // Auth portal interactions
            this.setupAuthPortal();

            // Dashboard interactions
            this.setupDashboard();

            // Engagement feed interactions
            this.setupEngagementFeed();
        },

        /**
         * Setup plan cards
         */
        setupPlanCards() {
            const cards = document.querySelectorAll('.catlaq-plan-card__cta');
            cards.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const planSlug = e.target.getAttribute('data-plan');
                    console.log('Plan selected:', planSlug);
                    // Trigger custom event for plugins to handle
                    window.dispatchEvent(new CustomEvent('catlaq-plan-selected', {
                        detail: { plan: planSlug }
                    }));
                });
            });
        },

        /**
         * Setup auth portal
         */
        setupAuthPortal() {
            const registerForm = document.querySelector('.catlaq-register-form');
            if (registerForm) {
                registerForm.addEventListener('submit', (e) => {
                    // Form validation can be added here
                    console.log('Registration form submitted');
                });
            }
        },

        /**
         * Setup dashboard
         */
        setupDashboard() {
            // Menu active state
            const menuLinks = document.querySelectorAll('.catlaq-dashboard__menu-link');
            menuLinks.forEach(link => {
                if (link.href === window.location.href) {
                    link.classList.add('active');
                }
            });

            // Sidebar toggle on mobile
            const sidebarToggle = document.querySelector('.catlaq-dashboard__sidebar-toggle');
            const sidebar = document.querySelector('.catlaq-dashboard__sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
            }
        },

        /**
         * Setup engagement feed
         */
        setupEngagementFeed() {
            const posts = document.querySelectorAll('.catlaq-post');
            posts.forEach(post => {
                // Like button
                const likeBtn = post.querySelector('[data-action="like"]');
                if (likeBtn) {
                    likeBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.toggleLike(likeBtn);
                    });
                }

                // Comment button
                const commentBtn = post.querySelector('[data-action="comment"]');
                if (commentBtn) {
                    commentBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.toggleCommentForm(post);
                    });
                }

                // Share button
                const shareBtn = post.querySelector('[data-action="share"]');
                if (shareBtn) {
                    shareBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.sharePost(post);
                    });
                }
            });
        },

        /**
         * Toggle like
         */
        toggleLike(btn) {
            btn.classList.toggle('active');
            const icon = btn.querySelector('.catlaq-post__action-icon');
            if (icon) {
                icon.textContent = btn.classList.contains('active') ? '❤️' : '🤍';
            }
        },

        /**
         * Toggle comment form
         */
        toggleCommentForm(post) {
            let form = post.querySelector('.catlaq-comment-form');
            if (form) {
                form.style.display = form.style.display === 'none' ? 'flex' : 'none';
            }
        },

        /**
         * Share post
         */
        sharePost(post) {
            const postUrl = post.getAttribute('data-url') || window.location.href;
            if (navigator.share) {
                navigator.share({
                    title: 'Catlaq Post',
                    url: postUrl
                }).catch(err => console.log('Error sharing:', err));
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(postUrl).then(() => {
                    this.showNotification('Link copied to clipboard', 'success');
                });
            }
        },

        /**
         * Initialize components
         */
        initializeComponents() {
            // Initialize tooltips
            this.initializeTooltips();

            // Initialize modals
            this.initializeModals();

            // Initialize forms
            this.initializeForms();
        },

        /**
         * Initialize tooltips
         */
        initializeTooltips() {
            const tooltips = document.querySelectorAll('[data-tooltip]');
            tooltips.forEach(el => {
                el.addEventListener('mouseenter', (e) => {
                    const text = e.target.getAttribute('data-tooltip');
                    const tooltip = document.createElement('div');
                    tooltip.className = 'catlaq-tooltip';
                    tooltip.textContent = text;
                    tooltip.style.position = 'absolute';
                    tooltip.style.background = '#333';
                    tooltip.style.color = '#fff';
                    tooltip.style.padding = '0.5rem 0.75rem';
                    tooltip.style.borderRadius = '0.375rem';
                    tooltip.style.fontSize = '0.85rem';
                    tooltip.style.pointerEvents = 'none';
                    tooltip.style.zIndex = '9999';
                    document.body.appendChild(tooltip);

                    const rect = e.target.getBoundingClientRect();
                    tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
                    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';

                    e.target.addEventListener('mouseleave', () => tooltip.remove());
                });
            });
        },

        /**
         * Initialize modals
         */
        initializeModals() {
            const modalTriggers = document.querySelectorAll('[data-modal]');
            modalTriggers.forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    const modalId = trigger.getAttribute('data-modal');
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.style.display = 'flex';
                        modal.classList.add('active');
                    }
                });
            });

            // Close modals
            const closeButtons = document.querySelectorAll('[data-modal-close]');
            closeButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const modal = e.target.closest('.catlaq-modal');
                    if (modal) {
                        modal.style.display = 'none';
                        modal.classList.remove('active');
                    }
                });
            });

            // Close on background click
            document.querySelectorAll('.catlaq-modal').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                        modal.classList.remove('active');
                    }
                });
            });
        },

        /**
         * Initialize forms
         */
        initializeForms() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', (e) => {
                    // Add your form validation here
                    console.log('Form submitted:', form.id);
                });
            });
        },

        /**
         * Setup responsive behavior
         */
        setupResponsive() {
            const mediaQuery = window.matchMedia('(max-width: 768px)');
            
            const handleResponsive = (e) => {
                if (e.matches) {
                    this.setupMobileMenu();
                }
            };

            mediaQuery.addListener(handleResponsive);
            handleResponsive(mediaQuery);
        },

        /**
         * Setup mobile menu
         */
        setupMobileMenu() {
            const sidebar = document.querySelector('.catlaq-dashboard__sidebar');
            if (sidebar) {
                // Add mobile-specific behavior
                document.addEventListener('click', (e) => {
                    if (!sidebar.contains(e.target) && !e.target.classList.contains('catlaq-dashboard__sidebar-toggle')) {
                        sidebar.classList.remove('open');
                    }
                });
            }
        },

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `catlaq-notification ${type}`;
            notification.innerHTML = `
                <div class="catlaq-notification__icon">${this.getNotificationIcon(type)}</div>
                <div class="catlaq-notification__content">
                    <p class="catlaq-notification__message">${message}</p>
                </div>
            `;
            
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        },

        /**
         * Get notification icon
         */
        getNotificationIcon(type) {
            const icons = {
                success: '✓',
                error: '✕',
                warning: '!',
                info: 'ℹ'
            };
            return icons[type] || icons.info;
        },

        /**
         * AJAX helper
         */
        ajax(url, options = {}) {
            const defaults = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            const config = { ...defaults, ...options };

            if (config.data) {
                config.body = JSON.stringify(config.data);
            }

            return fetch(url, config)
                .then(res => res.json())
                .catch(err => {
                    console.error('AJAX Error:', err);
                    this.showNotification('An error occurred', 'error');
                });
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => CatlaqTheme.init());
    } else {
        CatlaqTheme.init();
    }

    // Export to global scope for plugin access
    window.CatlaqTheme = CatlaqTheme;
})();
