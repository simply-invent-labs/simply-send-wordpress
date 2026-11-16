<?php
/** Regression checks for large attachment routing; all HTTP is mocked. */
require __DIR__ . '/regression.php';
$GLOBALS['options'][Simply_Send_Settings::OPTION_KEY] = $original_settings;
$settings = $original_settings;
$large_file = tempnam( sys_get_temp_dir(), 'ss-large-' );
$other_file = tempnam( sys_get_temp_dir(), 'ss-other-' );
$large_atts = array_merge( $base, array( 'attachments' => array( $large_file ) ) );
$GLOBALS['http_mock'] = function ( $url, $args ) {
	if ( '/attachments' === substr( $url, -12 ) ) {
		$manifest = json_decode( $args['body'], true );
		$uploads = array();
		foreach ( $manifest['attachments'] as $index => $attachment ) {
			$uploads[] = array( 'index' => $index, 'attachmentId' => 'att_' . $index, 'uploadUrl' => 'https://test-bucket.s3.' . ( $GLOBALS['test_aws_region'] ?? 'us-east-1' ) . '.amazonaws.com/file-' . $index . '?X-Amz-Signature=private-test-signature', 'requiredHeaders' => array( 'Content-Length' => (string) $attachment['sizeBytes'], 'x-amz-checksum-sha256' => base64_encode( hex2bin( $attachment['sha256'] ) ) ) );
		}
		$data = array( 'attachmentSubmissionId' => 'sub_' . str_repeat( 'a', 32 ), 'attachmentUploads' => $uploads );
		if ( isset( $GLOBALS['alter_authorization'] ) ) { $data = call_user_func( $GLOBALS['alter_authorization'], $data ); }
		return array( 'code' => $GLOBALS['authorization_code'] ?? 200, 'body' => json_encode( array( 'success' => true, 'data' => $data ) ) );
	}
	return array( 'code' => $GLOBALS['send_code'] ?? 200, 'body' => '{"success":true,"data":{"recipients":[]}}' );
};
try {
	file_put_contents( $large_file, str_repeat( 'A', 1200000 ) );
	file_put_contents( $other_file, 'small attachment' );
	foreach ( array( 'us' => 'us-east-1', 'au' => 'ap-southeast-2', 'ca' => 'ca-central-1', 'eu' => 'eu-central-1' ) as $site => $aws_region ) {
		$settings['region'] = $site;
		$GLOBALS['test_aws_region'] = $aws_region;
		$GLOBALS['requests'] = array();
		check( $mailer->send_from_wp_mail( $large_atts, $settings ), "$site large attachment sends" );
		$requests = $GLOBALS['requests'];
		check( 3 === count( $requests ), "$site authorizes, uploads, and sends" );
		$expected = 'https://' . ( 'us' === $site ? '' : $site . '-' ) . 'tapi.simplysend.dev/';
		check( $expected . 'attachments' === $requests[0][0] && $expected . 'send' === $requests[2][0], "$site API requests stay in selected region" );
		$manifest = json_decode( $requests[0][1]['body'], true );
		check( array( 'attachments' ) === array_keys( $manifest ) && 1200000 === $manifest['attachments'][0]['sizeBytes'] && hash_file( 'sha256', $large_file ) === $manifest['attachments'][0]['sha256'], 'authorization contains only metadata and checksum' );
		check( ! isset( $manifest['attachments'][0]['path'], $manifest['attachments'][0]['content'] ), 'local paths and file contents excluded from authorization' );
		$put = $requests[1][1];
		check( 'PUT' === $put['method'] && 1200000 === strlen( $put['body'] ) && ! isset( $put['headers']['X-Api-Key'], $put['headers']['X-Id'], $put['headers']['Idempotency-Key'] ), 'upload uses raw bytes without API credentials' );
		check( 0 === $put['redirection'] && true === $put['sslverify'], 'upload verifies TLS and forbids redirects' );
		$send = json_decode( $requests[2][1]['body'], true );
		check( 'sub_' . str_repeat( 'a', 32 ) === $send['attachmentSubmissionId'] && ! isset( $send['attachments'] ) && $base['subject'] === $send['subject'], 'send references uploaded files and preserves email' );
		check( $requests[0][1]['headers']['Idempotency-Key'] === $requests[2][1]['headers']['Idempotency-Key'], 'authorization and send share idempotency key' );
	}
	$settings = $original_settings;
	$GLOBALS['test_aws_region'] = 'us-east-1';
	$GLOBALS['requests'] = array();
	check( $mailer->send_from_wp_mail( array_merge( $base, array( 'attachments' => array( $large_file, $other_file ) ) ), $settings ) && 4 === count( $GLOBALS['requests'] ), 'multiple files upload separately before one send' );
	file_put_contents( $large_file, str_repeat( 'A', 1000000 ) );
	$GLOBALS['requests'] = array();
	check( $mailer->send_from_wp_mail( $large_atts, $settings ) && 1 === count( $GLOBALS['requests'] ), 'small email remains on direct send' );
	$inline = json_decode( $GLOBALS['requests'][0][1]['body'], true );
	check( strlen( base64_decode( $inline['attachments'][0]['content'] ) ) === 1000000, 'small attachment is preserved inline' );
	file_put_contents( $large_file, str_repeat( 'A', 1200000 ) );
	$invalid_manifests = array(
		'missing submission' => function ( $d ) { unset( $d['attachmentSubmissionId'] ); return $d; },
		'malformed submission' => function ( $d ) { $d['attachmentSubmissionId'] = '../bad'; return $d; },
		'missing upload' => function ( $d ) { $d['attachmentUploads'] = array(); return $d; },
		'wrong index' => function ( $d ) { $d['attachmentUploads'][0]['index'] = 5; return $d; },
		'wrong checksum' => function ( $d ) { $d['attachmentUploads'][0]['requiredHeaders']['x-amz-checksum-sha256'] = 'bad'; return $d; },
		'wrong length' => function ( $d ) { $d['attachmentUploads'][0]['requiredHeaders']['Content-Length'] = '1'; return $d; },
		'extra header' => function ( $d ) { $d['attachmentUploads'][0]['requiredHeaders']['Authorization'] = 'bad'; return $d; },
		'private host' => function ( $d ) { $d['attachmentUploads'][0]['uploadUrl'] = 'https://127.0.0.1/file'; return $d; },
		'HTTP URL' => function ( $d ) { $d['attachmentUploads'][0]['uploadUrl'] = str_replace( 'https:', 'http:', $d['attachmentUploads'][0]['uploadUrl'] ); return $d; },
		'wrong region' => function ( $d ) { $d['attachmentUploads'][0]['uploadUrl'] = str_replace( 'us-east-1', 'eu-central-1', $d['attachmentUploads'][0]['uploadUrl'] ); return $d; },
		'host suffix trick' => function ( $d ) { $d['attachmentUploads'][0]['uploadUrl'] = 'https://test-bucket.s3.us-east-1.amazonaws.com.evil.example/file'; return $d; },
	);
	foreach ( $invalid_manifests as $name => $alter ) {
		$GLOBALS['alter_authorization'] = $alter;
		$GLOBALS['requests'] = array();
		check( false === $mailer->send_from_wp_mail( $large_atts, $settings ) && 1 === count( $GLOBALS['requests'] ), "$name rejected before upload or send" );
	}
	unset( $GLOBALS['alter_authorization'] );
	foreach ( array( 403, 409, 413, 500 ) as $code ) {
		$GLOBALS['authorization_code'] = $code;
		$GLOBALS['requests'] = array();
		check( false === $mailer->send_from_wp_mail( $large_atts, $settings ) && 1 === count( $GLOBALS['requests'] ), "authorization $code prevents upload and send" );
	}
	unset( $GLOBALS['authorization_code'] );
	foreach ( array( 301, 403, 500 ) as $code ) {
		$GLOBALS['upload_response'] = array( 'code' => $code, 'body' => '' );
		$GLOBALS['requests'] = array();
		check( false === $mailer->send_from_wp_mail( $large_atts, $settings ) && 2 === count( $GLOBALS['requests'] ), "upload $code prevents final send" );
		check( false === strpos( $mailer->get_last_error(), 'private-test-signature' ), 'upload error does not disclose signed URL' );
	}
	unset( $GLOBALS['upload_response'] );
	$GLOBALS['upload_response'] = new WP_Error( 'upload_network', 'private-test-signature' );
	$GLOBALS['requests'] = array();
	check( false === $mailer->send_from_wp_mail( $large_atts, $settings ) && 2 === count( $GLOBALS['requests'] ) && false === strpos( $mailer->get_last_error(), 'private-test-signature' ), 'network upload failure stops send and hides signed URL' );
	unset( $GLOBALS['upload_response'] );
	$GLOBALS['send_code'] = 413;
	$GLOBALS['requests'] = array();
	check( false === $mailer->send_from_wp_mail( $large_atts, $settings ) && 3 === count( $GLOBALS['requests'] ), 'final send size rejection does not fall back or resend' );
	unset( $GLOBALS['send_code'] );
	$memory_limit = ini_get( 'memory_limit' );
	ini_set( 'memory_limit', '24M' );
	$handle = fopen( $large_file, 'w' ); ftruncate( $handle, 5000000 ); fclose( $handle );
	$GLOBALS['requests'] = array();
	check( false === $mailer->send_from_wp_mail( $large_atts, $settings ) && 1 === count( $GLOBALS['requests'] ), 'insufficient PHP memory fails before reading upload bytes' );
	ini_set( 'memory_limit', $memory_limit );
	file_put_contents( $large_file, str_repeat( 'A', 1200000 ) );
	$GLOBALS['alter_authorization'] = function ( $d ) use ( $large_file ) { file_put_contents( $large_file, 'changed' ); return $d; };
	$GLOBALS['requests'] = array();
	check( false === $mailer->send_from_wp_mail( $large_atts, $settings ) && 1 === count( $GLOBALS['requests'] ), 'changed file rejected before upload' );
	unset( $GLOBALS['alter_authorization'] );
	$GLOBALS['requests'] = array();
	check( false === $mailer->send_from_wp_mail( array_merge( $base, array( 'message' => str_repeat( 'B', 2097153 ) ) ), $settings ) && empty( $GLOBALS['requests'] ), 'oversized body rejected before HTTP' );
	$handle = fopen( $large_file, 'w' ); ftruncate( $handle, 40000001 ); fclose( $handle );
	$GLOBALS['requests'] = array();
	check( false === $mailer->send_from_wp_mail( $large_atts, $settings ) && empty( $GLOBALS['requests'] ), 'oversized file rejected before HTTP or reading content' );
	$handle = fopen( $large_file, 'w' ); ftruncate( $handle, 31000000 ); fclose( $handle );
	check( false === $mailer->send_from_wp_mail( $large_atts, $settings ) && empty( $GLOBALS['requests'] ), 'MIME expansion over hard maximum rejected' );
	file_put_contents( $large_file, str_repeat( 'A', 1200000 ) );
	$GLOBALS['alter_authorization'] = function ( $d ) { return array( 'recipients' => array() ); };
	$GLOBALS['requests'] = array();
	check( $mailer->send_from_wp_mail( $large_atts, $settings ) && 1 === count( $GLOBALS['requests'] ), 'completed authorization replay does not upload or send again' );
} finally {
	unset( $GLOBALS['http_mock'], $GLOBALS['alter_authorization'], $GLOBALS['upload_response'] );
	unlink( $large_file );
	unlink( $other_file );
}
echo "All attachment flow checks passed.\n";
