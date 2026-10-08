/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace = window.I18nly = window.I18nly || {};
	var __        = namespace.i18n.__;
	var _n        = namespace.i18n._n;
	var sprintf   = namespace.i18n.sprintf;

	/**
	 * Removes duplicates and empty values from a list of rows.
	 *
	 * @param {Array} rows Rows.
	 * @return {Array}
	 */
	function uniqueRows( rows ) {
		return ( rows || [] ).filter(
			function ( row ) {
				return !! row;
			}
		).filter(
			function ( row, index, list ) {
				return list.indexOf( row ) === index;
			}
		);
	}

	/**
	 * Remembers the rows edited since the filters were last applied and warns about those that no
	 * longer match the active filters, so that they do not vanish under the user's hands.
	 */
	class ModifiedRowsTracker {
		/**
		 * @param {?Element} filtersContainer Element after which the notice is displayed.
		 * @param {Function} applyFilters Callback re-applying the filters; receives the apply options.
		 */
		constructor( filtersContainer, applyFilters ) {
			this.filtersContainer = filtersContainer;
			this.applyFilters     = applyFilters;

			this.reset();
		}

		/**
		 * Forgets every tracked row and the notice state.
		 *
		 * @return {void}
		 */
		reset() {
			this.notice          = null;
			this.tracked         = [];
			this.outOfFilter     = [];
			this.showOnly        = false;
			this.suppressNotice  = false;
		}

		/**
		 * Tracks rows that were just edited.
		 *
		 * @param {Array} rows Edited rows.
		 * @return {boolean} Whether at least one row was given.
		 */
		track( rows ) {
			var changedRows = uniqueRows( rows );

			if ( 0 === changedRows.length ) {
				return false;
			}

			this.suppressNotice = false;
			this.tracked        = uniqueRows( this.tracked.concat( changedRows ) );

			return true;
		}

		/**
		 * Tells whether a row is tracked and no longer matches the filters.
		 *
		 * @param {Object} row Row.
		 * @return {boolean}
		 */
		isOutOfFilter( row ) {
			return this.outOfFilter.indexOf( row ) !== -1;
		}

		/**
		 * Recomputes the tracked rows that fall out of the filters and updates the notice.
		 *
		 * @param {Array}  currentRows Rows currently in the table.
		 * @param {Object} selection Filter selection.
		 * @return {void}
		 */
		refresh( currentRows, selection ) {
			if ( this.suppressNotice ) {
				this.hideNotice();
				return;
			}

			this.tracked = uniqueRows( this.tracked ).filter(
				function ( row ) {
					return currentRows.indexOf( row ) !== -1;
				}
			);

			this.outOfFilter = this.tracked.filter(
				function ( row ) {
					return ! row.matches( selection );
				}
			);

			if ( 0 === this.outOfFilter.length ) {
				this.showOnly = false;
				this.hideNotice();
				return;
			}

			this.showNotice( this.outOfFilter.length );
		}

		/**
		 * Binds the notice controls.
		 *
		 * @param {?Element} noticeNode Notice element.
		 * @return {void}
		 */
		bindNoticeEvents( noticeNode ) {
			var self              = this;
			var noticeToggle      = noticeNode ? noticeNode.querySelector( '.i18nly-filter-feedback-only-modified' ) : null;
			var noticeApplyButton = noticeNode ? noticeNode.querySelector( '.i18nly-filter-feedback-apply' ) : null;

			if ( noticeToggle ) {
				noticeToggle.onchange = function () {
					self.showOnly = !! noticeToggle.checked;
					self.applyFilters();
				};
			}

			if ( noticeApplyButton ) {
				noticeApplyButton.onclick = function ( event ) {
					if ( event ) {
						event.preventDefault();
						event.stopPropagation();
					}

					self.showOnly       = false;
					self.tracked        = [];
					self.outOfFilter    = [];
					self.suppressNotice = true;
					self.hideNotice();
					self.applyFilters( { strict: true } );
				};
			}
		}

		/**
		 * Returns the notice element, creating it when needed.
		 *
		 * @return {?Element}
		 */
		getOrCreateNotice() {
			var existingNotices;
			var textNode;
			var toggleLabel;
			var toggleInput;
			var applyButton;

			if ( this.notice ) {
				this.bindNoticeEvents( this.notice );
				return this.notice;
			}

			if ( ! this.filtersContainer || ! this.filtersContainer.parentNode ) {
				return null;
			}

			existingNotices = Array.prototype.slice.call( this.filtersContainer.parentNode.querySelectorAll( '.i18nly-filter-feedback' ) );
			if ( existingNotices.length > 0 ) {
				this.notice = existingNotices[0];

				existingNotices.slice( 1 ).forEach(
					function ( node ) {
						if ( node.parentNode ) {
							node.parentNode.removeChild( node );
						}
					}
				);

				this.bindNoticeEvents( this.notice );

				return this.notice;
			}

			this.notice           = window.document.createElement( 'div' );
			this.notice.className = 'i18nly-filter-feedback';
			this.notice.hidden    = true;

			textNode           = window.document.createElement( 'span' );
			textNode.className = 'i18nly-filter-feedback-text';
			this.notice.appendChild( textNode );

			toggleLabel           = window.document.createElement( 'label' );
			toggleLabel.className = 'i18nly-filter-feedback-toggle';
			toggleInput           = window.document.createElement( 'input' );
			toggleInput.type      = 'checkbox';
			toggleInput.className = 'i18nly-filter-feedback-only-modified';
			toggleLabel.appendChild( toggleInput );
			toggleLabel.appendChild( window.document.createTextNode( ' ' + __( 'Show only these modified rows', 'i18nly' ) ) );
			this.notice.appendChild( toggleLabel );

			applyButton             = window.document.createElement( 'button' );
			applyButton.type        = 'button';
			applyButton.className   = 'button button-small i18nly-filter-feedback-apply';
			applyButton.textContent = __( 'Apply filters and close', 'i18nly' );
			this.notice.appendChild( applyButton );

			this.bindNoticeEvents( this.notice );

			this.filtersContainer.parentNode.insertBefore( this.notice, this.filtersContainer.nextSibling );

			return this.notice;
		}

		/**
		 * Removes every notice from the page.
		 *
		 * @return {void}
		 */
		hideNotice() {
			var notices = Array.prototype.slice.call( window.document.querySelectorAll( '.i18nly-filter-feedback' ) );

			notices.forEach(
				function ( notice ) {
					notice.hidden = true;
					if ( notice.parentNode ) {
						notice.parentNode.removeChild( notice );
					}
				}
			);

			this.notice = null;
		}

		/**
		 * Displays the notice with the number of rows that left the filters.
		 *
		 * @param {number} hiddenRowsCount Number of rows.
		 * @return {void}
		 */
		showNotice( hiddenRowsCount ) {
			var notice = this.getOrCreateNotice();
			var textNode;
			var toggle;

			if ( ! notice ) {
				return;
			}

			textNode = notice.querySelector( '.i18nly-filter-feedback-text' );
			toggle   = notice.querySelector( '.i18nly-filter-feedback-only-modified' );

			if ( textNode ) {
				textNode.textContent = sprintf(
					_n(
						'%d modified row no longer matches active filters.',
						'%d modified rows no longer match active filters.',
						hiddenRowsCount,
						'i18nly'
					),
					hiddenRowsCount
				);
			}

			if ( toggle ) {
				toggle.checked = this.showOnly;
			}

			notice.hidden = false;
		}
	}

	namespace.ModifiedRowsTracker = ModifiedRowsTracker;
} )( window );
