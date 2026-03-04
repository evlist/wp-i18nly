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
			}
		);
	}

	refreshEntriesTable();

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
