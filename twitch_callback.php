<?php
session_start();

// Load environment variables
$client_id = getenv('TWITCH_CLIENT_ID');
$client_secret = getenv('TWITCH_CLIENT_SECRET');
$redirect_uri = getenv('TWITCH_REDIRECT_URI');

// Validate state to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['twitch_oauth_state']) {
    die('Invalid state parameter. Possible CSRF attack.');
}
unset($_SESSION['twitch_oauth_state']);

// Check for error
if (isset($_GET['error'])) {
    die('Twitch OAuth error: ' . htmlspecialchars($_GET['error_description']));
}

// Exchange code for access token
$code = $_GET['code'];
$token_url = 'https://id.twitch.tv/oauth2/token';
$data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code,
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirect_uri
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CAINFO, 'cacert.pem');
$response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE || empty($token_data['access_token'])) {
    die('Failed to obtain access token.');
}

// Get user info
$access_token = $token_data['access_token'];
$user_url = 'https://api.twitch.tv/helix/users';
$ch = curl_init($user_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CAINFO, 'cacert.pem');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Client-Id: ' . $client_id
]);
$user_response = curl_exec($ch);
curl_close($ch);

$user_data = json_decode($user_response, true);
if (empty($user_data['data'][0]['id'])) {
    die('Failed to retrieve user info.');
}

$user_id = $user_data['data'][0]['id'];
$login = $user_data['data'][0]['login'];

// Get thatviolinchick's broadcaster ID (hardcoded or fetch dynamically)
$broadcaster_login = 'unsaltedcornchip';
$broadcaster_url = 'https://api.twitch.tv/helix/users?login=' . urlencode($broadcaster_login);
$ch = curl_init($broadcaster_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CAINFO, 'cacert.pem');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Client-Id: ' . $client_id
]);
$broadcaster_response = curl_exec($ch);
curl_close($ch);

$broadcaster_data = json_decode($broadcaster_response, true);
$broadcaster_id = $broadcaster_data['data'][0]['id'] ?? null;

if (!$broadcaster_id) {
    die('Failed to retrieve broadcaster ID for thatviolinchick.');
}

// Check if user is streamer
$is_streamer = ($user_id === $broadcaster_id);

// Check if user is moderator
$is_moderator = false;
if (!$is_streamer) {
    $mods_url = "https://api.twitch.tv/helix/moderation/moderators?broadcaster_id=$broadcaster_id";
    $ch = curl_init($mods_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_CAINFO, 'cacert.pem');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Client-Id: ' . $client_id
    ]);
    $mods_response = curl_exec($ch);
    curl_close($ch);

    $mods_data = json_decode($mods_response, true);
    foreach ($mods_data['data'] ?? [] as $mod) {
        if ($mod['user_id'] === $user_id) {
            $is_moderator = true;
            break;
        }
    }
}

// Restrict access to streamer or moderators
if (!$is_streamer && !$is_moderator) {
    die('Access denied: You are not a moderator or the streamer for thatviolinchick.');
}

// Store user info in session
$_SESSION['twitch_user'] = [
    'user_id' => $user_id,
    'login' => $login,
    'access_token' => $access_token,
    'is_streamer' => $is_streamer,
    'is_moderator' => $is_moderator
];

// Redirect to admin area (e.g., bulk_upload.php)
header('Location: bulk_upload.php');
exit;
?>