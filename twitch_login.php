<?php
session_start();

// Load environment variables
$client_id = getenv('TWITCH_CLIENT_ID');
$redirect_uri = getenv('TWITCH_REDIRECT_URI');
$scopes = 'user:read:email moderation:read';

// Generate a random state for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['twitch_oauth_state'] = $state;

// Build Twitch authorization URL
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