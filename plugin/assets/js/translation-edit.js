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
			var row = input.closest( 'tr' );
			var badge = row ? row.querySelector( '.i18nly-entry-status' ) : null;
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
			badge.removeAttribute( 'data-status-token' );
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

				var row = input.closest( 'tr' );
				var badge = row ? row.querySelector( '.i18nly-entry-status' ) : null;
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

					var row = input.closest( 'tr' );
					var badge = row ? row.querySelector( '.i18nly-entry-status' ) : null;
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
						badge.removeAttribute( 'data-status-token' );
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
			if ( config.hasDeeplKey === false ) {
				return;
			}

			getSelectedRows().forEach(
				function (row) {
					Array.prototype.slice.call( row.querySelectorAll( '.i18nly-translate-btn' ) ).forEach(
						function (button) {
							translateWithAI( button );
						}
					);
				}
			);
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
