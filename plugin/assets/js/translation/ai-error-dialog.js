/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace = window.I18nly = window.I18nly || {};

	/**
	 * Displays AI translation errors in a modal dialog.
	 */
	class AiErrorDialog {
		/**
		 * Appends a link to the settings page to a container.
		 *
		 * @param {?Element} container Container.
		 * @param {?Object}  linkMeta Link metadata (url, label).
		 * @return {void}
		 */
		static appendSettingsLink( container, linkMeta ) {
			var paragraph;
			var anchor;

			if ( ! container || ! linkMeta || ! linkMeta.url ) {
				return;
			}

			paragraph           = window.document.createElement( 'p' );
			paragraph.className = 'i18nly-ai-error-help';

			anchor             = window.document.createElement( 'a' );
			anchor.href        = linkMeta.url;
			anchor.textContent = String( linkMeta.label || 'Settings > Translations' );
			paragraph.appendChild( anchor );

			container.appendChild( paragraph );
		}

		/**
		 * Shows (or updates) the error dialog.
		 *
		 * @param {string}  message Error message.
		 * @param {?Object} settingsLinkMeta Optional settings link metadata.
		 * @return {boolean} Whether the dialog was shown.
		 */
		static show( message, settingsLinkMeta ) {
			var normalizedMessage = String( message || '' ).trim();
			var modal             = window.document.getElementById( 'i18nly-ai-error-modal' );
			var overlay;
			var content;
			var title;
			var messageNode;
			var help;
			var actions;
			var closeButton;

			if ( '' === normalizedMessage ) {
				return false;
			}

			if ( ! modal ) {
				modal    = window.document.createElement( 'div' );
				modal.id = 'i18nly-ai-error-modal';
				modal.setAttribute( 'role', 'dialog' );
				modal.setAttribute( 'aria-modal', 'true' );
				modal.setAttribute( 'aria-labelledby', 'i18nly-ai-error-title' );

				overlay           = window.document.createElement( 'div' );
				overlay.className = 'i18nly-progress-overlay';

				content           = window.document.createElement( 'div' );
				content.className = 'i18nly-progress-content';

				title             = window.document.createElement( 'h2' );
				title.id          = 'i18nly-ai-error-title';
				title.className   = 'i18nly-progress-title';
				title.textContent = 'AI Translation Error';

				messageNode           = window.document.createElement( 'p' );
				messageNode.id        = 'i18nly-ai-error-message';
				messageNode.className = 'i18nly-progress-text';

				help           = window.document.createElement( 'div' );
				help.id        = 'i18nly-ai-error-help';
				help.className = 'i18nly-progress-help';

				actions           = window.document.createElement( 'div' );
				actions.className = 'i18nly-progress-actions';

				closeButton             = window.document.createElement( 'button' );
				closeButton.type        = 'button';
				closeButton.className   = 'button button-primary';
				closeButton.textContent = 'Close';
				closeButton.addEventListener(
					'click',
					function () {
						modal.remove();
					}
				);

				actions.appendChild( closeButton );
				content.appendChild( title );
				content.appendChild( messageNode );
				content.appendChild( help );
				content.appendChild( actions );
				overlay.appendChild( content );
				modal.appendChild( overlay );
				window.document.body.appendChild( modal );
			}

			messageNode = window.document.getElementById( 'i18nly-ai-error-message' );
			help        = window.document.getElementById( 'i18nly-ai-error-help' );

			if ( messageNode ) {
				messageNode.textContent = normalizedMessage;
			}

			if ( help ) {
				help.innerHTML = '';
				AiErrorDialog.appendSettingsLink( help, settingsLinkMeta );
			}

			return true;
		}

		/**
		 * Reports an error, falling back to a browser alert when the dialog cannot be shown.
		 *
		 * @param {string}  message Error message.
		 * @param {?Object} settingsLinkMeta Optional settings link metadata.
		 * @return {void}
		 */
		static notify( message, settingsLinkMeta ) {
			var normalizedMessage = String( message || '' ).trim();

			if ( '' === normalizedMessage ) {
				return;
			}

			if ( ! AiErrorDialog.show( normalizedMessage, settingsLinkMeta ) ) {
				window.alert( normalizedMessage );
			}
		}
	}

	namespace.AiErrorDialog = AiErrorDialog;
} )( window );
