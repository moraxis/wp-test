<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hreflang_Validator {

	private $timeout = 15;
	private $limit_response_size = 1048576; // 1MB limit

	public function parse_sitemap( $url ) {
		$response = wp_safe_remote_get( $url, array(
			'timeout'             => $this->timeout,
			'limit_response_size' => 5242880, // Sitemaps can be up to 5MB generally
			'headers'             => array( 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)' ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			return new WP_Error( 'fetch_failed', "Failed to fetch sitemap. HTTP Status: $status", array( 'status' => $status ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'empty_response', 'Sitemap is empty.' );
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body );
		if ( false === $xml ) {
			return new WP_Error( 'invalid_xml', 'Invalid XML sitemap.' );
		}

		$urls = array();
		$count = 0;
		$limit = 300;

		if ( isset( $xml->url ) ) {
			foreach ( $xml->url as $url_node ) {
				if ( $count >= $limit ) {
					break;
				}

				$loc = (string) $url_node->loc;
				if ( empty( $loc ) ) {
					continue;
				}

				// Check for xhtml:link alternate tags
				$namespaces = $url_node->getNamespaces( true );
				$xhtml = isset( $namespaces['xhtml'] ) ? $url_node->children( $namespaces['xhtml'] ) : null;

				$alternates = array();
				if ( $xhtml && isset( $xhtml->link ) ) {
					foreach ( $xhtml->link as $link ) {
						$attributes = $link->attributes();
						if ( isset( $attributes['rel'] ) && (string) $attributes['rel'] === 'alternate' && isset( $attributes['hreflang'] ) && isset( $attributes['href'] ) ) {
							$alternates[] = array(
								'hreflang' => (string) $attributes['hreflang'],
								'href'     => (string) $attributes['href']
							);
						}
					}
				}

				$urls[] = array(
					'url'        => $loc,
					'alternates' => $alternates
				);
				$count++;
			}
		} elseif ( isset( $xml->sitemap ) ) {
			// Sitemap index, not currently handling recursive parsing deeply to prevent timeouts
			return new WP_Error( 'sitemap_index', 'Provided URL is a Sitemap Index. Please provide a direct sitemap URL.' );
		}

		return array( 'urls' => $urls, 'type' => 'sitemap' );
	}

	public function parse_page( $url ) {
		$response = wp_safe_remote_get( $url, array(
			'timeout'             => $this->timeout,
			'limit_response_size' => $this->limit_response_size,
			'headers'             => array( 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)' ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			return new WP_Error( 'fetch_failed', "Failed to fetch page. HTTP Status: $status", array( 'status' => $status ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$headers = wp_remote_retrieve_headers( $response );

		$alternates = array();

		// Parse HTTP headers for Link: rel="alternate"
		if ( isset( $headers['link'] ) ) {
			$link_headers = is_array( $headers['link'] ) ? $headers['link'] : explode( ',', $headers['link'] );
			foreach ( $link_headers as $link_header ) {
				if ( preg_match( '/<([^>]+)>;\s*rel="?alternate"?;\s*hreflang="?([^"]+)"?/i', $link_header, $matches ) ) {
					$alternates[] = array(
						'href'     => $matches[1],
						'hreflang' => $matches[2],
						'source'   => 'header'
					);
				}
			}
		}

		// Parse HTML head for <link rel="alternate" hreflang="...">
		if ( ! empty( $body ) ) {
			if ( preg_match( '/<head[^>]*>(.*?)<\/head>/is', $body, $head_match ) ) {
				$head_content = $head_match[1];
				if ( preg_match_all( '/<link[^>]+rel=["\']?alternate["\']?[^>]*>/is', $head_content, $link_matches ) ) {
					foreach ( $link_matches[0] as $link_tag ) {
						if ( preg_match( '/hreflang=["\']([^"\']+)["\']/i', $link_tag, $hreflang_match ) && preg_match( '/href=["\']([^"\']+)["\']/i', $link_tag, $href_match ) ) {
							$alternates[] = array(
								'href'     => $href_match[1],
								'hreflang' => $hreflang_match[1],
								'source'   => 'html'
							);
						}
					}
				}
			}
		}

		return array(
			'urls' => array(
				array(
					'url'        => $url,
					'alternates' => $alternates
				)
			),
			'type' => 'page'
		);
	}

	public function validate_target( $source_url, $target_url, $source_lang ) {
		$result = array(
			'status'       => null,
			'errors'       => array(),
			'warnings'     => array(),
			'lang_valid'   => true,
			'has_return'   => false,
			'is_self'      => ( rtrim( $source_url, '/' ) === rtrim( $target_url, '/' ) )
		);

		// 1. Validate Lang/Region code format
		if ( ! $this->is_valid_hreflang( $source_lang ) && $source_lang !== 'x-default' ) {
			$result['lang_valid'] = false;
			$result['errors'][] = "Invalid language/region code format: '{$source_lang}'. Expected ISO 639-1 (and optionally ISO 3166-1 alpha-2).";
		}

		// 2. Fetch Target URL to check status and return tags
		$response = wp_safe_remote_get( $target_url, array(
			'timeout'             => $this->timeout,
			'limit_response_size' => $this->limit_response_size,
			'headers'             => array( 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)' ),
		) );

		if ( is_wp_error( $response ) ) {
			$result['status'] = 'Error';
			$result['errors'][] = "Failed to fetch URL: " . $response->get_error_message();
			return $result;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$result['status'] = $status;

		if ( $status !== 200 ) {
			$result['errors'][] = "Target URL returned HTTP status {$status}.";
		} else {
			// Check for return tag
			$body = wp_remote_retrieve_body( $response );
			$headers = wp_remote_retrieve_headers( $response );

			$has_return = false;
			$source_url_clean = rtrim( $source_url, '/' );

			// Check headers
			if ( isset( $headers['link'] ) ) {
				$link_headers = is_array( $headers['link'] ) ? $headers['link'] : explode( ',', $headers['link'] );
				foreach ( $link_headers as $link_header ) {
					if ( preg_match( '/<([^>]+)>;\s*rel="?alternate"?;\s*hreflang="?([^"]+)"?/i', $link_header, $matches ) ) {
						if ( rtrim( $matches[1], '/' ) === $source_url_clean ) {
							$has_return = true;
							break;
						}
					}
				}
			}

			// Check HTML
			if ( ! $has_return && ! empty( $body ) ) {
				if ( preg_match( '/<head[^>]*>(.*?)<\/head>/is', $body, $head_match ) ) {
					$head_content = $head_match[1];
					if ( preg_match_all( '/<link[^>]+rel=["\']?alternate["\']?[^>]*>/is', $head_content, $link_matches ) ) {
						foreach ( $link_matches[0] as $link_tag ) {
							if ( preg_match( '/href=["\']([^"\']+)["\']/i', $link_tag, $href_match ) ) {
								if ( rtrim( $href_match[1], '/' ) === $source_url_clean ) {
									$has_return = true;
									break;
								}
							}
						}
					}
				}
			}

			$result['has_return'] = $has_return;

			if ( ! $has_return && ! $result['is_self'] ) {
				$result['errors'][] = "Missing return link. The target page does not link back to the source URL.";
			}
		}

		return $result;
	}

	private function is_valid_hreflang( $lang ) {
		// x-default is handled outside
		// Format: language[-region] or language[-script][-region]
		// e.g. en, en-US, zh-Hant-TW
		// We'll do a basic regex for ISO 639-1 (2 letters) or ISO 639-2/3 (3 letters)
		// and ISO 3166-1 alpha-2 (2 letters)
		return preg_match( '/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?(-[a-zA-Z]{2})?$/', $lang );
	}
}