<?php
/**
 * includes/auth.php
 *
 * Session-based authentication and role-based access control
 * for the three roles: citizen, police, admin.
 */

require_once __DIR__ . '/functions.php';

/**
 * Is there a valid logged-in user in the current session?
 */
function is_logged_in() {
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

/**
 * Store the authenticated user's data in the session (called after
 * password_verify() succeeds in login.php). Regenerates the session ID
 * to prevent session fixation.
 */
function login_user(array $user) {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
}

/**
 * Destroy the current session completely (logout).
 */
function logout_user() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Return the currently logged-in user's basic info, or null if none.
 */
function get_current_user_data() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'user_id'   => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'],
        'email'     => $_SESSION['email'],
        'role'      => $_SESSION['role'],
    ];
}

/**
 * Send a logged-out user to the correct dashboard for their role,
 * or to the login page if not logged in at all.
 */
function redirect_to_dashboard() {
    if (!is_logged_in()) {
        redirect('login.php');
    }

    switch ($_SESSION['role']) {
        case 'citizen':
            redirect('citizen/dashboard.php');
        case 'police':
            redirect('police/dashboard.php');
        case 'admin':
            redirect('admin/dashboard.php');
        default:
            logout_user();
            redirect('login.php');
    }
}

/**
 * Block access unless the user is logged in.
 * Call at the very top of any protected page.
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('warning', 'Please log in to continue.');
        redirect('login.php');
    }
}

/**
 * Block access unless the user is logged in AND has the given role.
 */
function require_role($role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        set_flash_message('danger', 'You are not authorized to access that page.');
        redirect_to_dashboard();
    }
}

/**
 * Block access unless the user is logged in AND has one of the given roles.
 */
function require_any_role(array $roles) {
    require_login();
    if (!in_array($_SESSION['role'], $roles, true)) {
        set_flash_message('danger', 'You are not authorized to access that page.');
        redirect_to_dashboard();
    }
}