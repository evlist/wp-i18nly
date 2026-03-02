<?php

// SPDX-FileCopyrightText: 2025, 2026 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later OR MIT

/**
 * Dev-only: disable WordPress canonical redirects to avoid "-443" hops in Codespaces.
 */

add_filter('redirect_canonical', '__return_false', 100);

function cs_grafting_forwarded_host() {
	$forwarded = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '';
	if ('' === $forwarded) {
		return '';
	}

	$parts = array_map('trim', explode(',', $forwarded));
	$host  = end($parts);

	if (! is_string($host)) {
		return '';
	}

	$host = strtolower(trim($host));

	if ('' === $host) {
		return '';
	}

	return $host;
}

function cs_grafting_forwarded_scheme() {
	$forwarded_proto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));

	if ('https' === $forwarded_proto || 'http' === $forwarded_proto) {
		return $forwarded_proto;
	}

	if (! empty($_SERVER['HTTPS']) && 'off' !== strtolower((string) $_SERVER['HTTPS'])) {
		return 'https';
	}

	return 'http';
}

function cs_grafting_normalize_forwarded_server_vars() {
	$public_host = cs_grafting_forwarded_host();
	if ('' !== $public_host) {
		$_SERVER['HTTP_HOST']   = $public_host;
		$_SERVER['SERVER_NAME'] = $public_host;
	}

	$public_scheme = cs_grafting_forwarded_scheme();
	if ('https' === $public_scheme) {
		$_SERVER['HTTPS']      = 'on';
		$_SERVER['SERVER_PORT'] = '443';
	} else {
		$_SERVER['HTTPS']      = 'off';
		$_SERVER['SERVER_PORT'] = '80';
	}
}

cs_grafting_normalize_forwarded_server_vars();

function cs_grafting_rewrite_local_redirect($location) {
	if (! is_string($location) || '' === $location) {
		return $location;
	}

	$target = wp_parse_url($location);
	if (! is_array($target)) {
		return $location;
	}

	$target_host = strtolower((string) ($target['host'] ?? ''));
	if (! in_array($target_host, array('localhost', '127.0.0.1', '::1'), true)) {
		return $location;
	}

	$public_host = cs_grafting_forwarded_host();
	if ('' === $public_host) {
		return $location;
	}

	$target['host'] = $public_host;
	$target['scheme'] = cs_grafting_forwarded_scheme();
	unset($target['port']);

	$rebuilt = (string) ($target['scheme'] ?? 'http') . '://' . $target['host'];

	if (isset($target['user']) && '' !== $target['user']) {
		$rebuilt = (string) ($target['scheme'] ?? 'http') . '://' . $target['user'];
		if (isset($target['pass']) && '' !== $target['pass']) {
			$rebuilt .= ':' . $target['pass'];
		}
		$rebuilt .= '@' . $target['host'];
	}

	$path = (string) ($target['path'] ?? '');
	$rebuilt .= '' !== $path ? $path : '/';

	if (isset($target['query']) && '' !== (string) $target['query']) {
		$rebuilt .= '?' . $target['query'];
	}

	if (isset($target['fragment']) && '' !== (string) $target['fragment']) {
		$rebuilt .= '#' . $target['fragment'];
	}

	return $rebuilt;
}

add_filter('wp_redirect', 'cs_grafting_rewrite_local_redirect', 100, 1);

function cs_grafting_print_admin_url_rewrite_script() {
	$public_host = cs_grafting_forwarded_host();
	if ('' === $public_host) {
		return;
	}

	$public_scheme = cs_grafting_forwarded_scheme();
	?>
	<script>
	(function () {
		const publicHost = <?php echo wp_json_encode($public_host); ?>;
		const publicScheme = <?php echo wp_json_encode($public_scheme); ?>;

		const localhostHosts = new Set(['localhost', '127.0.0.1', '::1']);

		function normalizeUrlValue(input) {
			if (!input || typeof input !== 'string') {
				return input;
			}

			if (!/^https?:\/\//i.test(input)) {
				return input;
			}

			try {
				const url = new URL(input, window.location.href);
				if (!localhostHosts.has(url.hostname)) {
					return input;
				}
				url.protocol = publicScheme + ':';
				url.host = publicHost;
				return url.toString();
			} catch (error) {
				return input;
			}
		}

		function patchNodeUrl(node, attr) {
			const original = node.getAttribute(attr);
			const normalized = normalizeUrlValue(original);
			if (normalized && normalized !== original) {
				node.setAttribute(attr, normalized);
			}
		}

		function patchAll() {
			document.querySelectorAll('a[href]').forEach((node) => patchNodeUrl(node, 'href'));
			document.querySelectorAll('form[action]').forEach((node) => patchNodeUrl(node, 'action'));
		}

		patchAll();
		document.addEventListener('click', patchAll, true);
		document.addEventListener('submit', patchAll, true);
	})();
	</script>
	<?php
}

add_action('admin_head', 'cs_grafting_print_admin_url_rewrite_script', 100);