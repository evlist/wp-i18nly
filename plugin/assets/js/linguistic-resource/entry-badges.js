/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace = window.I18nly = window.I18nly || {};

	var QUALITY_STATUS_META = {
		draft: { className: 'i18nly-entry-status--draft', label: 'Draft' },
		suspect: { className: 'i18nly-entry-status--suspect', label: 'Suspect' },
		validated: { className: 'i18nly-entry-status--validated', label: 'Validated' }
	};

	var PROVENANCE_LABELS = {
		ai: 'AI',
		manual: 'Manual'
	};

	/**
	 * Reads and updates the quality and provenance badges displayed next to a target input.
	 *
	 * The badges are linked to their input through the data-for attribute. This class holds no state.
	 */
	class EntryBadges {
		/**
		 * Maps legacy AI review tokens to quality tokens.
		 *
		 * @param {string} token Raw token.
		 * @return {string}
		 */
		static normalizeQualityToken( token ) {
			if ( 'ai_draft_ok' === token || 'draft_ai' === token ) {
				return 'draft';
			}

			if (
				'ai_draft_suspect' === token
				|| 'ai_draft_needs_fix' === token
				|| 'draft_ai_suspect' === token
				|| 'draft_ai_needs_fix' === token
			) {
				return 'suspect';
			}

			return token;
		}

		/**
		 * Applies a quality token to a quality badge; an unknown or empty token makes it a placeholder.
		 *
		 * @param {?Element} badge Quality badge.
		 * @param {string}   token Quality token.
		 * @return {void}
		 */
		static applyQualityState( badge, token ) {
			var normalizedToken = EntryBadges.normalizeQualityToken( token || '' );
			var statusMeta      = QUALITY_STATUS_META[normalizedToken] || null;
			var labelNode;
			var toggle;
			var menu;

			if ( ! badge ) {
				return;
			}

			labelNode = badge.querySelector( '.i18nly-quality-label' );
			toggle    = badge.querySelector( '.i18nly-quality-toggle' );
			menu      = badge.querySelector( '.i18nly-quality-menu' );

			badge.className = statusMeta
				? 'i18nly-entry-status i18nly-entry-status--quality ' + statusMeta.className
				: 'i18nly-entry-status i18nly-entry-status--quality i18nly-entry-status--placeholder';
			badge.setAttribute( 'data-status-token', statusMeta ? normalizedToken : '__empty__' );

			if ( labelNode ) {
				labelNode.textContent = statusMeta ? statusMeta.label : ' ';
			}

			if ( toggle ) {
				toggle.disabled = ! statusMeta;
				toggle.setAttribute( 'aria-expanded', 'false' );
			}

			if ( menu ) {
				menu.hidden = true;
			}
		}

		/**
		 * Returns the quality badge of an input.
		 *
		 * @param {?Element} input Target input.
		 * @return {?Element}
		 */
		static getQualityBadge( input ) {
			var row;
			var inputId;

			if ( ! input ) {
				return null;
			}

			row     = input.closest( 'tr' );
			inputId = input.id || '';

			if ( ! row || '' === inputId ) {
				return null;
			}

			return row.querySelector( '.i18nly-entry-status--quality[data-for="' + inputId + '"]' );
		}

		/**
		 * Returns one provenance badge of an input.
		 *
		 * @param {?Element} input Target input.
		 * @param {string}   token Provenance token (ai or manual).
		 * @return {?Element}
		 */
		static getProvenanceBadge( input, token ) {
			var row;
			var inputId;

			if ( ! input || ! token ) {
				return null;
			}

			row     = input.closest( 'tr' );
			inputId = input.id || '';

			if ( ! row || '' === inputId ) {
				return null;
			}

			return row.querySelector( '.i18nly-entry-status[data-for="' + inputId + '"][data-provenance-token="' + token + '"]' );
		}

		/**
		 * Removes one provenance badge, with the whitespace that follows it.
		 *
		 * @param {?Element} input Target input.
		 * @param {string}   token Provenance token.
		 * @return {void}
		 */
		static removeProvenanceBadge( input, token ) {
			var badge = EntryBadges.getProvenanceBadge( input, token );

			if ( ! badge || ! badge.parentNode ) {
				return;
			}

			if ( badge.nextSibling && badge.nextSibling.nodeType === 3 && '' === String( badge.nextSibling.nodeValue || '' ).trim() ) {
				badge.parentNode.removeChild( badge.nextSibling );
			}

			badge.parentNode.removeChild( badge );
		}

		/**
		 * Adds one provenance badge after the quality badge when it is missing.
		 *
		 * @param {?Element} input Target input.
		 * @param {string}   token Provenance token (ai or manual).
		 * @return {void}
		 */
		static ensureProvenanceBadge( input, token ) {
			var statusBadge;
			var badge;

			if ( ! input ) {
				return;
			}

			if ( EntryBadges.getProvenanceBadge( input, token ) ) {
				return;
			}

			statusBadge = EntryBadges.getQualityBadge( input );
			if ( ! statusBadge || ! statusBadge.parentNode ) {
				return;
			}

			badge           = document.createElement( 'span' );
			badge.className = 'i18nly-entry-status i18nly-entry-status--provenance-' + token;
			badge.setAttribute( 'data-for', input.id || '' );
			badge.setAttribute( 'data-provenance-token', token );
			badge.textContent = PROVENANCE_LABELS[token] || token;

			if ( statusBadge.nextSibling ) {
				statusBadge.parentNode.insertBefore( document.createTextNode( ' ' ), statusBadge.nextSibling );
				statusBadge.parentNode.insertBefore( badge, statusBadge.nextSibling );
			} else {
				statusBadge.parentNode.appendChild( document.createTextNode( ' ' ) );
				statusBadge.parentNode.appendChild( badge );
			}
		}

		/**
		 * Resets the badges of an input after its text changed.
		 *
		 * A non-empty text becomes a draft; an empty text drops the provenance and the status.
		 *
		 * @param {Element} input Target input.
		 * @return {void}
		 */
		static refreshAfterEdit( input ) {
			var badge   = EntryBadges.getQualityBadge( input );
			var hasText = '' !== String( input.value || '' ).trim();

			if ( ! badge ) {
				return;
			}

			if ( hasText ) {
				EntryBadges.applyQualityState( badge, 'draft' );
				return;
			}

			EntryBadges.removeProvenanceBadge( input, 'ai' );
			EntryBadges.removeProvenanceBadge( input, 'manual' );

			EntryBadges.applyQualityState( badge, '' );
		}
	}

	namespace.EntryBadges = EntryBadges;
} )( window );
