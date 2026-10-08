<?php
// Redirector: /abc123 -> 301 to the stored target, with hit logging.
require __DIR__ . '/bootstrap.php';

$code = isset( $_GET['c'] ) ? (string) $_GET['c'] : '';
if ( ! preg_match( '/^[A-Za-z0-9]{4,10}$/', $code ) ) {
    http_response_code( 404 );
    exit( 'Not found' );
}

$stmt = $pdo->prepare(
    'SELECT id, target FROM links
     WHERE code = ? AND (expires_at IS NULL OR expires_at > strftime("%s", "now"))
     LIMIT 1'
);
$stmt->execute( array( $code ) );
$row = $stmt->fetch( PDO::FETCH_ASSOC );

if ( ! $row ) {
    http_response_code( 404 );
    exit( 'Not found' );
}

// Log the hit, then redirect.
$pdo->prepare( 'INSERT INTO hits (link_id, hit_at, ip, referer, ua) VALUES (?, ?, ?, ?, ?)' )
    ->execute( array(
        $row['id'],
        time(),
        substr( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), 0, 45 ),
        substr( (string) ( $_SERVER['HTTP_REFERER'] ?? '' ), 0, 255 ),
        substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
    ) );
$pdo->prepare( 'UPDATE links SET clicks = clicks + 1 WHERE id = ?' )
    ->execute( array( $row['id'] ) );

header( 'Location: ' . $row['target'], true, 301 );
exit;
