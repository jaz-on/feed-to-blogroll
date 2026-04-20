/**
 * Feed to Blogroll - Frontend JavaScript
 * Handles frontend interactions and OPML export
 */

(function($) {
	'use strict';

	// Frontend functionality
	const FeedToBlogrollFrontend = {
		
		/**
		 * Initialize frontend functionality
		 */
		init: function() {
			this.bindEvents();
			this.initLazyLoading();
			this.initSmoothScrolling();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Export OPML button
			$(document).on('click', '.export-opml-button', this.handleOPMLExport.bind(this));
			
			// Blog card interactions
			$(document).on('click', '.blog-card', this.handleCardClick.bind(this));
			
			// External link handling
			$(document).on('click', '.blog-actions a[target="_blank"]', this.handleExternalLink.bind(this));
		},

		/**
		 * Initialize lazy loading for images
		 */
		initLazyLoading: function() {
			// Check if Intersection Observer is supported
			if ('IntersectionObserver' in window) {
				const imageObserver = new IntersectionObserver((entries, observer) => {
					entries.forEach(entry => {
						if (entry.isIntersecting) {
							const img = entry.target;
							img.src = img.dataset.src;
							img.classList.remove('lazy');
							imageObserver.unobserve(img);
						}
					});
				});

				// Observe all lazy images
				document.querySelectorAll('img[data-src]').forEach(img => {
					imageObserver.observe(img);
				});
			}
		},

		/**
		 * Initialize smooth scrolling for anchor links
		 */
		initSmoothScrolling: function() {
			$('.feed-to-blogroll-container').on('click', 'a[href^="#"]', function(e) {
				e.preventDefault();
				
				const target = $(this.getAttribute('href'));
				if (target.length) {
					$('html, body').animate({
						scrollTop: target.offset().top - 100
					}, 600);
				}
			});
		},

		/**
		 * Handle OPML export
		 */
		handleOPMLExport: function(e) {
			e.preventDefault();
			
			const $button = $(e.target);
			const restUrl = (window.feedToBlogrollFrontend && feedToBlogrollFrontend.restUrl) ? feedToBlogrollFrontend.restUrl : '';
			
			// Show loading state
			var exportingText = (window.feedToBlogrollFrontend && feedToBlogrollFrontend.strings && feedToBlogrollFrontend.strings.exporting) ? feedToBlogrollFrontend.strings.exporting : 'Exporting...';
			$button.addClass('loading').text(exportingText);
			
			if (!restUrl) {
				var missingUrlMsg = (window.feedToBlogrollFrontend && feedToBlogrollFrontend.strings && feedToBlogrollFrontend.strings.error) ? feedToBlogrollFrontend.strings.error : 'Export failed. Please try again.';
				FeedToBlogrollFrontend.showMessage(missingUrlMsg, 'error');
				var labelMissing = (window.feedToBlogrollFrontend && feedToBlogrollFrontend.strings && feedToBlogrollFrontend.strings.exportLabel) ? feedToBlogrollFrontend.strings.exportLabel : 'Export OPML';
				$button.removeClass('loading').html('<span class="dashicons dashicons-download" aria-hidden="true"></span> ' + labelMissing);
				return;
			}

			fetch(restUrl, { credentials: 'same-origin' })
				.then(function(res) {
					if (!res.ok) {
						throw new Error('HTTP ' + res.status);
					}
					return res.json();
				})
				.then(function(data) {
					if (data && data.opml && data.filename) {
						FeedToBlogrollFrontend.downloadOPML(data.opml, data.filename);
						var exportedMsg = (window.feedToBlogrollFrontend && feedToBlogrollFrontend.strings && feedToBlogrollFrontend.strings.exported) ? feedToBlogrollFrontend.strings.exported : 'OPML file exported successfully!';
						FeedToBlogrollFrontend.showMessage(exportedMsg, 'success');
					} else {
						throw new Error('Invalid response');
					}
				})
				.catch(function() {
					var errMsg = (window.feedToBlogrollFrontend && feedToBlogrollFrontend.strings && feedToBlogrollFrontend.strings.error) ? feedToBlogrollFrontend.strings.error : 'Export failed. Please try again.';
					FeedToBlogrollFrontend.showMessage(errMsg, 'error');
				})
				.finally(function() {
					var label = (window.feedToBlogrollFrontend && feedToBlogrollFrontend.strings && feedToBlogrollFrontend.strings.exportLabel) ? feedToBlogrollFrontend.strings.exportLabel : 'Export OPML';
					$button.removeClass('loading').html('<span class="dashicons dashicons-download" aria-hidden="true"></span> ' + label);
				});
		},

		/**
		 * Handle blog card clicks
		 */
		handleCardClick: function(e) {
			// Don't trigger if clicking on links or buttons
			if ($(e.target).closest('a, button').length) {
				return;
			}
			
			// Find the site URL link and open it
			const $card = $(e.currentTarget);
			const $siteLink = $card.find('.blog-actions .blog-link');
			
			if ($siteLink.length && $siteLink.attr('href')) {
				window.open($siteLink.attr('href'), '_blank', 'noopener,noreferrer');
			}
		},

		/**
		 * Handle external link clicks
		 */
		handleExternalLink: function(e) {
			// Optional hook for analytics (no console output in production)
		},

		/**
		 * Download OPML file
		 */
		downloadOPML: function(opmlContent, filename) {
			const blob = new Blob([opmlContent], { type: 'application/xml' });
			const url = window.URL.createObjectURL(blob);
			
			const $link = $('<a>', {
				href: url,
				download: filename,
				style: 'display: none'
			});
			
			$('body').append($link);
			$link[0].click();
			$('body').remove($link);
			
			// Clean up
			window.URL.revokeObjectURL(url);
		},

		/**
		 * Show message to user
		 */
		showMessage: function(message, type) {
			// Create message element
			const $message = $('<div class="feed-to-blogroll-message ' + type + '">' + message + '</div>');
			
			// Add to page
			$('body').append($message);
			
			// Position message
			$message.css({
				position: 'fixed',
				top: '20px',
				right: '20px',
				zIndex: 9999,
				padding: '1rem 1.5rem',
				borderRadius: '6px',
				color: 'white',
				fontWeight: '500',
				boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
				maxWidth: '300px',
				wordWrap: 'break-word'
			});
			
			// Style based on type
			switch (type) {
				case 'success':
					$message.css('background', '#28a745');
					break;
				case 'error':
					$message.css('background', '#dc3545');
					break;
				case 'warning':
					$message.css('background', '#ffc107');
					$message.css('color', '#212529');
					break;
				case 'info':
					$message.css('background', '#17a2b8');
					break;
				default:
					$message.css('background', '#6c757d');
			}
			
			// Animate in
			$message.hide().fadeIn(300);
			
			// Auto-remove after 5 seconds
			setTimeout(function() {
				$message.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Initialize responsive behavior
		 */
		initResponsive: function() {
			// Handle window resize
			$(window).on('resize', this.debounce(this.handleResize.bind(this), 250));
			
			// Initial call
			this.handleResize();
		},

		/**
		 * Handle window resize
		 */
		handleResize: function() {
			const width = $(window).width();
			
			// Adjust grid columns based on screen size
			if (width < 768) {
				$('.blogroll-grid').removeClass('columns-2 columns-3 columns-4 columns-5 columns-6').addClass('columns-1');
			} else if (width < 1024) {
				$('.blogroll-grid').removeClass('columns-1 columns-3 columns-4 columns-5 columns-6').addClass('columns-2');
			} else if (width < 1200) {
				$('.blogroll-grid').removeClass('columns-1 columns-2 columns-4 columns-5 columns-6').addClass('columns-3');
			} else {
				$('.blogroll-grid').removeClass('columns-1 columns-2 columns-3 columns-5 columns-6').addClass('columns-4');
			}
		},

		/**
		 * Debounce function for performance
		 */
		debounce: function(func, wait) {
			let timeout;
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout);
					func(...args);
				};
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
			};
		},

		/**
		 * Initialize accessibility features
		 */
		initAccessibility: function() {
			// Add keyboard navigation
			$('.blog-card').attr('tabindex', '0');
			
			// Handle keyboard events
			$(document).on('keydown', '.blog-card', function(e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					$(this).click();
				}
			});
			
			// Add ARIA labels
			$('.blog-actions .button').each(function() {
				const $button = $(this);
				const action = $button.text().toLowerCase();
				const blogTitle = $button.closest('.blog-card').find('.blog-title').text();
				
				$button.attr('aria-label', action + ' ' + blogTitle);
			});
		},

		/**
		 * Initialize performance optimizations
		 */
		initPerformance: function() {
			// Add loading states for images
			$('.blog-card img').on('load', function() {
				$(this).addClass('loaded');
			});
			
			// Preload critical resources
			this.preloadCriticalResources();
		},

		/**
		 * Preload critical resources
		 */
		preloadCriticalResources: function() {
			// Preload CSS and JS files if needed
			const criticalResources = [
				// Add any critical resources here
			];
			
			criticalResources.forEach(resource => {
				const link = document.createElement('link');
				link.rel = 'preload';
				link.href = resource;
				link.as = resource.endsWith('.css') ? 'style' : 'script';
				document.head.appendChild(link);
			});
		},

		/**
		 * Handle errors gracefully
		 */
		handleError: function(error, context) {
			console.error('Feed to Blogroll Frontend Error:', error, context);
			
			this.showMessage(
				'An unexpected error occurred. Please try again.',
				'error'
			);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		FeedToBlogrollFrontend.init();
		FeedToBlogrollFrontend.initResponsive();
		FeedToBlogrollFrontend.initAccessibility();
		FeedToBlogrollFrontend.initPerformance();
	});

	// Expose to global scope for debugging
	window.FeedToBlogrollFrontend = FeedToBlogrollFrontend;

})(jQuery);
