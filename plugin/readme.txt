=== I18nly ===
Contributors: evlist
Tags: translation, localization, i18n
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Because translating a plugin in WordPress should be as simple as writing a blog post.

== Description ==

I18nly helps manage WordPress localization workflows while keeping compatibility
with WordPress standards. Translations are managed in the admin as dedicated
items, so that translators work on the translation itself rather than on gettext
files and build steps.

Features of the current development version:

* translations as dedicated admin items, with list, add and edit screens,
* import of the translatable strings of an installed plugin,
* plural-aware editing, with the plural forms of each language explained,
* download of the translations as PO, MO and JSON (JavaScript) files, or installation on the site in one click,
* optional machine translation with DeepL (single strings and batches), with
  monthly usage display, quota protection and rate-limit handling.

This is a work in progress.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/i18nly` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Open "Translations" from the admin sidebar. The DeepL settings are under "Settings > Translations".

== Changelog ==

= 0.1.1 =
* Download of translations as PO, MO and JSON files, and installation in the languages directory.
* Translations and glossaries stored as linguistic resources.
* Plural forms of each language explained to translators.
* DeepL usage gauge, quota protection and adaptive throttling.
* Security: translations are restricted to administrators and the source plugin is validated.
* Optional deletion of all data when the plugin is deleted, and DeepL key definable in wp-config.php (`I18NLY_DEEPL_API_KEY`).
* Translations are stored exactly as typed (HTML, line breaks and spacing are no longer altered).

= 0.1.0 =
* Initial public scaffold with admin workspace entry.
