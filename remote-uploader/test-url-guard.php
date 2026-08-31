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

$method = new ReflectionMethod( 'Remote_File_Uploader_AI1WM', 'validate_source_url' );
$method->setAccessible( true );
$obj = ( new ReflectionClass( 'Remote_File_Uploader_AI1WM' ) )->newInstanceWithoutConstructor();

function check( $method, $obj, $url, $should_pass, $label ) {
	$result = $method->invoke( $obj, $url );
	$passed = ( $result === true );
	if ( $passed !== $should_pass ) {
		fwrite( STDERR, sprintf( "FAIL: %s (url=%s) expected %s\n", $label, $url, $should_pass ? 'pass' : 'reject' ) );
		exit( 1 );
	}
	echo sprintf( "ok: %s\n", $label );
}

// Reject
check( $method, $obj, 'file:///etc/passwd',                 false, 'file:// scheme' );
check( $method, $obj, 'gopher://example.com/x.wpress',      false, 'gopher:// scheme' );
check( $method, $obj, 'http://127.0.0.1/backup.wpress',     false, 'loopback IP' );
check( $method, $obj, 'http://169.254.169.254/x.wpress',    false, 'link-local metadata IP' );
check( $method, $obj, 'http://10.0.0.5/backup.wpress',      false, 'private 10/8 IP' );
check( $method, $obj, 'https://example.com/shell.php',      false, 'disallowed .php extension' );
check( $method, $obj, 'https://example.com/no-extension',   false, 'missing extension' );

// Pass (public host, allowed extension)
check( $method, $obj, 'https://example.com/backup.wpress',  true,  'public host + .wpress' );
check( $method, $obj, 'https://example.com/path/site.zip',  true,  'public host + .zip' );

echo "\nall checks passed\n";
