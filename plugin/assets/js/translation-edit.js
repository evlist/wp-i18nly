/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

(function () {
	var config  = window.i18nlyTranslationEditConfig || null;
	var isReady = typeof window.fetch === 'function' && null !== config;

	if (window.i18nlyPotInitDone || ! isReady) {
		return;
	}

	window.i18nlyPotInitDone = true;

	function toFormBody(values) {
		var keys = Object.keys( values );

		return keys
			.map(
				function (key) {
					return encodeURIComponent( key ) + '=' + encodeURIComponent( String( values[key] ) );
				}
			)
			.join( '&' );
	}

	function postForm(body) {
		return window.fetch(
			config.ajaxUrl,
			{
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': config.contentTypeHeader
				},
				body: body
			}
		).then(
			function (response) {
				return response.json();
			}
		);
	}

	function postFormWithMeta(body, options) {
		var requestOptions = options || {};

		return window.fetch(
			config.ajaxUrl,
			{
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': config.contentTypeHeader
				},
				body: body,
				signal: requestOptions.signal
			}
		).then(
			function (response) {
				return response.text().then(
					function (text) {
						var payload = null;

						if ( '' !== text ) {
							try {
								payload = JSON.parse( text );
							} catch (error) {
								payload = null;
							}
						}

						return {
							ok: response.ok,
							status: response.status,
							payload: payload
						};
					}
				);
			}
		);
	}

	function wait(delayMs) {
		return new Promise(
			function (resolve) {
				window.setTimeout( resolve, delayMs );
			}
		);
	}

	function parsePositiveInteger(value, fallback) {
		var parsed = parseInt( value, 10 );

		if ( Number.isNaN( parsed ) || parsed < 1 ) {
			return fallback;
		}

		return parsed;
	}

	function getStatusBadgeForInput(input) {
		var row;
		var inputId;

		if ( ! input) {
			return null;
		}

		row = input.closest( 'tr' );
		inputId = input.id || '';

		if ( ! row || '' === inputId ) {
			return null;
		}

		return row.querySelector( '.i18nly-entry-status[data-for="' + inputId + '"]' );
	}

	function refreshEntriesTable() {
		var body = toFormBody(
			{
				action: config.refreshAction,
				translation_id: config.translationId,
				nonce: config.refreshNonce
			}
		);

		return postForm( body ).then(
			function (payload) {
				var container;

				if ( ! payload || ! payload.success || ! payload.data || 'string' !== typeof payload.data.html) {
					return;
				}

				container = document.getElementById( config.tableContainerId );
				if ( ! container) {
					return;
				}

				container.innerHTML = payload.data.html;
				installEntriesPayloadCompaction();
				installEntriesTableInteractions();
			}
		);
	}

	function installEntriesPayloadCompaction() {
		var form = document.getElementById( 'post' );
		var translationInputs;
		var hiddenField;

		function clearStatusBadgeForInput(input) {
			var badge = getStatusBadgeForInput( input );
			var hasText = '' !== String( input.value || '' ).trim();

			if ( ! badge ) {
				return;
			}

			if (hasText) {
				badge.className = 'i18nly-entry-status i18nly-entry-status--draft';
				badge.textContent = 'Draft';
				badge.setAttribute( 'data-status-token', 'draft' );
				return;
			}

			badge.className = 'i18nly-entry-status';
			badge.textContent = '';
			badge.setAttribute( 'data-status-token', '' );
		}

		function rebuildPayload() {
			var payload     = {};
			var index       = 0;
			var inputsCount = translationInputs.length;

			for (index = 0; index < inputsCount; index++) {
				var input         = translationInputs[index];
				var sourceEntryId = input.getAttribute( 'data-i18nly-source-entry-id' );
				var formIndex     = input.getAttribute( 'data-i18nly-form-index' );

				if ( ! sourceEntryId) {
					continue;
				}

				if ( ! formIndex) {
					formIndex = '0';
				}

				if ( ! payload[sourceEntryId]) {
					payload[sourceEntryId] = { forms: {}, statuses: {} };
				}

				var badge = getStatusBadgeForInput( input );
				var token = badge ? ( badge.getAttribute( 'data-status-token' ) || '' ) : '';

				payload[sourceEntryId].forms[formIndex] = input.value;
				payload[sourceEntryId].statuses[formIndex] = token;
			}

			hiddenField.value = JSON.stringify( payload );
		}

		if ( ! form) {
			return;
		}

		translationInputs = form.querySelectorAll( '.i18nly-translation-input' );
		if (0 === translationInputs.length) {
			return;
		}

		hiddenField = form.querySelector( 'input[name="i18nly_translation_entries_payload"]' );
		if ( ! hiddenField) {
			hiddenField      = document.createElement( 'input' );
			hiddenField.type = 'hidden';
			hiddenField.name = 'i18nly_translation_entries_payload';
			form.appendChild( hiddenField );
		}

		translationInputs.forEach(
			function (input) {
				var sourceEntryId = input.getAttribute( 'data-i18nly-source-entry-id' );
				var formIndex     = input.getAttribute( 'data-i18nly-form-index' );

				if ( ! sourceEntryId || ! formIndex) {
					return;
				}

				input.addEventListener(
					'input',
					function () {
						clearStatusBadgeForInput( input );
						rebuildPayload();
					}
				);
			}
		);

		rebuildPayload();

		form.addEventListener(
			'submit',
			function () {
				rebuildPayload();
			},
			{ once: true }
		);
	}

	function installEntriesTableInteractions() {
		var container      = document.getElementById( config.tableContainerId );
		var obsoleteToggle = document.getElementById( 'i18nly-show-obsolete-entries' );
		var rowCheckboxes;
		var selectAllCheckboxes;
		var bulkActionSelects;
		var bulkApplyButtons;

		function getSelectedRows() {
			return Array.prototype.slice.call( rowCheckboxes ).filter(
				function (checkbox) {
					return checkbox.checked;
				}
			).map(
				function (checkbox) {
					return checkbox.closest( 'tr' );
				}
			).filter(
				function (row) {
					return null !== row;
				}
			);
		}

		function updateBulkActionState() {
			var hasSelection = getSelectedRows().length > 0;

			bulkApplyButtons.forEach(
				function (button) {
					var wrapper   = button.closest( '.bulkactions' );
					var select    = wrapper ? wrapper.querySelector( '.i18nly-bulk-action-selector' ) : null;
					var hasAction = ! ! select && '' !== select.value;

					button.disabled = ! hasSelection || ! hasAction;
					if (button.disabled) {
						button.setAttribute( 'aria-disabled', 'true' );
					} else {
						button.removeAttribute( 'aria-disabled' );
					}
				}
			);
		}

		function syncSelectAllState() {
			var checkedCount = Array.prototype.slice.call( rowCheckboxes ).filter(
				function (checkbox) {
					return checkbox.checked;
				}
			).length;
			var totalCount   = rowCheckboxes.length;

			selectAllCheckboxes.forEach(
				function (checkbox) {
					checkbox.checked       = totalCount > 0 && checkedCount === totalCount;
					checkbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
				}
			);

			updateBulkActionState();
		}

		function applyObsoleteFilter() {
			var showObsolete = ! ! obsoleteToggle && obsoleteToggle.checked;

			Array.prototype.slice.call( container.querySelectorAll( 'tr.i18nly-translation-entry' ) ).forEach(
				function (row) {
					var status     = ( row.getAttribute( 'data-entry-status' ) || '' ).toLowerCase().trim();
					var isObsolete = status === 'obsolete';
					var checkbox   = row.querySelector( '.i18nly-entry-checkbox' );
					var mustHide   = isObsolete && ! showObsolete;

					row.style.display = mustHide ? 'none' : '';
					row.setAttribute( 'aria-hidden', mustHide ? 'true' : 'false' );

					if (mustHide && checkbox) {
						checkbox.checked = false;
					}
				}
			);

			syncSelectAllState();
		}

		function copySourceToTranslation(row) {
			Array.prototype.slice.call( row.querySelectorAll( '.i18nly-translation-input' ) ).forEach(
				function (input) {
					input.value = input.getAttribute( 'data-i18nly-source-text' ) || '';
					input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
			);
		}

		function clearSelectedTranslations(row) {
			Array.prototype.slice.call( row.querySelectorAll( '.i18nly-translation-input' ) ).forEach(
				function (input) {
					input.value = '';
					input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
			);
		}

		function translateWithAI(button) {
			var inputId        = button.getAttribute( 'data-for' );
			var input          = inputId ? document.getElementById( inputId ) : null;
			var sourceEntryId  = input ? input.getAttribute( 'data-i18nly-source-entry-id' ) : null;
			var formIndex      = input ? input.getAttribute( 'data-i18nly-form-index' ) : null;
			var sourceText     = input ? input.getAttribute( 'data-i18nly-source-text' ) : null;
			var witness        = input ? input.getAttribute( 'data-i18nly-witness' ) : null;
			var translateAction = config.translateAction || 'i18nly_ai_translate_entry';
			var translateNonce  = config.translateNonce || '';

			if ( ! input || ! sourceEntryId || ! formIndex || ! sourceText ) {
				return Promise.resolve();
			}

			button.disabled = true;
			button.setAttribute( 'aria-busy', 'true' );

			var body = toFormBody(
				{
					action: translateAction,
					translation_id: config.translationId,
					source_entry_id: sourceEntryId,
					form_index: formIndex,
					source_text: sourceText,
					witness_n: witness || '',
					nonce: translateNonce
				}
			);

			return postForm( body ).then(
				function (payload) {
					button.disabled = false;
					button.removeAttribute( 'aria-busy' );

					if ( ! payload || ! payload.success || ! payload.data ) {
						return;
					}

					input.value = payload.data.translation || '';
					input.dispatchEvent( new Event( 'input', { bubbles: true } ) );

					var badge = getStatusBadgeForInput( input );
					var token = payload.data.review_token || '';

					if ('ai_draft_ok' === token) {
						token = 'draft_ai';
					} else if ('ai_draft_suspect' === token) {
						token = 'draft_ai_suspect';
					} else if ('ai_draft_needs_fix' === token) {
						token = 'draft_ai_needs_fix';
					}

					var tokenMap = {
						draft: { className: 'i18nly-entry-status--draft', label: 'Draft' },
						validated: { className: 'i18nly-entry-status--validated', label: 'Validated' },
						draft_ai: { className: 'i18nly-entry-status--ai-draft', label: 'AI draft' },
						draft_ai_suspect: { className: 'i18nly-entry-status--suspect', label: 'AI draft (suspect)' },
						draft_ai_needs_fix: { className: 'i18nly-entry-status--needs-fix', label: 'AI draft (needs fix)' }
					};

					if ( badge && tokenMap[token] ) {
						badge.className = 'i18nly-entry-status ' + tokenMap[token].className;
						badge.textContent = tokenMap[token].label;
						badge.setAttribute( 'data-status-token', token );
					}

					if ( badge && ! tokenMap[token] ) {
						badge.className = 'i18nly-entry-status';
						badge.textContent = '';
						badge.setAttribute( 'data-status-token', '' );
					}
				}
			).catch(
				function () {
					button.disabled = false;
					button.removeAttribute( 'aria-busy' );
				}
			);
		}

		function translateSelectedRowsWithAI() {
			var buttons = [];
			var batchSize;
			var maxItemsPerRequest;
			var backoffBaseMs;
			var maxRetryAttempts;
			var batches = [];
			var batchIndex = 0;
			var completedBatches = 0;
			var batchAction = config.translateBatchAction || '';
			var batchNonce = config.translateBatchNonce || '';
			var isCancelled = false;
			var activeController = null;
			var activeBatchItems = [];
			var progressElements;

			if ( config.hasDeeplKey === false ) {
				return;
			}

			batchSize = parsePositiveInteger( config.translateBatchSize, 12 );
			maxItemsPerRequest = parsePositiveInteger( config.translateMaxItemsPerRequest, 50 );
			backoffBaseMs = parsePositiveInteger( config.translateBackoffBaseMs, 1000 );
			maxRetryAttempts = parsePositiveInteger( config.translateMaxRetryAttempts, 4 );

			batchSize = Math.min( batchSize, maxItemsPerRequest );

			getSelectedRows().forEach(
				function (row) {
					Array.prototype.slice.call( row.querySelectorAll( '.i18nly-translate-btn' ) ).forEach(
						function (button) {
							buttons.push( button );
						}
					);
				}
			);

			if (0 === buttons.length) {
				return;
			}

			for (var i = 0; i < buttons.length; i += batchSize) {
				batches.push( buttons.slice( i, i + batchSize ) );
			}

			function showProgressModal() {
				var modal = document.createElement( 'div' );
				var overlay = document.createElement( 'div' );
				var content = document.createElement( 'div' );
				var title = document.createElement( 'h2' );
				var progressText = document.createElement( 'p' );
				var progressBar = document.createElement( 'div' );
				var progressFill = document.createElement( 'div' );
				var actions = document.createElement( 'div' );
				var cancelButton = document.createElement( 'button' );
				var closeButton = document.createElement( 'button' );

				modal.id = 'i18nly-progress-modal';
				modal.setAttribute( 'role', 'dialog' );
				modal.setAttribute( 'aria-modal', 'true' );
				modal.setAttribute( 'aria-labelledby', 'i18nly-progress-title' );

				overlay.className = 'i18nly-progress-overlay';

				content.className = 'i18nly-progress-content';
				title.id = 'i18nly-progress-title';
				title.textContent = 'AI Translation in Progress';
				title.className = 'i18nly-progress-title';

				progressText.id = 'i18nly-progress-text';
				progressText.className = 'i18nly-progress-text';
				progressText.setAttribute( 'aria-live', 'polite' );
				progressText.textContent = 'Processing batch 0 of ' + batches.length;

				progressBar.className = 'i18nly-progress-bar';
				progressFill.id = 'i18nly-progress-fill';
				progressFill.className = 'i18nly-progress-fill';
				progressBar.appendChild( progressFill );

				actions.className = 'i18nly-progress-actions';

				cancelButton.type = 'button';
				cancelButton.className = 'button button-secondary i18nly-progress-cancel';
				cancelButton.textContent = 'Cancel';
				cancelButton.addEventListener( 'click', cancelTranslation );
				actions.appendChild( cancelButton );

				closeButton.type = 'button';
				closeButton.className = 'button button-primary i18nly-progress-close';
				closeButton.textContent = 'Close';
				closeButton.style.display = 'none';
				closeButton.addEventListener( 'click', closeModal );
				actions.appendChild( closeButton );

				content.appendChild( title );
				content.appendChild( progressText );
				content.appendChild( progressBar );
				content.appendChild( actions );

				overlay.appendChild( content );
				modal.appendChild( overlay );

				document.body.appendChild( modal );

				return {
					modal: modal,
					progressText: progressText,
					progressFill: progressFill,
					cancelButton: cancelButton,
					closeButton: closeButton
				};
			}

			function updateProgress(completed, totalBatches, message) {
				var percentage = 0;

				if ( progressElements && progressElements.progressText ) {
					progressElements.progressText.textContent = message || ( 'Processed batch ' + completed + ' of ' + totalBatches );
				}

				if ( progressElements && progressElements.progressFill ) {
					percentage = totalBatches > 0 ? Math.min( 100, Math.round( ( completed / totalBatches ) * 100 ) ) : 0;
					progressElements.progressFill.style.width = percentage + '%';
				}
			}

			function closeModal() {
				if ( progressElements && progressElements.modal && progressElements.modal.parentNode ) {
					progressElements.modal.parentNode.removeChild( progressElements.modal );
				}

				progressElements = null;
			}

			function releaseBatchItems(items) {
				items.forEach(
					function (item) {
						item.button.disabled = false;
						item.button.removeAttribute( 'aria-busy' );
					}
				);
			}

			function cancelTranslation() {
				if ( isCancelled ) {
					return;
				}

				isCancelled = true;

				if ( activeController ) {
					activeController.abort();
					activeController = null;
				}

				releaseBatchItems( activeBatchItems );
				updateProgress( completedBatches, batches.length, 'Translation cancelled.' );
				window.setTimeout( closeModal, 150 );
			}

			function showCloseAction() {
				if ( ! progressElements ) {
					return;
				}

				if ( progressElements.cancelButton ) {
					progressElements.cancelButton.style.display = 'none';
				}

				if ( progressElements.closeButton ) {
					progressElements.closeButton.style.display = 'inline-flex';
				}
			}

			function finishProgress() {
				updateProgress( batches.length, batches.length, 'Translation completed.' );
				window.setTimeout( closeModal, 600 );
			}

			function failProgress(message) {
				updateProgress( completedBatches, batches.length, message );
				showCloseAction();
			}

			function createRequestController() {
				if ( typeof window.AbortController !== 'function' ) {
					return null;
				}

				return new window.AbortController();
			}

			function requestBatch(body, currentBatchNum, attempt) {
				activeController = createRequestController();

				return postFormWithMeta(
					body,
					activeController ? { signal: activeController.signal } : {}
				).then(
					function (response) {
						var payload = response && response.payload ? response.payload : null;
						var retryAfterMs = 0;
						var waitMs = 0;
						activeController = null;

						if ( isCancelled ) {
							return { cancelled: true };
						}

						if ( payload && payload.data && payload.data.retry_after_ms ) {
							retryAfterMs = parsePositiveInteger( payload.data.retry_after_ms, 0 );
						}

						if ( 429 === response.status && attempt < maxRetryAttempts ) {
							waitMs = retryAfterMs > 0
								? retryAfterMs
								: backoffBaseMs * Math.pow( 2, attempt );

							updateProgress(
								completedBatches,
								batches.length,
								'Too many requests. Retrying batch ' + currentBatchNum + ' of ' + batches.length + ' in ' + Math.ceil( waitMs / 1000 ) + 's (attempt ' + ( attempt + 1 ) + '/' + maxRetryAttempts + ')...'
							);

							return wait( waitMs ).then(
								function () {
									return requestBatch( body, currentBatchNum, attempt + 1 );
								}
							);
						}

						return response;
					},
					function (error) {
						activeController = null;

						if ( isCancelled || ( error && 'AbortError' === error.name ) ) {
							return { cancelled: true };
						}

						throw error;
					}
				);
			}

			function runBatchSequentially(batch) {
				return batch.reduce(
					function (promise, button) {
						return promise.then(
							function () {
								if ( isCancelled ) {
									return Promise.resolve();
								}

								return translateWithAI( button );
							}
						);
					},
					Promise.resolve()
				);
			}

			function runNextBatch() {
				if ( isCancelled ) {
					return Promise.resolve();
				}

				if (batchIndex >= batches.length) {
					finishProgress();
					return Promise.resolve();
				}

				var currentBatch = batches[batchIndex];
				var currentBatchNum = batchIndex + 1;
				batchIndex += 1;

				if ( '' === batchAction || '' === batchNonce ) {
					updateProgress( completedBatches, batches.length, 'Processing batch ' + currentBatchNum + ' of ' + batches.length );
					return runBatchSequentially( currentBatch ).then(
						function () {
							completedBatches = currentBatchNum;
							return runNextBatch();
						}
					);
				}

				activeBatchItems = currentBatch.map(
					function (button) {
						var inputId = button.getAttribute( 'data-for' );
						var input = inputId ? document.getElementById( inputId ) : null;
						var sourceEntryId = input ? input.getAttribute( 'data-i18nly-source-entry-id' ) : '';
						var formIndex = input ? input.getAttribute( 'data-i18nly-form-index' ) : '0';
						var sourceText = input ? input.getAttribute( 'data-i18nly-source-text' ) : '';
						var witness = input ? input.getAttribute( 'data-i18nly-witness' ) : '';

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
					function (item) {
						return item.input && item.request.source_entry_id > 0 && '' !== item.request.source_text;
					}
				);

				if (0 === activeBatchItems.length) {
					completedBatches = currentBatchNum;
					return runNextBatch();
				}

				var body = toFormBody(
					{
						action: batchAction,
						translation_id: config.translationId,
						items_json: JSON.stringify( activeBatchItems.map( function (item) { return item.request; } ) ),
						nonce: batchNonce,
						batch_index: currentBatchNum,
						total_batches: batches.length
					}
				);

				updateProgress( completedBatches, batches.length, 'Processing batch ' + currentBatchNum + ' of ' + batches.length );

				return requestBatch( body, currentBatchNum, 0 ).then(
					function (response) {
						var currentBatchItems = activeBatchItems;
						var payload = response && response.payload ? response.payload : null;

						releaseBatchItems( currentBatchItems );
						activeBatchItems = [];

						if ( response && response.cancelled ) {
							return;
						}

						if ( response && 429 === response.status ) {
							failProgress( 'Translation stopped after repeated rate-limit errors.' );
							return;
						}

						if ( ! payload || ! payload.success || ! payload.data || ! Array.isArray( payload.data.results ) ) {
							failProgress( 'Translation stopped because the batch response was invalid.' );
							return;
						}

						payload.data.results.forEach(
							function (result) {
								if ( ! result || ! result.success ) {
									return;
								}

								var matchedItem = currentBatchItems.find(
									function (item) {
										return item.request.source_entry_id === parseInt( result.source_entry_id || '0', 10 )
											&& item.request.form_index === parseInt( result.form_index || '0', 10 );
									}
								);

								if ( ! matchedItem || ! matchedItem.input ) {
									return;
								}

								matchedItem.input.value = result.translation || '';
								matchedItem.input.dispatchEvent( new Event( 'input', { bubbles: true } ) );

								var badge = getStatusBadgeForInput( matchedItem.input );
								var token = result.review_token || '';

								if ('ai_draft_ok' === token) {
									token = 'draft_ai';
								} else if ('ai_draft_suspect' === token) {
									token = 'draft_ai_suspect';
								} else if ('ai_draft_needs_fix' === token) {
									token = 'draft_ai_needs_fix';
								}

								var tokenMap = {
									draft: { className: 'i18nly-entry-status--draft', label: 'Draft' },
									validated: { className: 'i18nly-entry-status--validated', label: 'Validated' },
									draft_ai: { className: 'i18nly-entry-status--ai-draft', label: 'AI draft' },
									draft_ai_suspect: { className: 'i18nly-entry-status--suspect', label: 'AI draft (suspect)' },
									draft_ai_needs_fix: { className: 'i18nly-entry-status--needs-fix', label: 'AI draft (needs fix)' }
								};

								if ( badge && tokenMap[token] ) {
									badge.className = 'i18nly-entry-status ' + tokenMap[token].className;
									badge.textContent = tokenMap[token].label;
									badge.setAttribute( 'data-status-token', token );
								}
							}
						);

						completedBatches = currentBatchNum;
						return runNextBatch();
					}
				).catch(
					function () {
						releaseBatchItems( activeBatchItems );
						activeBatchItems = [];
						failProgress( 'Translation stopped because the batch request failed.' );
					}
				);
			}

			progressElements = showProgressModal();
			updateProgress( 0, batches.length, 'Processing batch 0 of ' + batches.length );

			runNextBatch();
		}

		function applyBulkAction(action) {
			if ( 'ai_translate_selected' === action ) {
				translateSelectedRowsWithAI();
				return;
			}

			getSelectedRows().forEach(
				function (row) {
					if ( 'copy_source_to_translation' === action ) {
						copySourceToTranslation( row );
						return;
					}

					if ( 'clear_selected_translations' === action ) {
						clearSelectedTranslations( row );
					}
				}
			);
		}

		if ( ! container ) {
			return;
		}

		rowCheckboxes       = container.querySelectorAll( '.i18nly-entry-checkbox' );
		selectAllCheckboxes = container.querySelectorAll( '.i18nly-bulk-select-all' );
		bulkActionSelects   = container.querySelectorAll( '.i18nly-bulk-action-selector' );
		bulkApplyButtons    = container.querySelectorAll( '.i18nly-bulk-apply' );

		selectAllCheckboxes.forEach(
			function (checkbox) {
				checkbox.addEventListener(
					'change',
					function () {
						var checked = checkbox.checked;

						Array.prototype.slice.call( rowCheckboxes ).forEach(
							function (rowCheckbox) {
								var row = rowCheckbox.closest( 'tr' );

								if ( row && row.style.display === 'none' ) {
									return;
								}

								rowCheckbox.checked = checked;
							}
						);

						syncSelectAllState();
					}
				);
			}
		);

		Array.prototype.slice.call( rowCheckboxes ).forEach(
			function (checkbox) {
				checkbox.addEventListener( 'change', syncSelectAllState );
			}
		);

		bulkActionSelects.forEach(
			function (select) {
				select.addEventListener( 'change', updateBulkActionState );
			}
		);

		bulkApplyButtons.forEach(
			function (button) {
				button.addEventListener(
					'click',
					function () {
						var wrapper = button.closest( '.bulkactions' );
						var select  = wrapper ? wrapper.querySelector( '.i18nly-bulk-action-selector' ) : null;

						if ( ! select || '' === select.value ) {
							return;
						}

						applyBulkAction( select.value );
					}
				);
			}
		);

		if ( obsoleteToggle ) {
			obsoleteToggle.addEventListener( 'change', applyObsoleteFilter );
		}

		Array.prototype.slice.call( container.querySelectorAll( '.i18nly-translate-btn' ) ).forEach(
			function (button) {
				if ( config.hasDeeplKey === false ) {
					button.disabled = true;
					button.title    = 'DeepL API key not configured';
				}

				button.addEventListener(
					'click',
					function () {
						translateWithAI( button );
					}
				);
			}
		);

		applyObsoleteFilter();
		updateBulkActionState();
	}

	refreshEntriesTable();
	installEntriesPayloadCompaction();

	postForm(
		toFormBody(
			{
				action: config.generateAction,
				translation_id: config.translationId,
				nonce: config.generateNonce
			}
		)
	).then(
		function (payload) {
			if (payload && payload.success) {
				refreshEntriesTable();
			}
		}
	);
})();
