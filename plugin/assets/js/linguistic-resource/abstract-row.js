/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace = window.I18nly = window.I18nly || {};
	var EntryBadges = namespace.EntryBadges;

	var INPUT_SELECTOR = '.i18nly-translation-input';

	/**
	 * Base class of one entry row of a linguistic resource table.
	 *
	 * A row wraps a table row element holding one source entry and one target input per plural form.
	 * Concrete resource kinds extend it with their own controls.
	 */
	class AbstractLinguisticResourceRow {
		/**
		 * @param {Element} element Table row element.
		 */
		constructor( element ) {
			this.element = element;
		}

		/**
		 * Returns the resource kind of the row; implemented by concrete rows.
		 *
		 * @return {string}
		 */
		getKind() {
			throw new Error( 'AbstractLinguisticResourceRow::getKind() must be implemented.' );
		}

		/**
		 * Returns the target inputs of the row, one per plural form.
		 *
		 * @return {Element[]}
		 */
		getInputs() {
			return Array.prototype.slice.call( this.element.querySelectorAll( INPUT_SELECTOR ) );
		}

		/**
		 * Returns the selection checkbox of the row.
		 *
		 * @return {?Element}
		 */
		getCheckbox() {
			return this.element.querySelector( '.i18nly-entry-checkbox' );
		}

		/**
		 * Returns the lifecycle status of the source entry (active, obsolete...).
		 *
		 * @return {string}
		 */
		getEntryStatus() {
			return ( this.element.getAttribute( 'data-entry-status' ) || '' ).toLowerCase().trim();
		}

		/**
		 * Returns the quality tokens displayed in the row; untranslated inputs yield "empty".
		 *
		 * @return {string[]}
		 */
		getQualityTokens() {
			return Array.prototype.slice.call( this.element.querySelectorAll( '.i18nly-entry-status--quality' ) ).map(
				function ( badge ) {
					var token = String( badge.getAttribute( 'data-status-token' ) || '' ).toLowerCase().trim();

					if ( '__empty__' === token || '' === token ) {
						return 'empty';
					}

					return token;
				}
			);
		}

		/**
		 * Returns the distinct provenance tokens of the row: ai, manual or none.
		 *
		 * @return {string[]}
		 */
		getProvenanceTokens() {
			var tokens = [];

			this.getInputs().forEach(
				function ( input ) {
					var hasAi     = !! EntryBadges.getProvenanceBadge( input, 'ai' );
					var hasManual = !! EntryBadges.getProvenanceBadge( input, 'manual' );

					if ( hasAi ) {
						tokens.push( 'ai' );
					}

					if ( hasManual ) {
						tokens.push( 'manual' );
					}

					if ( ! hasAi && ! hasManual ) {
						tokens.push( 'none' );
					}
				}
			);

			return tokens.filter(
				function ( token, index, list ) {
					return list.indexOf( token ) === index;
				}
			);
		}

		/**
		 * Tells whether the row matches the text search of a filter selection.
		 *
		 * @param {Object} selection Filter selection.
		 * @return {boolean}
		 */
		matchesSearch( selection ) {
			var searchText         = selection.searchText || '';
			var fields             = selection.searchFields || [];
			var searchInSource     = fields.indexOf( 'source' ) !== -1;
			var searchInTranslated = fields.indexOf( 'translated' ) !== -1;
			var sourceTexts        = [];
			var translatedTexts    = [];

			if ( '' === searchText ) {
				return true;
			}

			if ( ! searchInSource && ! searchInTranslated ) {
				return false;
			}

			this.getInputs().forEach(
				function ( input ) {
					var source     = String( input.getAttribute( 'data-i18nly-source-text' ) || '' ).toLowerCase();
					var translated = String( input.value || '' ).toLowerCase();

					if ( '' !== source ) {
						sourceTexts.push( source );
					}

					if ( '' !== translated ) {
						translatedTexts.push( translated );
					}
				}
			);

			if ( searchInSource && sourceTexts.some( function ( text ) { return text.indexOf( searchText ) !== -1; } ) ) {
				return true;
			}

			if ( searchInTranslated && translatedTexts.some( function ( text ) { return text.indexOf( searchText ) !== -1; } ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Tells whether the row matches every group of a filter selection.
		 *
		 * @param {Object} selection Filter selection.
		 * @return {boolean}
		 */
		matches( selection ) {
			var matchesEntryStatus   = selection.entryStatuses.indexOf( this.getEntryStatus() ) !== -1;
			var matchesQualityStatus = this.getQualityTokens().some(
				function ( token ) {
					return selection.qualityStatuses.indexOf( token ) !== -1;
				}
			);
			var matchesProvenance    = this.getProvenanceTokens().some(
				function ( token ) {
					return selection.provenances.indexOf( token ) !== -1;
				}
			);

			return matchesEntryStatus
				&& matchesQualityStatus
				&& matchesProvenance
				&& this.matchesSearch( selection );
		}

		/**
		 * Shows or hides the row; hiding a row also unselects it.
		 *
		 * @param {boolean} hidden Whether the row must be hidden.
		 * @return {void}
		 */
		setHidden( hidden ) {
			var checkbox = this.getCheckbox();

			this.element.style.display = hidden ? 'none' : '';
			this.element.setAttribute( 'aria-hidden', hidden ? 'true' : 'false' );

			if ( hidden && checkbox ) {
				checkbox.checked = false;
			}
		}

		/**
		 * Copies the source text into every target input and drops their provenance.
		 *
		 * @return {void}
		 */
		copySourceToTarget() {
			this.getInputs().forEach(
				function ( input ) {
					input.value = input.getAttribute( 'data-i18nly-source-text' ) || '';
					EntryBadges.removeProvenanceBadge( input, 'ai' );
					EntryBadges.removeProvenanceBadge( input, 'manual' );
					input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
				}
			);
		}

		/**
		 * Empties every target input.
		 *
		 * @return {void}
		 */
		clearTargets() {
			this.getInputs().forEach(
				function ( input ) {
					input.value = '';
					input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
				}
			);
		}

		/**
		 * Applies a quality token to every translated input; untranslated inputs get no status.
		 *
		 * @param {string} token Quality token.
		 * @return {void}
		 */
		markQuality( token ) {
			this.getInputs().forEach(
				function ( input ) {
					var badge;
					var hasText;

					if ( ! input ) {
						return;
					}

					badge   = EntryBadges.getQualityBadge( input );
					hasText = '' !== String( input.value || '' ).trim();

					if ( ! badge ) {
						return;
					}

					if ( hasText ) {
						EntryBadges.applyQualityState( badge, token );
						return;
					}

					EntryBadges.applyQualityState( badge, '' );
				}
			);
		}

		/**
		 * Adds the values of this row to the payload posted with the form.
		 *
		 * @param {Object} payload Payload indexed by source entry ID.
		 * @return {void}
		 */
		serializeInto( payload ) {
			this.getInputs().forEach(
				function ( input ) {
					var sourceEntryId = input.getAttribute( 'data-i18nly-source-entry-id' );
					var formIndex     = input.getAttribute( 'data-i18nly-form-index' );
					var badge;
					var token;

					if ( ! sourceEntryId ) {
						return;
					}

					if ( ! formIndex ) {
						formIndex = '0';
					}

					if ( ! payload[sourceEntryId] ) {
						payload[sourceEntryId] = { forms: {}, statuses: {}, used_ai: {}, used_manual: {} };
					}

					badge = EntryBadges.getQualityBadge( input );
					token = badge ? ( badge.getAttribute( 'data-status-token' ) || '' ) : '';
					if ( '__empty__' === token ) {
						token = '';
					}

					payload[sourceEntryId].forms[formIndex]       = input.value;
					payload[sourceEntryId].statuses[formIndex]    = token;
					payload[sourceEntryId].used_ai[formIndex]     = EntryBadges.getProvenanceBadge( input, 'ai' ) ? 1 : 0;
					payload[sourceEntryId].used_manual[formIndex] = EntryBadges.getProvenanceBadge( input, 'manual' ) ? 1 : 0;
				}
			);
		}
	}

	namespace.AbstractLinguisticResourceRow = AbstractLinguisticResourceRow;
} )( window );
