/**
 * Feed Blogroll - Admin JavaScript
 * Handles admin interface interactions and AJAX requests
 * Uses WordPress native admin patterns
 */

(function($) {
	'use strict';

	// Admin functionality
	const FeedBlogrollAdmin = {
		
		/**
		 * Initialize admin functionality
		 */
		init: function() {
			this.bindEvents();
			this.initTooltips();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Test connection button
			$(document).on('click', '#test-connection', this.testConnection.bind(this));
			
			// Manual sync button
			$(document).on('click', '#manual-sync', this.manualSync.bind(this));
			
			// Export OPML button
			$(document).on('click', '#export-opml', this.exportOPML.bind(this));
			
			// Settings form submission
			$(document).on('submit', '.feed-blogroll-settings form', this.handleSettingsSubmit.bind(this));
		},

		/**
		 * Initialize tooltips and help text
		 */
		initTooltips: function() {
			// Add help text for form fields
			$('.form-table th').each(function() {
				const $th = $(this);
				const $td = $th.next('td');
				const helpText = $td.find('.description').text();
				
				if (helpText) {
					$th.attr('title', helpText);
					$th.css('cursor', 'help');
				}
			});
		},

		/**
		 * Test Feedbin API connection
		 */
		testConnection: function(e) {
			e.preventDefault();
			
			const $button = $(e.target);
			const originalText = $button.text();
			
			// Show loading state
			$button.addClass('loading').text(feedBlogrollAdmin.strings.testing);
			
			// Make AJAX request
			$.ajax({
				url: feedBlogrollAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'feed_blogroll_test_connection',
					nonce: feedBlogrollAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						FeedBlogrollAdmin.showMessage(
							response.data.message + ' (' + response.data.count + ' subscriptions found)',
							'success'
						);
					} else {
						FeedBlogrollAdmin.showMessage(
							response.data || 'Connection test failed',
							'error'
						);
					}
				},
				error: function() {
					FeedBlogrollAdmin.showMessage(
						'Network error occurred',
						'error'
					);
				},
				complete: function() {
					// Reset button state
					$button.removeClass('loading').text(originalText);
				}
			});
		},

		/**
		 * Perform manual synchronization
		 */
		manualSync: function(e) {
			e.preventDefault();
			
			const $button = $(e.target);
			const originalText = $button.text();
			
			// Show loading state
			$button.addClass('loading').text(feedBlogrollAdmin.strings.syncing);
			
			// Show progress indicator
			$('#sync-progress').show();
			
			// Make AJAX request
			$.ajax({
				url: feedBlogrollAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'feed_blogroll_manual_sync',
					nonce: feedBlogrollAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						FeedBlogrollAdmin.showMessage(
							response.data.message,
							'success'
						);
						
						// Refresh page after successful sync to update stats
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						FeedBlogrollAdmin.showMessage(
							response.data || 'Synchronization failed',
							'error'
						);
					}
				},
				error: function() {
					FeedBlogrollAdmin.showMessage(
						'Network error occurred',
						'error'
					);
				},
				complete: function() {
					// Reset button state
					$button.removeClass('loading').text(originalText);
					$('#sync-progress').hide();
				}
			});
		},

		/**
		 * Export OPML file
		 */
		exportOPML: function(e) {
			e.preventDefault();
			
			const $button = $(e.target);
			const originalText = $button.text();
			
			// Show loading state
			$button.addClass('loading').text(feedBlogrollAdmin.strings.exporting);
			
			// Make AJAX request
			$.ajax({
				url: feedBlogrollAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'feed_blogroll_export_opml',
					nonce: feedBlogrollAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						// Create and download OPML file
						FeedBlogrollAdmin.downloadFile(
							response.data.opml,
							response.data.filename,
							'application/xml'
						);
						
						FeedBlogrollAdmin.showMessage(
							'OPML file exported successfully',
							'success'
						);
					} else {
						FeedBlogrollAdmin.showMessage(
							response.data || 'Export failed',
							'error'
						);
					}
				},
				error: function() {
					FeedBlogrollAdmin.showMessage(
						'Network error occurred',
						'error'
					);
				},
				complete: function() {
					// Reset button state
					$button.removeClass('loading').text(originalText);
				}
			});
		},

		/**
		 * Handle settings form submission
		 */
		handleSettingsSubmit: function(e) {
			// Add loading state to submit button
			const $submitButton = $(e.target).find('input[type="submit"]');
			const originalText = $submitButton.val();
			
			$submitButton.val('Saving...').prop('disabled', true);
			
			// Form will submit normally, but we can show feedback
			setTimeout(function() {
				$submitButton.val(originalText).prop('disabled', false);
			}, 2000);
		},

		/**
		 * Show message to user using WordPress admin notices
		 */
		showMessage: function(message, type) {
			const $messagesContainer = $('#feed-blogroll-messages');
			const noticeClass = 'notice notice-' + type + ' is-dismissible';

			const $message = $('<div/>', { 'class': noticeClass });
			const $btn = $('<button/>', { type: 'button', 'class': 'notice-dismiss' })
				.append($('<span/>', { 'class': 'screen-reader-text', text: 'Dismiss this notice.' }));
			const $p = $('<p/>').text(String(message));
			$message.append($btn).append($p);
			$messagesContainer.append($message);

			// Dismiss handler
			$message.find('.notice-dismiss').on('click', function() {
				$message.fadeOut(300, function() { $(this).remove(); });
			});

			// Auto-remove after 8s
			setTimeout(function() {
				if ($message.is(':visible')) {
					$message.fadeOut(300, function() { $(this).remove(); });
				}
			}, 8000);

			// Scroll to message
			$('html, body').animate({ scrollTop: $message.offset().top - 100 }, 300);

			// Announce for screen readers
			try { if (window.wp && wp.a11y && typeof wp.a11y.speak === 'function') { wp.a11y.speak(String(message), 'polite'); } } catch (e) {}
		},

		/**
		 * Download file from data
		 */
		downloadFile: function(data, filename, mimeType) {
			const blob = new Blob([data], { type: mimeType });
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
		 * Handle errors gracefully
		 */
		handleError: function(error, context) {
			console.error('Feed Blogroll Admin Error:', error, context);
			
			this.showMessage(
				'An unexpected error occurred. Please check the console for details.',
				'error'
			);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		FeedBlogrollAdmin.init();
	});

	// Expose to global scope for debugging
	window.FeedBlogrollAdmin = FeedBlogrollAdmin;

})(jQuery);
