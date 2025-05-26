<?php
// Start the session
session_start();

// Load environment variables
$client_id = getenv('TWITCH_CLIENT_ID');
$redirect_uri = getenv('TWITCH_REDIRECT_URI');

// Set scopes
$scopes = 'user:read:email user:read:moderated_channels';
$state = bin2hex(random_bytes(16));
$_SESSION['twitch_oauth_state'] = $state;

// Build authorization URL
$auth_url = 'https://id.twitch.tv/oauth2/authorize' . '?' . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => $scopes,
    'state' => $state
]);

// Redirect to Twitch
header('Location: ' . $auth_url);
exit;
?>