<?php
/**
 * Standalone check for Remote_File_Uploader_AI1WM::validate_source_url().
 * Run: php remote-uploader/test-url-guard.php
 *
 * ponytail: no framework. Shims the few WP functions the method touches, then
 * drives it through reflection. Fails loudly if the SSRF / extension guard breaks.
 */

define( 'ABSPATH', __DIR__ );

function __( $s, $d = null ) { return $s; }
function apply_filters( $tag, $value ) { return $value; }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

class WP_Error {
	public $code;
	public function __construct( $code = '', $msg = '' ) { $this->code = $code; }
	public function get_error_message() { return $this->code; }
}

require __DIR__ . '/class-remote-file-uploader.php';

$obj = ( new ReflectionClass( 'Remote_File_Uploader_AI1WM' ) )->newInstanceWithoutConstructor();

function check( $obj, $method_name, $url, $should_pass, $label ) {
	$method = new ReflectionMethod( 'Remote_File_Uploader_AI1WM', $method_name );
	$method->setAccessible( true );
	$passed = ! is_wp_error( $method->invoke( $obj, $url ) );
	if ( $passed !== $should_pass ) {
		fwrite( STDERR, sprintf( "FAIL: %s (url=%s) expected %s\n", $label, $url, $should_pass ? 'pass' : 'reject' ) );
		exit( 1 );
	}
	echo sprintf( "ok: %s\n", $label );
}

// Host guard (runs on every redirect hop)
check( $obj, 'validate_source_url', 'file:///etc/passwd',              false, 'file:// scheme' );
check( $obj, 'validate_source_url', 'gopher://example.com/x.wpress',   false, 'gopher:// scheme' );
check( $obj, 'validate_source_url', 'http://127.0.0.1/backup.wpress',  false, 'loopback IP' );
check( $obj, 'validate_source_url', 'http://169.254.169.254/x.wpress', false, 'link-local metadata IP' );
check( $obj, 'validate_source_url', 'http://10.0.0.5/backup.wpress',   false, 'private 10/8 IP' );
check( $obj, 'validate_source_url', 'http://93.184.215.14/a',          true,  'public IP, any path (redirect hop)' );

// Extension guard (original URL only)
check( $obj, 'validate_extension', 'https://example.com/shell.php',     false, 'disallowed .php extension' );
check( $obj, 'validate_extension', 'https://example.com/site.zip',      false, '.zip rejected' );
check( $obj, 'validate_extension', 'https://example.com/no-extension',  false, 'missing extension' );
check( $obj, 'validate_extension', 'https://example.com/backup.WPRESS', true,  '.wpress (case-insensitive)' );

echo "\nall checks passed\n";
