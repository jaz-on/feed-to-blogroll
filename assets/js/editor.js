/**
 * Feed to Blogroll - Editor Script
 * Simple editor script for the blogroll block
 */

(function() {
	'use strict';

	// Register the block
	wp.blocks.registerBlockType('feed-to-blogroll/blogroll', {
		title: wp.i18n.__('Blogroll', 'feed-to-blogroll'),
		description: wp.i18n.__('Display a blogroll of RSS feeds.', 'feed-to-blogroll'),
		category: 'widgets',
		icon: 'rss',
		supports: {
			html: false,
			align: ['wide', 'full']
		},
		attributes: {
			category: {
				type: 'string',
				default: ''
			},
			limit: {
				type: 'number',
				default: -1
			},
			columns: {
				type: 'number',
				default: 3
			},
			showExport: {
				type: 'boolean',
				default: true
			}
		},
		edit: function(props) {
			var attributes = props.attributes;
			
			return wp.element.createElement('div', {
				className: 'feed-to-blogroll-editor-preview'
			}, [
				wp.element.createElement('div', {
					className: 'feed-to-blogroll-editor-header'
				}, wp.element.createElement('h3', {}, wp.i18n.__('Blogroll', 'feed-to-blogroll'))),
				wp.element.createElement('div', {
					className: 'feed-to-blogroll-editor-content'
				}, wp.element.createElement('p', {}, wp.i18n.__('Blogroll content will be displayed here.', 'feed-to-blogroll'))),
				wp.element.createElement('div', {
					className: 'feed-to-blogroll-editor-settings'
				}, [
					wp.element.createElement('p', {}, [
						wp.element.createElement('strong', {}, wp.i18n.__('Settings:', 'feed-to-blogroll')),
						' ',
						wp.i18n.sprintf(
							wp.i18n.__('Category: %s, Limit: %s, Columns: %s', 'feed-to-blogroll'),
							attributes.category || wp.i18n.__('All', 'feed-to-blogroll'),
							attributes.limit === -1 ? wp.i18n.__('All', 'feed-to-blogroll') : attributes.limit,
							attributes.columns
						)
					])
				])
			]);
		},
		save: function() {
			// Dynamic block - content is rendered server-side
			return null;
		}
	});

})();
