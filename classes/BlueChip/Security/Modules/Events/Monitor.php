<?php
/**
 * @package BC_Security
 */

namespace BlueChip\Security\Modules\Events;

use BlueChip\Security\Modules\Log;
use BlueChip\Security\Modules\Login;

class Monitor implements \BlueChip\Security\Modules\Initializable
{
    public function init()
    {
        // Log the following WordPress events:
        // - bad authentication cookie
        add_action('auth_cookie_bad_username', [$this, 'logBadCookie'], 5, 1);
        add_action('auth_cookie_bad_hash', [$this, 'logBadCookie'], 5, 1);
        // - failed login
        add_action('wp_login_failed', [$this, 'logFailedLogin'], 5, 1);
        // - successful login
        add_action('wp_login', [$this, 'logSuccessfulLogin'], 5, 1);
        // - 404 query
        add_action('wp', [$this, 'log404Queries'], 20, 1);

        // Log the following BC Security events:
        // - lockout event
        add_action(Login\Hooks::LOCKOUT_EVENT, [$this, 'logLockoutEvent'], 10, 3);
    }


    /**
     * Log 404 event (main queries that returned no results).
     *
     * Note: `parse_query` action cannot be used for 404 detection, because 404
     * state can be set later (see WP::main() method).
     *
     * @param \WP $wp
     */
    public function log404Queries(\WP $wp)
    {
        /** @var \WP_Query $wp_query */
        global $wp_query;

        if ($wp_query->is_404()) {
            do_action(
                Log\Action::INFO,
                'Main query returned no results (404) for request {request}.',
                ['request' => $wp->request, 'event' => EventType::QUERY_404]
            );
        }
    }


    /**
     * Log when bad cookie is used for authentication.
     * @param array $cookie_elements
     */
    public function logBadCookie(array $cookie_elements)
    {
        do_action(
            Log\Action::NOTICE,
            'Bad authentication cookie used with {username}.',
            ['username' => $cookie_elements['username'], 'event' => EventType::AUTH_BAD_COOKIE]
        );
    }


    /**
     * Log failed login.
     * @param string $username
     */
    public function logFailedLogin($username)
    {
        do_action(
            Log\Action::NOTICE,
            'Login attempt with username {username} failed.',
            ['username' => $username, 'event' => EventType::LOGIN_FAILURE]
        );
    }


    /**
     * Log successful login.
     * @param string $username
     */
    public function logSuccessfulLogin($username)
    {
        do_action(
            Log\Action::INFO,
            'User {username} logged in successfully.',
            ['username' => $username, 'event' => EventType::LOGIN_SUCCESSFUL]
        );
    }


    /**
     * Log lockout event.
     * @param string $remote_address
     * @param string $username
     * @param int $duration
     */
    public function logLockoutEvent($remote_address, $username, $duration)
    {
        do_action(
            Log\Action::WARNING,
            'Remote IP address {ip_address} has been locked out from login for {duration} seconds. Last username used for login was {username}.',
            ['ip_address' => $remote_address, 'duration' => $duration, 'username' => $username, 'event' => EventType::LOGIN_LOCKOUT]
        );
    }
}
