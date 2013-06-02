<?php
/**
 * XML syntax and type checker.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

class XmlTypeCheck {
	/**
	 * Will be set to true or false to indicate whether the file is
	 * well-formed XML. Note that this doesn't check schema validity.
	 */
	public $wellFormed = false;

	/**
	 * Will be set to true if the optional element filter returned
	 * a match at some point.
	 */
	public $filterMatch = false;

	/**
	 * Name of the document's root element, including any namespace
	 * as an expanded URL.
	 */
	public $rootElement = '';

	/**
	 * @param string $input a filename or string containing the XML element
	 * @param callable $filterCallback (optional)
	 *        Function to call to do additional custom validity checks from the
	 *        SAX element handler event. This gives you access to the element
	 *        namespace, name, and attributes, but not to text contents.
	 *        Filter should return 'true' to toggle on $this->filterMatch
	 * @param boolean $isFile (optional) indicates if the first parameter is a
	 *        filename (default, true) or if it is a string (false)
	 */
	function __construct( $input, $filterCallback = null, $isFile = true ) {
		$this->filterCallback = $filterCallback;
		if ( $isFile ) {
			$this->validateFromFile( $input );
		} else {
			$this->validateFromString( $input );
		}
	}

	/**
	 * Alternative constructor: from filename
	 *
	 * @param string $input a filename or string containing the XML element
	 * @param callable $filterCallback (optional)
	 *        Function to call to do additional custom validity checks from the
	 *        SAX element handler event. This gives you access to the element
	 *        namespace, name, and attributes, but not to text contents.
	 *        Filter should return 'true' to toggle on $this->filterMatch
	 * @return XmlTypeCheck
	 */
	public static function newFromFilename( $fname, $filterCallback = null ) {
		return new self( $fname, $filterCallback, true );
	}

	/**
	 * Alternative constructor: from string
	 *
	 * @param string $input a filename or string containing the XML element
	 * @param callable $filterCallback (optional)
	 *        Function to call to do additional custom validity checks from the
	 *        SAX element handler event. This gives you access to the element
	 *        namespace, name, and attributes, but not to text contents.
	 *        Filter should return 'true' to toggle on $this->filterMatch
	 * @return XmlTypeCheck
	 */
	public static function newFromString( $string, $filterCallback = null ) {
		return new self( $string, $filterCallback, false );
	}

	/**
	 * Get the root element. Simple accessor to $rootElement
	 *
	 * @return string
	 */
	public function getRootElement() {
		return $this->rootElement;
	}

	/**
	 * Get an XML parser with the root element handler.
	 * @see XmlTypeCheck::rootElementOpen()
	 * @return resource a resource handle for the XML parser
	 */
	private function getParser() {
		$parser = xml_parser_create_ns( 'UTF-8' );
		// case folding violates XML standard, turn it off
		xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, false );
		xml_set_element_handler( $parser, array( $this, 'rootElementOpen' ), false );
		return $parser;
	}

	/**
	 * @param string $fname the filename
	 */
	private function validateFromFile( $fname ) {
		$parser = $this->getParser();

		if ( file_exists( $fname ) ) {
			$file = fopen( $fname, "rb" );
			if ( $file ) {
				do {
					$chunk = fread( $file, 32768 );
					$ret = xml_parse( $parser, $chunk, feof( $file ) );
					if ( $ret == 0 ) {
						$this->wellFormed = false;
						fclose( $file );
						xml_parser_free( $parser );
						return;
					}
				} while ( !feof( $file ) );

				fclose( $file );
			}
		}
		$this->wellFormed = true;

		xml_parser_free( $parser );
	}

	/**
	 *
	 * @param string $string the XML-input-string to be checked.
	 */
	private function validateFromString( $string ) {
		$parser = $this->getParser();
		$ret = xml_parse( $parser, $string, true );
		xml_parser_free( $parser );
		if ( $ret == 0 ) {
			$this->wellFormed = false;
			return;
		}
		$this->wellFormed = true;
	}

	/**
	 * @param $parser
	 * @param $name
	 * @param $attribs
	 */
	private function rootElementOpen( $parser, $name, $attribs ) {
		$this->rootElement = $name;

		if ( is_callable( $this->filterCallback ) ) {
			xml_set_element_handler( $parser, array( $this, 'elementOpen' ), false );
			$this->elementOpen( $parser, $name, $attribs );
		} else {
			// We only need the first open element
			xml_set_element_handler( $parser, false, false );
		}
	}

	/**
	 * @param $parser
	 * @param $name
	 * @param $attribs
	 */
	private function elementOpen( $parser, $name, $attribs ) {
		if ( call_user_func( $this->filterCallback, $name, $attribs ) ) {
			// Filter hit!
			$this->filterMatch = true;
		}
	}
}
