<?php
/**
 * Settings authority for SimpleLMS.
 *
 * Stores plugin-wide configuration in the `slms_settings` option and exposes
 * it via REST for the React "Settings" admin screen. Removes hardcoded values
 * scattered across the codebase (default guard mode, checkout/levels pages,
 * login redirect behaviour, and certificate Gravity Forms field IDs).
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings
 */
class Settings
{

    /**
     * Option key.
     *
     * @var string
     */
    const OPTION = 'slms_settings';

    /**
     * Cached settings for the request.
     *
     * @var array|null
     */
    private static $cache = null;

    /**
     * Hook into WordPress.
     *
     * @return void
     */
    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Default values for every known setting.
     *
     * @return array
     */
    public static function defaults()
    {
        return array(
            'default_guard_mode'    => 'enrolled',
            'checkout_page_id'      => 0,
            'levels_page_id'        => 0,
            'login_redirect'        => 'login', // login | checkout
            'cert_state_field_id'   => 6,
            'cert_course_field_id'  => 18,
        );
    }

    /**
     * Get the full settings array (merged with defaults).
     *
     * @return array
     */
    public static function all()
    {
        if (null === self::$cache) {
            $saved = get_option(self::OPTION, array());
            if (!is_array($saved)) {
                $saved = array();
            }
            self::$cache = wp_parse_args($saved, self::defaults());
        }
        return self::$cache;
    }

    /**
     * Get a single setting.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Fallback if unset.
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $all = self::all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        return $default;
    }

    /**
     * Sanitize an incoming settings payload against the known schema.
     *
     * @param array $input Raw input.
     * @return array
     */
    public static function sanitize($input)
    {
        $defaults = self::defaults();
        $clean    = self::all();

        if (isset($input['default_guard_mode'])) {
            $mode = $input['default_guard_mode'];
            $clean['default_guard_mode'] = in_array($mode, array('public', 'enrolled', 'level'), true) ? $mode : 'enrolled';
        }

        if (isset($input['login_redirect'])) {
            $lr = $input['login_redirect'];
            $clean['login_redirect'] = in_array($lr, array('login', 'checkout'), true) ? $lr : 'login';
        }

        foreach (array('checkout_page_id', 'levels_page_id', 'cert_state_field_id', 'cert_course_field_id') as $int_key) {
            if (isset($input[$int_key])) {
                $clean[$int_key] = absint($input[$int_key]);
            }
        }

        return wp_parse_args($clean, $defaults);
    }

    /**
     * Persist a settings payload.
     *
     * @param array $input Raw input.
     * @return array The saved settings.
     */
    public static function save($input)
    {
        $clean = self::sanitize($input);
        update_option(self::OPTION, $clean);
        self::$cache = $clean;
        return $clean;
    }

    /* ───────────────────────────────────────────────────────────────────
     * REST
     * ─────────────────────────────────────────────────────────────────── */

    /**
     * Register the settings REST routes.
     *
     * @return void
     */
    public static function register_routes()
    {
        register_rest_route(REST::NAMESPACE, '/settings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'rest_get'),
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'rest_save'),
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ),
        ));
    }

    /**
     * GET /settings callback.
     *
     * @return \WP_REST_Response
     */
    public static function rest_get()
    {
        return rest_ensure_response(self::all());
    }

    /**
     * POST /settings callback.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public static function rest_save($request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }

        $saved = self::save($params);

        return rest_ensure_response(array(
            'success'  => true,
            'settings' => $saved,
        ));
    }
}
