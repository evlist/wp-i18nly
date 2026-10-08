/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace           = window.I18nly = window.I18nly || {};
	var AjaxClient          = namespace.AjaxClient;
	var UiText              = namespace.UiText;
	var EntryBadges         = namespace.EntryBadges;
	var EntryFilterBar      = namespace.EntryFilterBar;
	var ModifiedRowsTracker = namespace.ModifiedRowsTracker;

	var INPUT_SELECTOR = '.i18nly-translation-input';

	/**
	 * Base class of the editors of a linguistic resource.
	 *
	 * An editor drives the entries table displayed on a resource edit screen: it loads the table,
	 * keeps the hidden payload field of the post form in sync with the target inputs, filters rows,
	 * handles row selection, bulk actions and quality menus.
	 *
	 * Concrete resource kinds extend it and implement createRow(). They extend getBulkActionHandlers(),
	 * bindRowControls() and onBoot() to add their own behavior.
	 */
	class AbstractLinguisticResourceEditor {
		/**
		 * @param {Object} config Editor configuration localized by PHP.
		 */
		constructor( config ) {
			this.config       = config;
			this.ajax         = new AjaxClient( config );
			this.uiText       = new UiText( config );
			this.rowsByElement = new WeakMap();
			this.filterBar    = null;
			this.tracker      = null;
			this.payloadField = null;
			this.hasSubmitListener    = false;
			this.hasContainerListener = false;
		}

		/**
		 * Creates the row object wrapping a table row element; implemented by concrete editors.
		 *
		 * @param {Element} element Table row element.
		 * @return {Object} AbstractLinguisticResourceRow subclass instance.
		 */
		createRow( element ) { // eslint-disable-line no-unused-vars
			throw new Error( 'AbstractLinguisticResourceEditor::createRow() must be implemented.' );
		}

		/**
		 * Returns the bulk actions this editor handles, indexed by the action ID of the PHP list table.
		 *
		 * A handler receives the selected rows. Returning false tells the editor not to rebuild the
		 * payload afterwards, for actions completing asynchronously.
		 *
		 * @return {Object<string, Function>}
		 */
		getBulkActionHandlers() {
			var self = this;

			return {
				clear_selected_translations: function ( rows ) {
					rows.forEach( function ( row ) { row.clearTargets(); } );
				},
				copy_source_to_translation: function ( rows ) {
					rows.forEach( function ( row ) { row.copySourceToTarget(); } );
				},
				mark_as_draft: function ( rows ) {
					self.markRowsQuality( rows, 'draft' );
				},
				mark_as_suspect: function ( rows ) {
					self.markRowsQuality( rows, 'suspect' );
				},
				mark_as_validated: function ( rows ) {
					self.markRowsQuality( rows, 'validated' );
				}
			};
		}

		/**
		 * Binds controls specific to a resource kind inside the freshly loaded table.
		 *
		 * @param {Element} container Table container.
		 * @return {void}
		 */
		bindRowControls( container ) { // eslint-disable-line no-unused-vars
		}

		/**
		 * Runs resource specific work once the editor started.
		 *
		 * @return {void}
		 */
		onBoot() {
		}

		/**
		 * Starts the editor.
		 *
		 * @return {void}
		 */
		boot() {
			this.refreshTable();
			this.installPayloadCompaction();
			this.onBoot();
		}

		/**
		 * Returns the element holding the entries table.
		 *
		 * @return {?Element}
		 */
		getContainer() {
			return window.document.getElementById( this.config.tableContainerId );
		}

		/**
		 * Returns the row object of a table row element.
		 *
		 * @param {Element} element Table row element.
		 * @return {Object}
		 */
		getRow( element ) {
			if ( ! this.rowsByElement.has( element ) ) {
				this.rowsByElement.set( element, this.createRow( element ) );
			}

			return this.rowsByElement.get( element );
		}

		/**
		 * Returns the row object of a table row element, or null when there is no element.
		 *
		 * @param {?Element} element Table row element.
		 * @return {?Object}
		 */
		getRowOrNull( element ) {
			return element ? this.getRow( element ) : null;
		}

		/**
		 * Returns every row of the table.
		 *
		 * @return {Object[]}
		 */
		getRows() {
			var container = this.getContainer();

			if ( ! container ) {
				return [];
			}

			return Array.prototype.slice.call( container.querySelectorAll( 'tr.i18nly-translation-entry' ) ).map( this.getRow, this );
		}

		/**
		 * Returns the rows whose selection checkbox is checked.
		 *
		 * @return {Object[]}
		 */
		getSelectedRows() {
			var container = this.getContainer();

			if ( ! container ) {
				return [];
			}

			return Array.prototype.slice.call( container.querySelectorAll( '.i18nly-entry-checkbox' ) ).filter(
				function ( checkbox ) {
					return checkbox.checked;
				}
			).map(
				function ( checkbox ) {
					return checkbox.closest( 'tr' );
				}
			).filter(
				function ( element ) {
					return null !== element;
				}
			).map( this.getRow, this );
		}

		/**
		 * Reloads the entries table from the server and wires it.
		 *
		 * @return {Promise<void>}
		 */
		refreshTable() {
			var self = this;

			return this.ajax.post(
				{
					action: this.config.refreshAction,
					translation_id: this.config.translationId,
					nonce: this.config.refreshNonce
				}
			).then(
				function ( payload ) {
					var container;

					if ( ! payload || ! payload.success || ! payload.data || 'string' !== typeof payload.data.html ) {
						return;
					}

					container = self.getContainer();
					if ( ! container ) {
						return;
					}

					container.innerHTML = payload.data.html;
					self.installPayloadCompaction();
					self.installTableInteractions();
				}
			);
		}

		/**
		 * Keeps the hidden payload field of the post form in sync with the target inputs.
		 *
		 * @return {void}
		 */
		installPayloadCompaction() {
			var self = this;
			var form = window.document.getElementById( 'post' );
			var translationInputs;

			if ( ! form ) {
				return;
			}

			translationInputs = form.querySelectorAll( INPUT_SELECTOR );
			if ( 0 === translationInputs.length ) {
				return;
			}

			this.payloadField = form.querySelector( 'input[name="i18nly_translation_entries_payload"]' );
			if ( ! this.payloadField ) {
				this.payloadField      = window.document.createElement( 'input' );
				this.payloadField.type = 'hidden';
				this.payloadField.name = 'i18nly_translation_entries_payload';
				form.appendChild( this.payloadField );
			}

			translationInputs.forEach(
				function ( input ) {
					var sourceEntryId = input.getAttribute( 'data-i18nly-source-entry-id' );
					var formIndex     = input.getAttribute( 'data-i18nly-form-index' );

					if ( ! sourceEntryId || ! formIndex ) {
						return;
					}

					input.addEventListener(
						'input',
						function ( event ) {
							if ( event && event.isTrusted ) {
								EntryBadges.ensureProvenanceBadge( input, 'manual' );
							}

							EntryBadges.refreshAfterEdit( input );
							self.rebuildPayload();
						}
					);
				}
			);

			this.rebuildPayload();
			window.i18nlyRebuildEntriesPayload = function () {
				self.rebuildPayload();
			};

			if ( ! this.hasSubmitListener ) {
				this.hasSubmitListener = true;

				form.addEventListener(
					'submit',
					function () {
						self.rebuildPayload();
						self.syncPostRefererWithCurrentLocation();
					}
				);
			}
		}

		/**
		 * Serializes every row into the hidden payload field.
		 *
		 * @return {void}
		 */
		rebuildPayload() {
			var payload = {};

			if ( ! this.payloadField ) {
				return;
			}

			this.getRows().forEach(
				function ( row ) {
					row.serializeInto( payload );
				}
			);

			this.payloadField.value = JSON.stringify( payload );
		}

		/**
		 * Rebuilds the payload through the public global, when the payload compaction is installed.
		 *
		 * @return {void}
		 */
		rebuildPayloadIfInstalled() {
			if ( typeof window.i18nlyRebuildEntriesPayload === 'function' ) {
				window.i18nlyRebuildEntriesPayload();
			}
		}

		/**
		 * Points the post form referer and action to the current URL, filters included.
		 *
		 * @return {void}
		 */
		syncPostRefererWithCurrentLocation() {
			var form = window.document.getElementById( 'post' );
			var refererField;

			if ( ! form || ! window.location ) {
				return;
			}

			refererField = form.querySelector( 'input[name="_wp_http_referer"]' );
			if ( ! refererField ) {
				return;
			}

			refererField.value = String( window.location.pathname || '' ) + String( window.location.search || '' );
			form.action        = String( window.location.pathname || '' ) + String( window.location.search || '' );
		}

		/**
		 * Wires the freshly loaded table: selection, bulk actions, filters and quality menus.
		 *
		 * @return {void}
		 */
		installTableInteractions() {
			var self      = this;
			var container = this.getContainer();

			if ( ! container ) {
				return;
			}

			if ( ! this.filterBar ) {
				this.filterBar = new EntryFilterBar( window.document );
				this.tracker   = new ModifiedRowsTracker(
					this.filterBar.container,
					this.uiText,
					function ( options ) {
						self.applyFilters( options );
					}
				);

				this.filterBar.onChange(
					function () {
						self.applyFilters();
					}
				);
			}

			this.tracker.reset();

			if ( ! this.hasContainerListener ) {
				this.hasContainerListener = true;

				container.addEventListener(
					'input',
					function ( event ) {
						var target = event && event.target ? event.target : null;

						if ( ! target || ! target.classList || ! target.classList.contains( 'i18nly-translation-input' ) ) {
							return;
						}

						self.handleRowsUpdated( [ self.getRowOrNull( target.closest( 'tr' ) ) ] );
					}
				);
			}

			this.bindSelection( container );
			this.bindBulkActions( container );

			this.filterBar.restoreFromQuery();
			this.filterBar.persistToQuery();

			this.bindRowControls( container );
			this.bindQualityMenus( container );

			this.applyFilters();
			this.updateBulkActionState();
		}

		/**
		 * Binds the select-all and row selection checkboxes.
		 *
		 * @param {Element} container Table container.
		 * @return {void}
		 */
		bindSelection( container ) {
			var self                = this;
			var rowCheckboxes       = container.querySelectorAll( '.i18nly-entry-checkbox' );
			var selectAllCheckboxes = container.querySelectorAll( '.i18nly-bulk-select-all' );

			selectAllCheckboxes.forEach(
				function ( checkbox ) {
					checkbox.addEventListener(
						'change',
						function () {
							var checked = checkbox.checked;

							Array.prototype.slice.call( rowCheckboxes ).forEach(
								function ( rowCheckbox ) {
									var row = rowCheckbox.closest( 'tr' );

									if ( row && row.style.display === 'none' ) {
										return;
									}

									rowCheckbox.checked = checked;
								}
							);

							self.syncSelectAllState();
						}
					);
				}
			);

			Array.prototype.slice.call( rowCheckboxes ).forEach(
				function ( checkbox ) {
					checkbox.addEventListener( 'change', function () { self.syncSelectAllState(); } );
				}
			);
		}

		/**
		 * Binds the bulk action selectors and apply buttons.
		 *
		 * @param {Element} container Table container.
		 * @return {void}
		 */
		bindBulkActions( container ) {
			var self = this;

			container.querySelectorAll( '.i18nly-bulk-action-selector' ).forEach(
				function ( select ) {
					select.addEventListener( 'change', function () { self.updateBulkActionState(); } );
				}
			);

			container.querySelectorAll( '.i18nly-bulk-apply' ).forEach(
				function ( button ) {
					button.addEventListener(
						'click',
						function () {
							var wrapper = button.closest( '.bulkactions' );
							var select  = wrapper ? wrapper.querySelector( '.i18nly-bulk-action-selector' ) : null;

							if ( ! select || '' === select.value ) {
								return;
							}

							self.applyBulkAction( select.value );
						}
					);
				}
			);
		}

		/**
		 * Enables the apply buttons only when rows are selected and an action is chosen.
		 *
		 * @return {void}
		 */
		updateBulkActionState() {
			var container    = this.getContainer();
			var hasSelection = this.getSelectedRows().length > 0;

			if ( ! container ) {
				return;
			}

			container.querySelectorAll( '.i18nly-bulk-apply' ).forEach(
				function ( button ) {
					var wrapper   = button.closest( '.bulkactions' );
					var select    = wrapper ? wrapper.querySelector( '.i18nly-bulk-action-selector' ) : null;
					var hasAction = ! ! select && '' !== select.value;

					button.disabled = ! hasSelection || ! hasAction;
					if ( button.disabled ) {
						button.setAttribute( 'aria-disabled', 'true' );
					} else {
						button.removeAttribute( 'aria-disabled' );
					}
				}
			);
		}

		/**
		 * Reflects the row selection on the select-all checkboxes.
		 *
		 * @return {void}
		 */
		syncSelectAllState() {
			var container = this.getContainer();
			var rowCheckboxes;
			var checkedCount;
			var totalCount;

			if ( ! container ) {
				return;
			}

			rowCheckboxes = container.querySelectorAll( '.i18nly-entry-checkbox' );
			checkedCount  = Array.prototype.slice.call( rowCheckboxes ).filter(
				function ( checkbox ) {
					return checkbox.checked;
				}
			).length;
			totalCount    = rowCheckboxes.length;

			container.querySelectorAll( '.i18nly-bulk-select-all' ).forEach(
				function ( checkbox ) {
					checkbox.checked       = totalCount > 0 && checkedCount === totalCount;
					checkbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
				}
			);

			this.updateBulkActionState();
		}

		/**
		 * Applies the filters to every row.
		 *
		 * Rows edited since the last application that no longer match stay visible, unless the
		 * options ask for a strict application.
		 *
		 * @param {Object} options Options (strict).
		 * @return {void}
		 */
		applyFilters( options ) {
			var self      = this;
			var behavior  = options || {};
			var strict    = !! behavior.strict;
			var selection = this.filterBar.getSelection();
			var rows      = this.getRows();

			this.tracker.refresh( rows, selection );

			rows.forEach(
				function ( row ) {
					var matches                  = row.matches( selection );
					var isOutOfFilterModifiedRow = self.tracker.isOutOfFilter( row );
					var mustHide                 = ! matches;

					if ( ! strict && isOutOfFilterModifiedRow ) {
						mustHide = false;
					}

					if ( self.tracker.showOnly ) {
						mustHide = ! isOutOfFilterModifiedRow;
					}

					row.setHidden( mustHide );
				}
			);

			this.syncSelectAllState();
		}

		/**
		 * Tracks rows that were just edited and re-applies the filters.
		 *
		 * @param {Object[]} rows Edited rows.
		 * @return {void}
		 */
		handleRowsUpdated( rows ) {
			if ( this.tracker.track( rows ) ) {
				this.applyFilters();
			}
		}

		/**
		 * Applies a quality token to rows, one row at a time.
		 *
		 * @param {Object[]} rows Rows.
		 * @param {string}   token Quality token.
		 * @return {void}
		 */
		markRowsQuality( rows, token ) {
			var self = this;

			rows.forEach(
				function ( row ) {
					row.markQuality( token );
					self.handleRowsUpdated( [ row ] );
				}
			);
		}

		/**
		 * Applies one bulk action to the selected rows.
		 *
		 * @param {string} action Bulk action ID.
		 * @return {void}
		 */
		applyBulkAction( action ) {
			var handlers = this.getBulkActionHandlers();
			var handler  = Object.prototype.hasOwnProperty.call( handlers, action ) ? handlers[action] : null;
			var result;

			if ( 'function' === typeof handler ) {
				result = handler( this.getSelectedRows() );
			}

			if ( false === result ) {
				return;
			}

			this.rebuildPayloadIfInstalled();
		}

		/**
		 * Binds the quality menu of every quality badge.
		 *
		 * @param {Element} container Table container.
		 * @return {void}
		 */
		bindQualityMenus( container ) {
			var self = this;

			Array.prototype.slice.call( container.querySelectorAll( '.i18nly-quality-toggle' ) ).forEach(
				function ( toggle ) {
					var badge   = toggle.closest( '.i18nly-entry-status--quality' );
					var rowElement = toggle.closest( 'tr' );
					var menu    = badge ? badge.querySelector( '.i18nly-quality-menu' ) : null;
					var options = menu ? Array.prototype.slice.call( menu.querySelectorAll( '.i18nly-quality-option' ) ) : [];
					var inputId = badge ? ( badge.getAttribute( 'data-for' ) || '' ) : '';
					var input   = inputId ? window.document.getElementById( inputId ) : null;

					function closeMenu() {
						if ( ! menu || ! toggle ) {
							return;
						}

						menu.hidden = true;
						toggle.setAttribute( 'aria-expanded', 'false' );
					}

					if ( ! badge || ! menu ) {
						return;
					}

					toggle.addEventListener(
						'click',
						function ( event ) {
							event.preventDefault();

							if ( toggle.disabled ) {
								return;
							}

							menu.hidden = ! menu.hidden;
							toggle.setAttribute( 'aria-expanded', menu.hidden ? 'false' : 'true' );

							if ( ! menu.hidden && options.length > 0 ) {
								options[0].focus();
							}
						}
					);

					toggle.addEventListener(
						'keydown',
						function ( event ) {
							if ( 'Escape' === event.key ) {
								closeMenu();
								return;
							}

							if ( 'Enter' === event.key || ' ' === event.key ) {
								event.preventDefault();
								toggle.click();
							}
						}
					);

					badge.addEventListener(
						'focusout',
						function ( event ) {
							if ( badge.contains( event.relatedTarget ) ) {
								return;
							}

							closeMenu();
						}
					);

					options.forEach(
						function ( option ) {
							option.addEventListener(
								'click',
								function () {
									var selectedToken = option.getAttribute( 'data-quality-token' ) || '';

									EntryBadges.applyQualityState( badge, selectedToken );
									closeMenu();
									toggle.focus();

									if ( input && input.value && String( input.value ).trim() !== '' ) {
										self.rebuildPayloadIfInstalled();
									}

									self.handleRowsUpdated( [ self.getRowOrNull( rowElement ) ] );
								}
							);

							option.addEventListener(
								'keydown',
								function ( event ) {
									if ( 'Escape' === event.key ) {
										event.preventDefault();
										closeMenu();
										toggle.focus();
									}
								}
							);
						}
					);
				}
			);
		}
	}

	namespace.AbstractLinguisticResourceEditor = AbstractLinguisticResourceEditor;
} )( window );
