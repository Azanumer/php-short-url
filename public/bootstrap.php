<?php
// Shared bootstrap: config + SQLite connection + schema auto-install.
if ( ! file_exists( __DIR__ . '/config.php' ) ) {
    http_response_code( 500 );
    exit( 'Missing public/config.php — copy config.example.php from the repo root and edit it.' );
}
require __DIR__ . '/config.php'; // defines SHORT_DB, ADMIN_TOKEN, BASE_URL

if ( ! defined( 'ADMIN_TOKEN' ) || ADMIN_TOKEN === '' || ADMIN_TOKEN === 'change-me' ) {
    http_response_code( 500 );
    exit( 'Set a strong ADMIN_TOKEN in public/config.php first.' );
}

$pdo = new PDO( 'sqlite:' . SHORT_DB );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$pdo->exec( 'PRAGMA journal_mode = WAL' );

// Install schema on first run.
$tables = $pdo->query( "SELECT name FROM sqlite_master WHERE type='table' AND name='links'" )->fetch();
if ( ! $tables ) {
    $schema = file_get_contents( __DIR__ . '/../db/schema.sql' );
    if ( $schema === false ) {
        http_response_code( 500 );
        exit( 'db/schema.sql not found.' );
    }
    $pdo->exec( $schema );
}

/** Generate a random base62 code. */
function short_random_code( $length = 6 ) {
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $bytes    = random_bytes( $length );
    $code     = '';
    for ( $i = 0; $i < $length; $i++ ) {
        $code .= $alphabet[ ord( $bytes[ $i ] ) % 62 ];
    }
    return $code;
}
