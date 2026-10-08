/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace       = window.I18nly = window.I18nly || {};
	var __              = namespace.i18n.__;
	var sprintf         = namespace.i18n.sprintf;
	var AjaxClient      = namespace.AjaxClient;
	var AiErrorDialog   = namespace.AiErrorDialog;
	var DeepLUsageGauge = namespace.DeepLUsageGauge;

	/**
	 * Parses a strictly positive integer.
	 *
	 * @param {*}      value Raw value.
	 * @param {number} fallback Value returned when the raw value is not a positive integer.
	 * @return {number}
	 */
	function parsePositiveInteger( value, fallback ) {
		var parsed = parseInt( value, 10 );

		if ( Number.isNaN( parsed ) || parsed < 1 ) {
			return fallback;
		}

		return parsed;
	}

	/**
	 * Resolves after a delay.
	 *
	 * @param {number} delayMs Delay in milliseconds.
	 * @return {Promise<void>}
	 */
	function wait( delayMs ) {
		return new Promise(
			function ( resolve ) {
				window.setTimeout( resolve, delayMs );
			}
		);
	}

	/**
	 * Translates a list of inputs with AI, batch after batch, with a progress modal.
	 *
	 * Batches are sent one at a time. A batch rate-limited by the server is retried after the delay
	 * it asks for. The user can cancel at any time; the response of a pending request is then ignored.
	 */
	class AiBatchTranslation {
		/**
		 * @param {Object}    editor TranslationEditor running the translation.
		 * @param {Element[]} buttons "Translate with AI" buttons of the inputs to translate.
		 */
		constructor( editor, buttons ) {
			var config             = editor.config;
			var batchSize          = parsePositiveInteger( config.translateBatchSize, 12 );
			var maxItemsPerRequest = parsePositiveInteger( config.translateMaxItemsPerRequest, 50 );
			var index;

			this.editor           = editor;
			this.ajax             = editor.ajax;
			this.config           = config;
			this.batchAction      = config.translateBatchAction || '';
			this.batchNonce       = config.translateBatchNonce || '';
			this.batches          = [];
			this.batchIndex       = 0;
			this.completedBatches = 0;
			this.isCancelled      = false;
			this.activeController = null;
			this.activeBatchItems = [];
			this.progress         = null;

			batchSize = Math.min( batchSize, maxItemsPerRequest );

			for ( index = 0; index < buttons.length; index += batchSize ) {
				this.batches.push( buttons.slice( index, index + batchSize ) );
			}
		}

		/**
		 * Opens the progress modal and starts the first batch.
		 *
		 * @return {void}
		 */
		start() {
			this.progress = this.showProgressModal();
			this.updateProgress( 0, this.batches.length, this.processingMessage( 0 ) );

			this.runNextBatch();
		}

		/**
		 * Builds and displays the progress modal.
		 *
		 * @return {Object} References to the modal elements.
		 */
		showProgressModal() {
			var self         = this;
			var modal        = window.document.createElement( 'div' );
			var overlay      = window.document.createElement( 'div' );
			var content      = window.document.createElement( 'div' );
			var title        = window.document.createElement( 'h2' );
			var progressText = window.document.createElement( 'p' );
			var progressBar  = window.document.createElement( 'div' );
			var progressFill = window.document.createElement( 'div' );
			var actions      = window.document.createElement( 'div' );
			var cancelButton = window.document.createElement( 'button' );
			var closeButton  = window.document.createElement( 'button' );

			modal.id = 'i18nly-progress-modal';
			modal.setAttribute( 'role', 'dialog' );
			modal.setAttribute( 'aria-modal', 'true' );
			modal.setAttribute( 'aria-labelledby', 'i18nly-progress-title' );

			overlay.className = 'i18nly-progress-overlay';

			content.className = 'i18nly-progress-content';
			title.id          = 'i18nly-progress-title';
			title.textContent = __( 'AI Translation in Progress', 'i18nly' );
			title.className   = 'i18nly-progress-title';

			progressText.id          = 'i18nly-progress-text';
			progressText.className   = 'i18nly-progress-text';
			progressText.setAttribute( 'aria-live', 'polite' );
			progressText.textContent = this.processingMessage( 0 );

			progressBar.className = 'i18nly-progress-bar';
			progressFill.id       = 'i18nly-progress-fill';
			progressFill.className = 'i18nly-progress-fill';
			progressBar.appendChild( progressFill );

			actions.className = 'i18nly-progress-actions';

			cancelButton.type        = 'button';
			cancelButton.className   = 'button button-secondary i18nly-progress-cancel';
			cancelButton.textContent = __( 'Cancel', 'i18nly' );
			cancelButton.addEventListener( 'click', function () { self.cancel(); } );
			actions.appendChild( cancelButton );

			closeButton.type          = 'button';
			closeButton.className     = 'button button-primary i18nly-progress-close';
			closeButton.textContent   = __( 'Close', 'i18nly' );
			closeButton.style.display = 'none';
			closeButton.addEventListener( 'click', function () { self.closeModal(); } );
			actions.appendChild( closeButton );

			content.appendChild( title );
			content.appendChild( progressText );
			content.appendChild( progressBar );
			content.appendChild( actions );

			overlay.appendChild( content );
			modal.appendChild( overlay );

			window.document.body.appendChild( modal );

			return {
				modal: modal,
				progressText: progressText,
				progressFill: progressFill,
				cancelButton: cancelButton,
				closeButton: closeButton
			};
		}

		/**
		 * Returns the progress message of a batch.
		 *
		 * @param {number} batchNumber Number of the batch being processed (0 before the first one).
		 * @return {string}
		 */
		processingMessage( batchNumber ) {
			return sprintf(
				/* translators: 1: number of the batch being processed, 2: total number of batches. */
				__( 'Processing batch %1$d of %2$d', 'i18nly' ),
				batchNumber,
				this.batches.length
			);
		}

		/**
		 * Updates the progress text and bar.
		 *
		 * @param {number} completed Completed batches.
		 * @param {number} totalBatches Total batches.
		 * @param {string} message Optional message replacing the default text.
		 * @return {void}
		 */
		updateProgress( completed, totalBatches, message ) {
			var percentage = 0;

			if ( this.progress && this.progress.progressText ) {
				this.progress.progressText.textContent = message || sprintf(
						/* translators: 1: number of batches processed, 2: total number of batches. */
						__( 'Processed batch %1$d of %2$d', 'i18nly' ),
						completed,
						totalBatches
					);
			}

			if ( this.progress && this.progress.progressFill ) {
				percentage = totalBatches > 0 ? Math.min( 100, Math.round( ( completed / totalBatches ) * 100 ) ) : 0;
				this.progress.progressFill.style.width = percentage + '%';
			}
		}

		/**
		 * Removes the progress modal.
		 *
		 * @return {void}
		 */
		closeModal() {
			if ( this.progress && this.progress.modal && this.progress.modal.parentNode ) {
				this.progress.modal.parentNode.removeChild( this.progress.modal );
			}

			this.progress = null;
		}

		/**
		 * Re-enables the buttons of a batch.
		 *
		 * @param {Object[]} items Batch items.
		 * @return {void}
		 */
		releaseBatchItems( items ) {
			items.forEach(
				function ( item ) {
					item.button.disabled = false;
					item.button.removeAttribute( 'aria-busy' );
				}
			);
		}

		/**
		 * Cancels the translation and ignores the response of the pending request.
		 *
		 * @return {void}
		 */
		cancel() {
			if ( this.isCancelled ) {
				return;
			}

			this.isCancelled = true;

			if ( this.activeController ) {
				this.activeController.abort();
				this.activeController = null;
			}

			this.releaseBatchItems( this.activeBatchItems );
			this.updateProgress( this.completedBatches, this.batches.length, __( 'Translation cancelled.', 'i18nly' ) );
			window.setTimeout( this.closeModal.bind( this ), 150 );
		}

		/**
		 * Reports completion and closes the modal shortly after.
		 *
		 * @return {void}
		 */
		finish() {
			this.updateProgress( this.batches.length, this.batches.length, __( 'Translation completed.', 'i18nly' ) );
			window.setTimeout( this.closeModal.bind( this ), 600 );
		}

		/**
		 * Stops the translation and reports an error.
		 *
		 * @param {string}  message Error message.
		 * @param {?Object} settingsLinkMeta Optional settings link metadata.
		 * @return {void}
		 */
		fail( message, settingsLinkMeta ) {
			this.closeModal();
			AiErrorDialog.notify( message, settingsLinkMeta );
		}

		/**
		 * Creates an abort controller when the browser supports it.
		 *
		 * @return {?AbortController}
		 */
		createRequestController() {
			if ( typeof window.AbortController !== 'function' ) {
				return null;
			}

			return new window.AbortController();
		}

		/**
		 * Sends one batch request, waiting and retrying while the server rate-limits it.
		 *
		 * @param {Object} values Request values.
		 * @param {number} currentBatchNum One-based batch number.
		 * @return {Promise<Object>}
		 */
		requestBatch( values, currentBatchNum ) {
			var self = this;

			this.activeController = this.createRequestController();

			return this.ajax.postWithMeta(
				values,
				this.activeController ? { signal: this.activeController.signal } : {}
			).then(
				function ( response ) {
					var payload      = response && response.payload ? response.payload : null;
					var retryAfterMs = 0;

					self.activeController = null;

					if ( self.isCancelled ) {
						return { cancelled: true };
					}

					if ( payload && payload.data && payload.data.retry_after_ms ) {
						retryAfterMs = parsePositiveInteger( payload.data.retry_after_ms, 0 );
					}

					if ( 429 === response.status ) {
						if ( retryAfterMs <= 0 ) {
							retryAfterMs = 1000;
						}

						self.updateProgress(
							self.completedBatches,
							self.batches.length,
							sprintf(
								/* translators: 1: current batch number, 2: total number of batches, 3: seconds to wait. */
								__( 'Too many requests. Retrying batch %1$d of %2$d in %3$ds...', 'i18nly' ),
								currentBatchNum,
								self.batches.length,
								Math.ceil( retryAfterMs / 1000 )
							)
						);

						return wait( retryAfterMs ).then(
							function () {
								return self.requestBatch( values, currentBatchNum );
							}
						);
					}

					return response;
				},
				function ( error ) {
					self.activeController = null;

					if ( self.isCancelled || ( error && 'AbortError' === error.name ) ) {
						return { cancelled: true };
					}

					throw error;
				}
			);
		}

		/**
		 * Translates the inputs of a batch one request at a time.
		 *
		 * @param {Element[]} batch Translate buttons.
		 * @return {Promise<void>}
		 */
		runBatchSequentially( batch ) {
			var self = this;

			return batch.reduce(
				function ( promise, button ) {
					return promise.then(
						function () {
							if ( self.isCancelled ) {
								return Promise.resolve();
							}

							return self.editor.translateWithAI( button );
						}
					);
				},
				Promise.resolve()
			);
		}

		/**
		 * Builds the request items of a batch, locking their buttons.
		 *
		 * @param {Element[]} batch Translate buttons.
		 * @return {Object[]}
		 */
		buildBatchItems( batch ) {
			return batch.map(
				function ( button ) {
					var inputId       = button.getAttribute( 'data-for' );
					var input         = inputId ? window.document.getElementById( inputId ) : null;
					var sourceEntryId = input ? input.getAttribute( 'data-i18nly-source-entry-id' ) : '';
					var formIndex     = input ? input.getAttribute( 'data-i18nly-form-index' ) : '0';
					var sourceText    = input ? input.getAttribute( 'data-i18nly-source-text' ) : '';
					var witness       = input ? input.getAttribute( 'data-i18nly-witness' ) : '';

					button.disabled = true;
					button.setAttribute( 'aria-busy', 'true' );

					return {
						button: button,
						input: input,
						request: {
							source_entry_id: parseInt( sourceEntryId || '0', 10 ),
							form_index: parseInt( formIndex || '0', 10 ),
							source_text: sourceText || '',
							witness_n: witness || ''
						}
					};
				}
			).filter(
				function ( item ) {
					return item.input && item.request.source_entry_id > 0 && '' !== item.request.source_text;
				}
			);
		}

		/**
		 * Applies the results of a batch response to the matching inputs.
		 *
		 * @param {Object[]} batchItems Items of the batch.
		 * @param {Object[]} results Results returned by the server.
		 * @return {void}
		 */
		applyResults( batchItems, results ) {
			var self = this;

			results.forEach(
				function ( result ) {
					var matchedItem;

					if ( ! result || ! result.success ) {
						return;
					}

					matchedItem = batchItems.find(
						function ( item ) {
							return item.request.source_entry_id === parseInt( result.source_entry_id || '0', 10 )
								&& item.request.form_index === parseInt( result.form_index || '0', 10 );
						}
					);

					if ( ! matchedItem || ! matchedItem.input ) {
						return;
					}

					self.editor.applyAiResult( matchedItem.input, result.translation, result.review_token );
				}
			);
		}

		/**
		 * Sends the next batch, or finishes when none is left.
		 *
		 * @return {Promise<void>}
		 */
		runNextBatch() {
			var self = this;
			var currentBatch;
			var currentBatchNum;
			var values;

			if ( this.isCancelled ) {
				return Promise.resolve();
			}

			if ( this.batchIndex >= this.batches.length ) {
				this.finish();
				return Promise.resolve();
			}

			currentBatch    = this.batches[this.batchIndex];
			currentBatchNum = this.batchIndex + 1;
			this.batchIndex += 1;

			if ( '' === this.batchAction || '' === this.batchNonce ) {
				this.updateProgress( this.completedBatches, this.batches.length, this.processingMessage( currentBatchNum ) );
				return this.runBatchSequentially( currentBatch ).then(
					function () {
						self.completedBatches = currentBatchNum;
						return self.runNextBatch();
					}
				);
			}

			this.activeBatchItems = this.buildBatchItems( currentBatch );

			if ( 0 === this.activeBatchItems.length ) {
				this.completedBatches = currentBatchNum;
				return this.runNextBatch();
			}

			values = {
				action: this.batchAction,
				translation_id: this.config.translationId,
				items_json: JSON.stringify( this.activeBatchItems.map( function ( item ) { return item.request; } ) ),
				nonce: this.batchNonce,
				batch_index: currentBatchNum,
				total_batches: this.batches.length
			};

			this.updateProgress( this.completedBatches, this.batches.length, this.processingMessage( currentBatchNum ) );

			return this.requestBatch( values, currentBatchNum ).then(
				function ( response ) {
					var currentBatchItems = self.activeBatchItems;
					var payload           = response && response.payload ? response.payload : null;

					self.releaseBatchItems( currentBatchItems );
					self.activeBatchItems = [];

					if ( response && response.cancelled ) {
						return;
					}

					if ( response && 429 === response.status ) {
						self.fail( __( 'Translation stopped after repeated rate-limit errors.', 'i18nly' ) );
						return;
					}

					if ( ! payload || ! payload.success || ! payload.data || ! Array.isArray( payload.data.results ) ) {
						self.fail(
							AjaxClient.getErrorMessage( payload, __( 'Translation stopped because the batch response was invalid.', 'i18nly' ) ),
							AjaxClient.getSettingsLinkMeta( payload )
						);
						return;
					}

					self.applyResults( currentBatchItems, payload.data.results );

					if ( payload.data.usage_html ) {
						DeepLUsageGauge.updateFromHtml( payload.data.usage_html );
					}

					self.completedBatches = currentBatchNum;

					return self.runNextBatch();
				}
			).catch(
				function () {
					self.releaseBatchItems( self.activeBatchItems );
					self.activeBatchItems = [];
					self.fail( __( 'Translation stopped because the batch request failed.', 'i18nly' ) );
				}
			);
		}
	}

	namespace.AiBatchTranslation = AiBatchTranslation;
} )( window );
