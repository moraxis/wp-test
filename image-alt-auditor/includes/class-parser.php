<?php
/**
 * Core logic for fetching and parsing the target URL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Alt_Auditor_Parser {

	/**
	 * Perform the audit on the given URL
	 *
	 * @param string $url The URL to audit
	 * @return array|WP_Error Result of the audit or WP_Error on failure
	 */
	public function audit_url( $url ) {
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid_url', 'The provided URL is invalid or empty.' );
		}

		$args = array(
			'timeout'     => 15,
			'limit_response_size' => 1048576, // 1MB limit to prevent resource exhaustion
			'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ImageAltAuditor/1.0',
		);

		$response = wp_safe_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fetch_error', 'Cannot fetch URL: ' . $response->get_error_message() . '. Please check if the URL is correct and accessible.' );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new WP_Error( 'http_error', sprintf( 'Cannot fetch URL. The server responded with status code %d. Please check the URL and try again.', $status_code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'empty_response', 'Cannot fetch URL: The page returned an empty response.' );
		}

		return $this->parse_html( $body, $url );
	}

	/**
	 * Parse HTML to extract images missing alt attributes and background images
	 *
	 * @param string $html The HTML content to parse
	 * @param string $base_url The URL of the page (to resolve relative URLs)
	 * @return array Array of findings
	 */
	public function parse_html( $html, $base_url ) {
		$findings = array();

		// Suppress DOMDocument warnings for malformed HTML
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOBLANKS | LIBXML_NOERROR );
		libxml_clear_errors();

		if ( ! $loaded ) {
			// If DOMDocument fails entirely, return empty array as fallback, though rare.
			return $findings;
		}

		$xpath = new DOMXPath( $dom );

		// 1. Find <img> tags without alt or with empty alt
		$images = $xpath->query( '//img' );
		if ( $images ) {
			foreach ( $images as $img ) {
				$src = $img->getAttribute( 'src' );
				// Also check common lazy-loading attributes
				if ( empty( $src ) ) {
					$src = $img->getAttribute( 'data-src' );
				}

				if ( empty( $src ) ) {
					continue; // Ignore images without any source
				}

				$has_alt = $img->hasAttribute( 'alt' );
				$alt_value = $img->getAttribute( 'alt' );

				if ( ! $has_alt || trim( $alt_value ) === '' ) {
					$findings[] = array(
						'type' => 'img',
						'url'  => $this->resolve_url( $src, $base_url ),
						'issue'=> ! $has_alt ? 'Missing alt attribute' : 'Empty alt attribute',
					);
				}
			}
		}

		// 2. Find elements with inline style containing background-image
		// Looking for anything with a style attribute
		$styled_elements = $xpath->query( '//*[@style]' );
		if ( $styled_elements ) {
			foreach ( $styled_elements as $el ) {
				$style = $el->getAttribute( 'style' );
				if ( preg_match( '/background(?:-image)?\s*:[^;]*url\s*\(\s*[\'"]?(.*?)[\'"]?\s*\)/i', $style, $matches ) ) {
					$bg_url = $matches[1];
					if ( ! empty( $bg_url ) && strpos( $bg_url, 'data:' ) !== 0 ) {
						$findings[] = array(
							'type'  => 'inline_bg',
							'url'   => $this->resolve_url( $bg_url, $base_url ),
							'issue' => 'Inline CSS background image (Requires manual review)',
						);
					}
				}
			}
		}

		// 3. Find background images in <style> blocks
		$style_blocks = $xpath->query( '//style' );
		if ( $style_blocks ) {
			foreach ( $style_blocks as $style_block ) {
				$css = $style_block->nodeValue;
				if ( preg_match_all( '/background(?:-image)?\s*:[^;}]*url\s*\(\s*[\'"]?(.*?)[\'"]?\s*\)/i', $css, $matches ) ) {
					foreach ( $matches[1] as $bg_url ) {
						if ( ! empty( $bg_url ) && strpos( $bg_url, 'data:' ) !== 0 ) {
							$findings[] = array(
								'type'  => 'style_bg',
								'url'   => $this->resolve_url( $bg_url, $base_url ),
								'issue' => 'CSS background image in <style> tag (Requires manual review)',
							);
						}
					}
				}
			}
		}

		return $findings;
	}

	/**
	 * Resolve a potentially relative URL to an absolute URL based on the base URL
	 *
	 * @param string $url The URL to resolve
	 * @param string $base The base URL
	 * @return string Absolute URL
	 */
	public function resolve_url( $url, $base ) {
		// If it's already absolute (http, https, data), return it
		if ( preg_match( '#^(?:http|https|data):#i', $url ) ) {
			return $url;
		}

		// Protocol-relative URL
		if ( strpos( $url, '//' ) === 0 ) {
			$scheme = parse_url( $base, PHP_URL_SCHEME );
			$scheme = $scheme ? $scheme : 'http';
			return $scheme . ':' . $url;
		}

		$base_parts = parse_url( $base );
		$scheme = isset( $base_parts['scheme'] ) ? $base_parts['scheme'] . '://' : 'http://';
		$host = isset( $base_parts['host'] ) ? $base_parts['host'] : '';
		$port = isset( $base_parts['port'] ) ? ':' . $base_parts['port'] : '';
		$base_path = isset( $base_parts['path'] ) ? $base_parts['path'] : '/';

		// Root-relative URL
		if ( strpos( $url, '/' ) === 0 ) {
			return $scheme . $host . $port . $url;
		}

		// Document-relative URL
		// Strip the file from the base path if it has one
		$dir = ( substr( $base_path, -1 ) === '/' ) ? $base_path : dirname( $base_path );
		$dir = ( $dir === '.' || $dir === '\\' ) ? '/' : $dir;
		if ( substr( $dir, -1 ) !== '/' ) {
			$dir .= '/';
		}

		$absolute_path = $dir . $url;

		// Resolve ../ and ./
		$path_parts = explode( '/', $absolute_path );
		$resolved_parts = array();
		foreach ( $path_parts as $part ) {
			if ( $part === '.' || $part === '' ) {
				continue;
			}
			if ( $part === '..' ) {
				array_pop( $resolved_parts );
			} else {
				$resolved_parts[] = $part;
			}
		}

		return $scheme . $host . $port . '/' . implode( '/', $resolved_parts );
	}
}
