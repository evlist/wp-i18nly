/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace         = window.I18nly = window.I18nly || {};
	var AjaxClient        = namespace.AjaxClient;
	var EntryBadges       = namespace.EntryBadges;
	var AiErrorDialog     = namespace.AiErrorDialog;
	var AiBatchTranslation = namespace.AiBatchTranslation;
	var TranslationRow    = namespace.TranslationRow;

	/**
	 * Editor of a translation resource.
	 *
	 * On top of the generic resource editing, it generates the temporary POT when the screen opens and
	 * translates entries with AI, one at a time or by batches.
	 */
	class TranslationEditor extends namespace.AbstractLinguisticResourceEditor {
		/**
		 * Creates the row object of a table row element.
		 *
		 * @param {Element} element Table row element.
		 * @return {TranslationRow}
		 */
		createRow( element ) {
			return new TranslationRow( element );
		}

		/**
		 * Adds the AI batch action to the generic bulk actions.
		 *
		 * @return {Object<string, Function>}
		 */
		getBulkActionHandlers() {
			var self     = this;
			var handlers = super.getBulkActionHandlers();

			handlers.ai_translate_selected = function () {
				self.translateSelectedRowsWithAI();

				return false;
			};

			return handlers;
		}

		/**
		 * Generates the temporary POT of the translation, then reloads the table.
		 *
		 * @return {void}
		 */
		onBoot() {
			var self = this;

			this.ajax.post(
				{
					action: this.config.generateAction,
					translation_id: this.config.translationId,
					nonce: this.config.generateNonce
				}
			).then(
				function ( payload ) {
					if ( payload && payload.success ) {
						self.refreshTable();
					}
				}
			);
		}

		/**
		 * Binds the "translate with AI" buttons.
		 *
		 * @param {Element} container Table container.
		 * @return {void}
		 */
		bindRowControls( container ) {
			var self = this;

			Array.prototype.slice.call( container.querySelectorAll( '.i18nly-translate-btn' ) ).forEach(
				function ( button ) {
					if ( self.config.hasDeeplKey === false ) {
						button.disabled = true;
						button.title    = 'DeepL API key not configured';
					}

					button.addEventListener(
						'click',
						function () {
							self.translateWithAI( button );
						}
					);
				}
			);
		}

		/**
		 * Applies one AI translation to an input and records its AI provenance.
		 *
		 * @param {Element} input Target input.
		 * @param {string}  translation Translated text.
		 * @param {string}  reviewToken Quality token returned by the server.
		 * @return {void}
		 */
		applyAiResult( input, translation, reviewToken ) {
			input.value = translation || '';
			input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );

			EntryBadges.applyQualityState( EntryBadges.getQualityBadge( input ), reviewToken || '' );

			EntryBadges.ensureProvenanceBadge( input, 'ai' );
			EntryBadges.removeProvenanceBadge( input, 'manual' );
			this.handleRowsUpdated( [ this.getRowOrNull( input.closest( 'tr' ) ) ] );
		}

		/**
		 * Translates one input with AI.
		 *
		 * @param {Element} button "Translate with AI" button of the input.
		 * @return {Promise<void>}
		 */
		translateWithAI( button ) {
			var self            = this;
			var inputId         = button.getAttribute( 'data-for' );
			var input           = inputId ? window.document.getElementById( inputId ) : null;
			var sourceEntryId   = input ? input.getAttribute( 'data-i18nly-source-entry-id' ) : null;
			var formIndex       = input ? input.getAttribute( 'data-i18nly-form-index' ) : null;
			var sourceText      = input ? input.getAttribute( 'data-i18nly-source-text' ) : null;
			var witness         = input ? input.getAttribute( 'data-i18nly-witness' ) : null;
			var translateAction = this.config.translateAction || 'i18nly_ai_translate_entry';
			var translateNonce  = this.config.translateNonce || '';

			if ( ! input || ! sourceEntryId || ! formIndex || ! sourceText ) {
				return Promise.resolve();
			}

			button.disabled = true;
			button.setAttribute( 'aria-busy', 'true' );

			return this.ajax.post(
				{
					action: translateAction,
					translation_id: this.config.translationId,
					source_entry_id: sourceEntryId,
					form_index: formIndex,
					source_text: sourceText,
					witness_n: witness || '',
					nonce: translateNonce
				}
			).then(
				function ( payload ) {
					button.disabled = false;
					button.removeAttribute( 'aria-busy' );

					if ( ! payload || ! payload.success || ! payload.data ) {
						AiErrorDialog.notify(
							AjaxClient.getErrorMessage( payload, 'Translation failed.' ),
							AjaxClient.getSettingsLinkMeta( payload )
						);
						return;
					}

					self.applyAiResult( input, payload.data.translation, payload.data.review_token );
				}
			).catch(
				function () {
					button.disabled = false;
					button.removeAttribute( 'aria-busy' );
					AiErrorDialog.notify( 'Translation failed because the request could not be completed.' );
				}
			);
		}

		/**
		 * Translates every input of the selected rows with AI, by batches.
		 *
		 * @return {void}
		 */
		translateSelectedRowsWithAI() {
			var buttons = [];

			if ( this.config.hasDeeplKey === false ) {
				return;
			}

			this.getSelectedRows().forEach(
				function ( row ) {
					row.getTranslateButtons().forEach(
						function ( button ) {
							buttons.push( button );
						}
					);
				}
			);

			if ( 0 === buttons.length ) {
				return;
			}

			new AiBatchTranslation( this, buttons ).start();
		}
	}

	namespace.TranslationEditor = TranslationEditor;
} )( window );
