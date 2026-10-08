/**
 * SPDX-FileCopyrightText: 2026 Eric van der Vlist <vdv@dyomedea.com>
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @package I18nly
 */

( function ( window ) {
	'use strict';

	var namespace = window.I18nly = window.I18nly || {};

	var QUERY_KEY_ENTRY         = 'i18nly_filter_entries';
	var QUERY_KEY_QUALITY       = 'i18nly_filter_statuses';
	var QUERY_KEY_PROVENANCE    = 'i18nly_filter_provenance';
	var QUERY_KEY_SEARCH_TEXT   = 'i18nly_filter_search';
	var QUERY_KEY_SEARCH_FIELDS = 'i18nly_filter_search_fields';
	var NONE_TOKEN              = '__none__';

	/**
	 * Lists the checked values of a group of filter checkboxes, lowercased.
	 *
	 * @param {NodeList|Element[]} checkboxes Filter checkboxes.
	 * @return {string[]}
	 */
	function getCheckedValues( checkboxes ) {
		return Array.prototype.slice.call( checkboxes || [] ).filter(
			function ( checkbox ) {
				return checkbox.checked;
			}
		).map(
			function ( checkbox ) {
				return String( checkbox.value || '' ).toLowerCase().trim();
			}
		).filter(
			function ( value ) {
				return '' !== value;
			}
		);
	}

	/**
	 * Lists every value offered by a group of filter checkboxes, lowercased.
	 *
	 * @param {NodeList|Element[]} checkboxes Filter checkboxes.
	 * @return {string[]}
	 */
	function getOfferedValues( checkboxes ) {
		return Array.prototype.slice.call( checkboxes || [] ).map(
			function ( checkbox ) {
				return String( checkbox.value || '' ).toLowerCase().trim();
			}
		).filter(
			function ( value ) {
				return '' !== value;
			}
		);
	}

	/**
	 * Reads the entry filters displayed above a resource table and keeps them in the page query string.
	 */
	class EntryFilterBar {
		/**
		 * @param {Document} root Document holding the filter controls.
		 */
		constructor( root ) {
			var doc = root || window.document;

			this.entryStatusFilters   = doc.querySelectorAll( '.i18nly-filter-entry-status' );
			this.qualityStatusFilters = doc.querySelectorAll( '.i18nly-filter-quality-status' );
			this.provenanceFilters    = doc.querySelectorAll( '.i18nly-filter-provenance' );
			this.searchInput          = doc.getElementById( 'i18nly-filter-search-text' );
			this.searchFieldFilters   = doc.querySelectorAll( '.i18nly-filter-search-field' );
			this.container            = doc.querySelector( '.i18nly-entry-filters' );
		}

		/**
		 * Returns the current filter selection.
		 *
		 * @return {{entryStatuses: string[], qualityStatuses: string[], provenances: string[], searchText: string, searchFields: string[]}}
		 */
		getSelection() {
			return {
				entryStatuses: getCheckedValues( this.entryStatusFilters ),
				qualityStatuses: getCheckedValues( this.qualityStatusFilters ),
				provenances: getCheckedValues( this.provenanceFilters ),
				searchText: String( this.searchInput && this.searchInput.value ? this.searchInput.value : '' ).toLowerCase().trim(),
				searchFields: getCheckedValues( this.searchFieldFilters )
			};
		}

		/**
		 * Calls back whenever the user changes a filter, after the query string was updated.
		 *
		 * @param {Function} callback Change callback.
		 * @return {void}
		 */
		onChange( callback ) {
			var self = this;

			function handle() {
				self.persistToQuery();
				callback();
			}

			[ this.entryStatusFilters, this.qualityStatusFilters, this.provenanceFilters, this.searchFieldFilters ].forEach(
				function ( group ) {
					Array.prototype.slice.call( group ).forEach(
						function ( checkbox ) {
							checkbox.addEventListener( 'change', handle );
						}
					);
				}
			);

			if ( this.searchInput ) {
				this.searchInput.addEventListener( 'input', handle );
			}
		}

		/**
		 * Restores the filters from the page query string.
		 *
		 * @return {void}
		 */
		restoreFromQuery() {
			var params;
			var rawSearchText;

			this.restoreGroupFromQuery( this.entryStatusFilters, QUERY_KEY_ENTRY );
			this.restoreGroupFromQuery( this.qualityStatusFilters, QUERY_KEY_QUALITY );
			this.restoreGroupFromQuery( this.provenanceFilters, QUERY_KEY_PROVENANCE );

			if ( ! this.searchInput || 'function' !== typeof window.URLSearchParams ) {
				return;
			}

			params        = new window.URLSearchParams( window.location.search || '' );
			rawSearchText = params.get( QUERY_KEY_SEARCH_TEXT );

			if ( null !== rawSearchText ) {
				this.searchInput.value = String( rawSearchText );
			}

			this.restoreGroupFromQuery( this.searchFieldFilters, QUERY_KEY_SEARCH_FIELDS );
		}

		/**
		 * Restores one checkbox group from its query string parameter.
		 *
		 * @param {NodeList} checkboxes Filter checkboxes.
		 * @param {string}   queryKey Query string parameter.
		 * @return {void}
		 */
		restoreGroupFromQuery( checkboxes, queryKey ) {
			var params;
			var rawValue;
			var allowedValues;
			var selectedValues;

			if ( 'function' !== typeof window.URLSearchParams ) {
				return;
			}

			params   = new window.URLSearchParams( window.location.search || '' );
			rawValue = params.get( queryKey );

			if ( null === rawValue ) {
				return;
			}

			allowedValues = getOfferedValues( checkboxes );

			if ( NONE_TOKEN === rawValue ) {
				Array.prototype.slice.call( checkboxes || [] ).forEach(
					function ( checkbox ) {
						checkbox.checked = false;
					}
				);
				return;
			}

			selectedValues = String( rawValue )
				.split( ',' )
				.map(
					function ( token ) {
						return token.toLowerCase().trim();
					}
				)
				.filter(
					function ( token ) {
						return '' !== token && allowedValues.indexOf( token ) !== -1;
					}
				);

			Array.prototype.slice.call( checkboxes || [] ).forEach(
				function ( checkbox ) {
					var checkboxValue = String( checkbox.value || '' ).toLowerCase().trim();

					checkbox.checked = selectedValues.indexOf( checkboxValue ) !== -1;
				}
			);
		}

		/**
		 * Writes the filters into the page query string without reloading the page.
		 *
		 * @return {void}
		 */
		persistToQuery() {
			var params;
			var entryStatuses;
			var qualityStatuses;
			var provenances;
			var searchText;
			var searchFields;

			if ( 'function' !== typeof window.URLSearchParams || ! window.history || 'function' !== typeof window.history.replaceState ) {
				return;
			}

			params          = new window.URLSearchParams( window.location.search || '' );
			entryStatuses   = getCheckedValues( this.entryStatusFilters );
			qualityStatuses = getCheckedValues( this.qualityStatusFilters );
			provenances     = getCheckedValues( this.provenanceFilters );
			searchText      = String( this.searchInput && this.searchInput.value ? this.searchInput.value : '' ).trim();
			searchFields    = getCheckedValues( this.searchFieldFilters );

			params.set( QUERY_KEY_ENTRY, entryStatuses.length > 0 ? entryStatuses.join( ',' ) : NONE_TOKEN );
			params.set( QUERY_KEY_QUALITY, qualityStatuses.length > 0 ? qualityStatuses.join( ',' ) : NONE_TOKEN );
			params.set( QUERY_KEY_PROVENANCE, provenances.length > 0 ? provenances.join( ',' ) : NONE_TOKEN );

			if ( '' === searchText ) {
				params.delete( QUERY_KEY_SEARCH_TEXT );
			} else {
				params.set( QUERY_KEY_SEARCH_TEXT, searchText );
			}

			params.set( QUERY_KEY_SEARCH_FIELDS, searchFields.length > 0 ? searchFields.join( ',' ) : NONE_TOKEN );

			window.history.replaceState( null, '', String( window.location.pathname || '' ) + '?' + params.toString() + String( window.location.hash || '' ) );
		}
	}

	namespace.EntryFilterBar = EntryFilterBar;
} )( window );
