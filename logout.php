<?php
/**
 * logout.php
 *
 * Destroys the current session completely, then starts a fresh one
 * just long enough to carry a one-time flash message to the login page.
 */

require_once __DIR__ . '/includes/auth.php';

logout_user();

session_start();
set_flash_message('success', 'You have been logged out successfully.');

redirect('login.php');