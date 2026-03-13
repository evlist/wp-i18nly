<?php
/**
 * Minimal Gettext stubs for Psalm static analysis.
 *
 * @package I18nly
 */

declare( strict_types=1 );

namespace Gettext\Generator;

class PoGenerator {
	/**
	 * @param mixed  $translations Translations object.
	 * @param string $destination_file Destination file path.
	 * @return bool
	 */
	public function generateFile( $translations, $destination_file ) {
		unset( $translations, $destination_file );
		return true;
	}
}

namespace Gettext\Loader;

class PoLoader {
	/**
	 * @param string $path POT/PO file path.
	 * @return \Gettext\Translations
	 */
	public function loadFile( $path ) {
		unset( $path );
		return \Gettext\Translations::create();
	}
}

namespace Gettext;

class Headers {
	/**
	 * @return array<string, string>
	 */
	public function toArray() {
		return array();
	}

	/**
	 * @param string $name Header name.
	 * @param string $value Header value.
	 * @return void
	 */
	public function set( $name, $value ) {
		unset( $name, $value );
	}
}

class CommentsCollection {
	/**
	 * @return array<int, string>
	 */
	public function toArray() {
		return array();
	}

	/**
	 * @param string $value Comment value.
	 * @return void
	 */
	public function add( $value ) {
		unset( $value );
	}
}

class ReferencesCollection {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function toArray() {
		return array();
	}

	/**
	 * @param string   $file File path.
	 * @param int|null $line Optional line number.
	 * @return void
	 */
	public function add( $file, $line = null ) {
		unset( $file, $line );
	}
}

class FlagsCollection {
	/**
	 * @return array<int, string>
	 */
	public function toArray() {
		return array();
	}

	/**
	 * @param string $value Flag value.
	 * @return void
	 */
	public function add( $value ) {
		unset( $value );
	}
}

class Translation {
	/**
	 * @param string|null $context Context.
	 * @param string      $original Original string.
	 * @return self
	 */
	public static function create( $context, $original ) {
		unset( $context, $original );
		return new self();
	}

	/**
	 * @return string
	 */
	public function getOriginal() {
		return '';
	}

	/**
	 * @return string|null
	 */
	public function getContext() {
		return null;
	}

	/**
	 * @return string|null
	 */
	public function getPlural() {
		return null;
	}

	/**
	 * @return CommentsCollection
	 */
	public function getComments() {
		return new CommentsCollection();
	}

	/**
	 * @return CommentsCollection
	 */
	public function getExtractedComments() {
		return new CommentsCollection();
	}

	/**
	 * @return ReferencesCollection
	 */
	public function getReferences() {
		return new ReferencesCollection();
	}

	/**
	 * @return FlagsCollection
	 */
	public function getFlags() {
		return new FlagsCollection();
	}

	/**
	 * @param string $value Plural value.
	 * @return void
	 */
	public function setPlural( $value ) {
		unset( $value );
	}
}

class Translations implements \IteratorAggregate {
	/**
	 * @return self
	 */
	public static function create() {
		return new self();
	}

	/**
	 * @return Headers
	 */
	public function getHeaders() {
		return new Headers();
	}

	/**
	 * @param Translation $translation Translation object.
	 * @return void
	 */
	public function add( Translation $translation ) {
		unset( $translation );
	}

	/**
	 * @return \ArrayIterator<int, Translation>
	 */
	public function getIterator(): \Traversable {
		return new \ArrayIterator( array() );
	}
}
