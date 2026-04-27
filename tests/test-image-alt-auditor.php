<?php
require_once dirname( __FILE__ ) . '/mocks/wordpress.php';
require_once dirname( __FILE__ ) . '/../image-alt-auditor/includes/class-parser.php';

class Image_Alt_Auditor_Test {

	public function run() {
		$this->test_parse_html();
		$this->test_resolve_url();
		echo "All Image Alt Auditor tests passed!\n";
	}

	private function assert( $condition, $message ) {
		if ( ! $condition ) {
			throw new Exception( "Test Failed: $message" );
		}
	}

	private function test_parse_html() {
		$parser = new Image_Alt_Auditor_Parser();
		$base_url = 'https://example.com/page/';

		$html = <<<HTML
		<!DOCTYPE html>
		<html>
		<head>
			<style>
				.bg1 { background-image: url('bg1.jpg'); }
				.bg2 { background: url("https://example.com/bg2.png") no-repeat; }
			</style>
		</head>
		<body>
			<img src="img1.jpg" alt="Valid alt">
			<img src="img2.jpg"> <!-- Missing alt -->
			<img src="img3.jpg" alt=""> <!-- Empty alt -->
			<div style="background-image: url(/inline-bg.jpg)"></div>
			<img data-src="lazy.jpg"> <!-- Lazy loaded missing alt -->
		</body>
		</html>
HTML;

		$findings = $parser->parse_html( $html, $base_url );

		$this->assert( count( $findings ) === 6, 'Should find exactly 6 issues (2 imgs missing alt, 1 lazy img missing alt, 1 inline bg, 2 style bgs). Found: ' . count($findings) );

		// Check the findings
		$issues_found = array_map( function($f) { return $f['url']; }, $findings );

		$this->assert( in_array( 'https://example.com/page/img2.jpg', $issues_found ), 'Failed to find img2.jpg' );
		$this->assert( in_array( 'https://example.com/page/img3.jpg', $issues_found ), 'Failed to find img3.jpg' );
		$this->assert( in_array( 'https://example.com/inline-bg.jpg', $issues_found ), 'Failed to find inline-bg.jpg' );
		$this->assert( in_array( 'https://example.com/page/lazy.jpg', $issues_found ), 'Failed to find lazy.jpg' );
		$this->assert( in_array( 'https://example.com/page/bg1.jpg', $issues_found ), 'Failed to find bg1.jpg' );
		$this->assert( in_array( 'https://example.com/bg2.png', $issues_found ), 'Failed to find bg2.png' );
	}

	private function test_resolve_url() {
		$parser = new Image_Alt_Auditor_Parser();
		$base = 'https://example.com/dir1/dir2/page.html';

		$this->assert( $parser->resolve_url( 'http://absolute.com/img.jpg', $base ) === 'http://absolute.com/img.jpg', 'Absolute HTTP failed' );
		$this->assert( $parser->resolve_url( 'https://absolute.com/img.jpg', $base ) === 'https://absolute.com/img.jpg', 'Absolute HTTPS failed' );
		$this->assert( $parser->resolve_url( '//protocol-relative.com/img.jpg', $base ) === 'https://protocol-relative.com/img.jpg', 'Protocol relative failed' );
		$this->assert( $parser->resolve_url( '/root-relative/img.jpg', $base ) === 'https://example.com/root-relative/img.jpg', 'Root relative failed' );
		$this->assert( $parser->resolve_url( 'img.jpg', $base ) === 'https://example.com/dir1/dir2/img.jpg', 'Document relative failed' );
		$this->assert( $parser->resolve_url( './img.jpg', $base ) === 'https://example.com/dir1/dir2/img.jpg', 'Document relative ./ failed' );
		$this->assert( $parser->resolve_url( '../img.jpg', $base ) === 'https://example.com/dir1/img.jpg', 'Document relative ../ failed' );
		$this->assert( $parser->resolve_url( '../../img.jpg', $base ) === 'https://example.com/img.jpg', 'Document relative ../../ failed' );
	}
}

$test = new Image_Alt_Auditor_Test();
$test->run();
