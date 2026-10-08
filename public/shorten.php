<?php
// Admin API (JSON). Authenticate with header X-Admin-Token: <ADMIN_TOKEN>
// or POST field "token".
//
//   POST action=create&url=https://example.com/very/long&code=mylink&note=...&expires_days=30
//   GET  action=stats&code=mylink          -> clicks + recent hits
//   GET  action=list&limit=50              -> newest links
require __DIR__ . '/bootstrap.php';

header( 'Content-Type: application/json' );

$token = isset( $_SERVER['HTTP_X_ADMIN_TOKEN'] )
    ? (string) $_SERVER['HTTP_X_ADMIN_TOKEN']
    : (string) ( $_POST['token'] ?? $_GET['token'] ?? '' );

if ( ! hash_equals( ADMIN_TOKEN, $token ) ) {
    http_response_code( 403 );
    echo json_encode( array( 'error' => 'forbidden' ) );
    exit;
}

/** Tiny per-IP throttle: max 60 link creations per hour. */
function short_throttle() {
    $dir  = sys_get_temp_dir() . '/shorturl-throttle';
    if ( ! is_dir( $dir ) ) {
        mkdir( $dir, 0700, true );
    }
    $file = $dir . '/' . md5( $_SERVER['REMOTE_ADDR'] ?? 'cli' ) . '.json';
    $now  = time();
    $data = array( 'window' => $now, 'count' => 0 );
    if ( file_exists( $file ) ) {
        $data = json_decode( (string) file_get_contents( $file ), true ) ?: $data;
        if ( $now - (int) $data['window'] > 3600 ) {
            $data = array( 'window' => $now, 'count' => 0 );
        }
    }
    $data['count']++;
    file_put_contents( $file, json_encode( $data ), LOCK_EX );
    return $data['count'] <= 60;
}

$action = (string) ( $_REQUEST['action'] ?? '' );

if ( $action === 'create' ) {
    if ( ! short_throttle() ) {
        http_response_code( 429 );
        echo json_encode( array( 'error' => 'rate limited, try later' ) );
        exit;
    }

    $url = trim( (string) ( $_REQUEST['url'] ?? '' ) );
    if ( ! filter_var( $url, FILTER_VALIDATE_URL ) || ! preg_match( '#^https?://#i', $url ) ) {
        http_response_code( 400 );
        echo json_encode( array( 'error' => 'a valid http(s) URL is required' ) );
        exit;
    }

    $code = trim( (string) ( $_REQUEST['code'] ?? '' ) );
    if ( $code === '' ) {
        // generate a unique random code
        do {
            $code = short_random_code();
            $exists = $pdo->prepare( 'SELECT 1 FROM links WHERE code = ?' );
            $exists->execute( array( $code ) );
        } while ( $exists->fetch() );
    } elseif ( ! preg_match( '/^[A-Za-z0-9]{4,32}$/', $code ) ) {
        http_response_code( 400 );
        echo json_encode( array( 'error' => 'code must be 4-32 alphanumeric characters' ) );
        exit;
    }

    $expires_at = null;
    if ( isset( $_REQUEST['expires_days'] ) && (int) $_REQUEST['expires_days'] > 0 ) {
        $expires_at = time() + (int) $_REQUEST['expires_days'] * 86400;
    }

    try {
        $pdo->prepare( 'INSERT INTO links (code, target, note, created_at, expires_at) VALUES (?, ?, ?, ?, ?)' )
            ->execute( array(
                $code,
                $url,
                substr( (string) ( $_REQUEST['note'] ?? '' ), 0, 255 ),
                time(),
                $expires_at,
            ) );
    } catch ( PDOException $e ) {
        http_response_code( 409 );
        echo json_encode( array( 'error' => 'code already taken' ) );
        exit;
    }

    echo json_encode( array(
        'ok'        => true,
        'code'      => $code,
        'short_url' => rtrim( BASE_URL, '/' ) . '/' . $code,
    ) );
    exit;
}

if ( $action === 'stats' ) {
    $code = (string) ( $_REQUEST['code'] ?? '' );
    $stmt = $pdo->prepare( 'SELECT id, target, clicks, created_at, expires_at FROM links WHERE code = ?' );
    $stmt->execute( array( $code ) );
    $link = $stmt->fetch( PDO::FETCH_ASSOC );
    if ( ! $link ) {
        http_response_code( 404 );
        echo json_encode( array( 'error' => 'not found' ) );
        exit;
    }
    $hits = $pdo->prepare( 'SELECT hit_at, ip, referer, ua FROM hits WHERE link_id = ? ORDER BY id DESC LIMIT 50' );
    $hits->execute( array( $link['id'] ) );
    echo json_encode( array( 'link' => $link, 'recent_hits' => $hits->fetchAll( PDO::FETCH_ASSOC ) ) );
    exit;
}

if ( $action === 'list' ) {
    $limit = min( 200, max( 1, (int) ( $_REQUEST['limit'] ?? 50 ) ) );
    $rows  = $pdo->query( 'SELECT code, target, clicks, created_at, expires_at FROM links ORDER BY id DESC LIMIT ' . $limit )
                 ->fetchAll( PDO::FETCH_ASSOC );
    echo json_encode( array( 'links' => $rows ) );
    exit;
}

http_response_code( 400 );
echo json_encode( array( 'error' => 'unknown action (create|stats|list)' ) );
