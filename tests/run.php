<?php
/**
 * Dependency-free native SimpleLMS integration tests.
 *
 * Run with: php tests/run.php
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'SIMPLE_LMS_VERSION', '0.1.0' );
	define( 'SIMPLE_LMS_DIR', dirname( __DIR__ ) . '/' );
	define( 'SIMPLE_LMS_URL', 'https://example.test/wp-content/plugins/simple-lms/' );

	final class WP_Post {
		public function __construct(
			public int $ID,
			public string $post_type,
			public string $post_status = 'publish',
			public int $post_parent = 0,
			public string $post_title = '',
			public string $post_content = '',
			public string $post_excerpt = '',
			public int $menu_order = 0
		) {
		}
	}

	final class WP_REST_Server {
		public const READABLE  = 'GET';
		public const CREATABLE = 'POST';
	}

	final class WP_REST_Request {
		public function __construct( private array $params = array() ) {
		}
		public function get_param( string $key ): mixed {
			return $this->params[ $key ] ?? null;
		}
		public function get_params(): array {
			return $this->params;
		}
	}

	final class WP_REST_Response {
		public function __construct( public mixed $data, public int $status = 200 ) {
		}
	}

	final class WP_Error {
		public function __construct( public string $code, public string $message, public array $data = array() ) {
		}
		public function get_error_message(): string {
			return $this->message;
		}
	}

	$GLOBALS['slms_posts']       = array();
	$GLOBALS['slms_post_meta']   = array();
	$GLOBALS['slms_user_meta']   = array();
	$GLOBALS['slms_options']     = array();
	$GLOBALS['slms_actions']     = array();
	$GLOBALS['slms_filters']     = array();
	$GLOBALS['slms_post_types']  = array();
	$GLOBALS['slms_taxonomies']  = array();
	$GLOBALS['slms_meta_fields'] = array();
	$GLOBALS['slms_current_user'] = 1;
	$GLOBALS['slms_query_count'] = 0;
	$GLOBALS['slms_assertions']  = 0;

	function add_action( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['slms_actions'][ $hook ][] = $callback;
	}
	function add_filter( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['slms_filters'][ $hook ][] = $callback;
	}
	function do_action( string $hook, mixed ...$args ): void {
		$GLOBALS['slms_actions_run'][] = array( $hook, $args );
	}
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		return $value;
	}
	function absint( mixed $value ): int {
		return abs( (int) $value );
	}
	function sanitize_key( string $value ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
	}
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
	function rest_sanitize_boolean( mixed $value ): bool {
		return in_array( $value, array( true, 1, '1', 'true', 'yes' ), true );
	}
	function __( string $text, string $domain = '' ): string {
		return $text;
	}
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_html_e( string $text, string $domain = '' ): void {
		echo esc_html( $text );
	}
	function esc_attr_e( string $text, string $domain = '' ): void {
		echo esc_html( $text );
	}
	function esc_url( string $url ): string {
		return $url;
	}
	function esc_url_raw( string $url ): string {
		return $url;
	}
	function wp_kses_post( string $html ): string {
		return $html;
	}
	function number_format_i18n( int $number ): string {
		return (string) $number;
	}
	function current_time( string $type ): string {
		return 'mysql' === $type ? '2026-11-26 12:00:00' : time();
	}
	function get_current_user_id(): int {
		return (int) $GLOBALS['slms_current_user'];
	}
	function current_user_can( string $capability, int ...$args ): bool {
		return in_array( $capability, array( 'manage_options', 'edit_posts', 'edit_post' ), true )
			? (bool) ( $GLOBALS['slms_admin'] ?? true )
			: false;
	}
	function get_userdata( int $user_id ): \stdClass|false {
		return $user_id > 0 ? (object) array( 'ID' => $user_id ) : false;
	}
	function is_user_logged_in(): bool {
		return get_current_user_id() > 0;
	}
	function get_option( string $key, mixed $default = false ): mixed {
		return $GLOBALS['slms_options'][ $key ] ?? $default;
	}
	function update_option( string $key, mixed $value, bool $autoload = true ): bool {
		$GLOBALS['slms_options'][ $key ] = $value;
		return true;
	}
	function register_post_type( string $type, array $args ): void {
		$GLOBALS['slms_post_types'][ $type ] = $args;
	}
	function flush_rewrite_rules(): void {
		++$GLOBALS['slms_rewrite_flushes'];
	}
	function register_taxonomy( string $taxonomy, array $types, array $args ): void {
		$GLOBALS['slms_taxonomies'][ $taxonomy ] = array( 'types' => $types, 'args' => $args );
	}
	function register_post_meta( string $type, string $key, array $args ): void {
		$GLOBALS['slms_meta_fields'][ $type ][ $key ] = $args;
	}
	function register_meta( string $type, string $key, array $args ): void {
		$GLOBALS['slms_meta_fields'][ $type ][ $key ] = $args;
	}
	function post_type_exists( string $type ): bool {
		return isset( $GLOBALS['slms_post_types'][ $type ] );
	}
	function get_post( int $id ): WP_Post|false {
		return $GLOBALS['slms_posts'][ $id ] ?? false;
	}
	function get_post_type( int $id ): string {
		return isset( $GLOBALS['slms_posts'][ $id ] ) ? $GLOBALS['slms_posts'][ $id ]->post_type : '';
	}
	function get_post_status( int $id ): string {
		return isset( $GLOBALS['slms_posts'][ $id ] ) ? $GLOBALS['slms_posts'][ $id ]->post_status : '';
	}
	function get_post_field( string $field, int $id ): mixed {
		return isset( $GLOBALS['slms_posts'][ $id ] ) ? ( $GLOBALS['slms_posts'][ $id ]->$field ?? '' ) : '';
	}
	function get_the_title( int|WP_Post $post ): string {
		$id = $post instanceof WP_Post ? $post->ID : $post;
		return isset( $GLOBALS['slms_posts'][ $id ] ) ? $GLOBALS['slms_posts'][ $id ]->post_title : '';
	}
	function get_permalink( int|WP_Post $post ): string {
		$id = $post instanceof WP_Post ? $post->ID : $post;
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		$base = 'slms_course' === $post->post_type ? 'courses' : ( 'slms_lesson' === $post->post_type ? 'lessons' : 'pages' );
		return 'https://example.test/' . $base . '/' . sanitize_key( $post->post_title ) . '/';
	}
	function get_edit_post_link( int $id, string $context = 'display' ): string {
		return 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit';
	}
	function add_query_arg( string|array $key, mixed $value = '', string $url = '' ): string {
		if ( is_array( $key ) ) {
			$url = $value;
			$pairs = array();
			foreach ( $key as $name => $item ) {
				$pairs[] = rawurlencode( (string) $name ) . '=' . rawurlencode( (string) $item );
			}
			return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . implode( '&', $pairs );
		}
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
	}
	function get_post_meta( int $id, string $key, bool $single = false ): mixed {
		$value = $GLOBALS['slms_post_meta'][ $id ][ $key ] ?? '';
		return $single ? $value : ( '' === $value ? array() : array( $value ) );
	}
	function update_post_meta( int $id, string $key, mixed $value ): bool {
		$GLOBALS['slms_post_meta'][ $id ][ $key ] = $value;
		return true;
	}
	function clean_post_cache( int $id ): void {
		++$GLOBALS['slms_query_count'];
	}
	function get_user_meta( int $user_id, string $key, bool $single = false ): mixed {
		$value = $GLOBALS['slms_user_meta'][ $user_id ][ $key ] ?? '';
		return $single ? $value : ( '' === $value ? array() : array( $value ) );
	}
	function update_user_meta( int $user_id, string $key, mixed $value ): int|bool {
		$GLOBALS['slms_user_meta'][ $user_id ][ $key ] = $value;
		return 1;
	}
	function wp_cache_delete( int $key, string $group = '' ): bool {
		return true;
	}
	function get_posts( array $args ): array {
		++$GLOBALS['slms_query_count'];
		$types   = (array) ( $args['post_type'] ?? array_keys( $GLOBALS['slms_post_types'] ) );
		$statuses = (array) ( $args['post_status'] ?? array( 'publish' ) );
		$posts   = array_values(
			array_filter(
				$GLOBALS['slms_posts'],
				static function ( WP_Post $post ) use ( $args, $types, $statuses ): bool {
					if ( ! in_array( $post->post_type, $types, true ) || ! in_array( $post->post_status, $statuses, true ) ) {
						return false;
					}
					if ( isset( $args['post_parent'] ) && $args['post_parent'] !== $post->post_parent ) {
						return false;
					}
					if ( ! empty( $args['s'] ) && ! str_contains( strtolower( $post->post_title ), strtolower( $args['s'] ) ) ) {
						return false;
					}
					return true;
				}
			)
		);
		usort( $posts, static fn( WP_Post $left, WP_Post $right ): int => ( $left->menu_order <=> $right->menu_order ) ?: strcmp( $left->post_title, $right->post_title ) );
		if ( isset( $args['posts_per_page'] ) && -1 !== $args['posts_per_page'] ) {
			$posts = array_slice( $posts, 0, (int) $args['posts_per_page'] );
		}
		if ( 'ids' === ( $args['fields'] ?? '' ) ) {
			return array_map( static fn( WP_Post $post ): int => $post->ID, $posts );
		}
		return $posts;
	}
	function rest_url( string $path = '' ): string {
		return 'https://example.test/wp-json/' . ltrim( $path, '/' );
	}
	function wp_create_nonce( string $action ): string {
		return 'test-nonce';
	}
	function register_rest_route( string $namespace, string $route, array $args ): void {
		$GLOBALS['slms_routes'][ $namespace ][ $route ] = $args;
	}
	function home_url( string $path = '/' ): string {
		return 'https://example.test' . $path;
	}
	function url_to_postid( string $url ): int {
		return 0;
	}
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/' );
	}
	function is_singular( string $type ): bool {
		return false;
	}
	function is_page( int $id ): bool {
		return false;
	}
	function get_queried_object_id(): int {
		return 0;
	}

	require_once dirname( __DIR__ ) . '/includes/class-settings.php';
	require_once dirname( __DIR__ ) . '/includes/class-post-types.php';
	require_once dirname( __DIR__ ) . '/includes/class-relationships.php';
	require_once dirname( __DIR__ ) . '/includes/class-access.php';
	require_once dirname( __DIR__ ) . '/includes/class-progress.php';
	require_once dirname( __DIR__ ) . '/includes/class-gravity-forms.php';
	require_once dirname( __DIR__ ) . '/includes/class-rest-api.php';

	use SimpleLMS\Access;
	use SimpleLMS\Post_Types;
	use SimpleLMS\Progress;
	use SimpleLMS\Relationships;
	use SimpleLMS\Rest_API;
	use SimpleLMS\Settings;

	function check( bool $condition, string $message ): void {
		++$GLOBALS['slms_assertions'];
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
	}
	function same( mixed $expected, mixed $actual, string $message ): void {
		check( $expected === $actual, $message . ' Expected: ' . var_export( $expected, true ) . ' Actual: ' . var_export( $actual, true ) );
	}
	function slms_post( int $id, string $type, string $title, string $status = 'publish' ): void {
		$GLOBALS['slms_posts'][ $id ] = new WP_Post( $id, $type, $status, 0, $title );
	}

	$defaults = Settings::defaults();
	same( 'slms_course', $defaults['course_post_type'], 'Native course CPT should be the default.' );
	same( 'slms_lesson', $defaults['lesson_post_type'], 'Native lesson CPT should be the default.' );
	same( '_slms_enrolled_courses', $defaults['enrollment_meta_key'], 'Native enrollment meta should be the default.' );
	same( 324, $defaults['pdf_template_id'], 'Requested Gravity PDF template ID should be retained.' );
	same( 'aala-certificate', $defaults['pdf_template_slug'], 'Requested Gravity PDF template slug should be retained.' );
	check( ! isset( $defaults['relationship_mode'] ) && ! isset( $defaults['pods_access_field'] ), 'Legacy Pods settings should be removed.' );
	$GLOBALS['slms_options']['simple_lms_settings'] = array(
		'course_post_type'  => 'course',
		'lesson_post_type'  => 'course',
		'relationship_mode' => 'post_parent',
		'access_method'     => 'pods',
	);
	same( 'slms_course', Settings::get( 'course_post_type' ), 'Saved legacy CPT settings must not override the native schema.' );
	same( 'native', Settings::get( 'access_method' ), 'Invalid saved access methods should fall back to native enrollment.' );
	$GLOBALS['slms_options'] = array();

	Post_Types::register();
	check( isset( $GLOBALS['slms_post_types']['slms_course'], $GLOBALS['slms_post_types']['slms_lesson'] ), 'Both native CPTs should register.' );
	$GLOBALS['slms_rewrite_flushes'] = 0;
	Post_Types::activate();
	same( 1, $GLOBALS['slms_rewrite_flushes'], 'Activation should persist native CPT rewrite rules.' );
	same( true, $GLOBALS['slms_post_types']['slms_course']['show_in_rest'], 'Courses should support the REST API.' );
	same( 'courses', $GLOBALS['slms_post_types']['slms_course']['rewrite']['slug'], 'Course archive should use /courses/.' );
	$legacy_rewrite_rules = array(
		'courses/?$'                            => 'index.php?post_type=course',
		'courses/page/([0-9]{1,})/?$'            => 'index.php?post_type=course&paged=$matches[1]',
		'course/([^/]+)/?$'                      => 'index.php?post_type=course&name=$matches[1]',
		'courses/sample-course/?$'               => 'index.php?post_type=slms_course&name=$matches[1]',
		'uncollided-page/?$'                     => 'index.php?pagename=uncollided-page',
	);
	$preferred_rewrite_rules = Post_Types::prefer_course_archive( $legacy_rewrite_rules );
	same( 'index.php?post_type=slms_course', $preferred_rewrite_rules['courses/?$'], 'Native courses should win the archive root rewrite collision.' );
	same( 'index.php?post_type=slms_course&paged=$matches[1]', $preferred_rewrite_rules['courses/page/([0-9]{1,})/?$'], 'Native courses should win paginated archive routing.' );
	same(
		array( 'courses/?$', 'courses/page/([0-9]{1,})/?$', 'course/([^/]+)/?$', 'courses/sample-course/?$', 'uncollided-page/?$' ),
		array_keys( $preferred_rewrite_rules ),
		'Native archive rules should take precedence without disturbing unrelated legacy rules.'
	);
	same( 'index.php?post_type=course&name=$matches[1]', $preferred_rewrite_rules['course/([^/]+)/?$'], 'Legacy single-course URLs should remain untouched.' );
	same( 'index.php?pagename=uncollided-page', $preferred_rewrite_rules['uncollided-page/?$'], 'Unrelated rewrite rules should remain untouched.' );
	check( in_array( 'excerpt', $GLOBALS['slms_post_types']['slms_lesson']['supports'], true ), 'Lessons should support excerpts.' );
	check( isset( $GLOBALS['slms_meta_fields']['slms_course']['_slms_lesson_ids'] ), 'Ordered lesson meta should be registered.' );
	Access::register_meta();
	check( isset( $GLOBALS['slms_meta_fields']['user']['_slms_enrolled_courses'] ), 'Native enrollment meta should be registered.' );

	slms_post( 1, 'slms_course', '10 Hour Course' );
	slms_post( 2, 'slms_course', '8 Hour Course' );
	slms_post( 3, 'slms_course', '5 Hour Course' );
	slms_post( 201, 'slms_lesson', 'Bloodborne Pathogens' );
	slms_post( 202, 'slms_lesson', 'Shared Safety' );
	slms_post( 203, 'slms_lesson', 'Eight Hour Final' );
	slms_post( 465, 'page', 'Certificate' );
	$GLOBALS['slms_post_meta'][201]['_lms_presto_video_id'] = 920;
	$GLOBALS['slms_post_meta'][202]['lesson_video']         = '[presto_player id=921]';

	same( 920, Post_Types::presto_video_id( 201 ), 'Native Presto IDs should resolve.' );
	same( 921, Post_Types::presto_video_id( 202 ), 'Legacy Presto embeds should resolve without Pods.' );
	check( Relationships::set_course_lesson_ids( 1, array( 201, 202, 201 ) ), 'Course curriculum should save.' );
	check( Relationships::set_course_lesson_ids( 2, array( 202, 203 ) ), 'Shared curriculum should save.' );
	check( Relationships::set_course_lesson_ids( 3, array( 202 ) ), 'Third shared curriculum should save.' );
	same( array( 201, 202 ), Relationships::get_course_lesson_ids( 1 ), 'Curriculum order should be preserved and deduplicated.' );
	same( array( 1, 2, 3 ), Relationships::get_lesson_courses( 202 ), 'Reverse lookup should resolve every sharing course.' );
	$GLOBALS['slms_query_count'] = 0;
	Relationships::get_lesson_courses( 202 );
	same( 0, $GLOBALS['slms_query_count'], 'Reverse lookup should be O(1) meta access without a course scan.' );
	check( str_contains( Relationships::lesson_url( 202, 2 ), 'course_id=2' ), 'Lesson links should carry their course context.' );
	check( Relationships::set_course_lesson_ids( 1, array( 202, 201 ) ), 'Curriculum reorder should save.' );
	same( array( 1, 2, 3 ), Relationships::get_lesson_courses( 202 ), 'Reordering must not duplicate reverse assignments.' );
	check( Relationships::set_course_lesson_ids( 1, array( 201 ) ), 'Removing a shared lesson should save.' );
	same( array( 2, 3 ), Relationships::get_lesson_courses( 202 ), 'Removing one course must preserve the other reverse links.' );
	check( Relationships::set_course_lesson_ids( 1, array( 201, 202 ) ), 'Restore shared lesson for progress tests.' );

	check( Access::enroll_user( 1, array( 1, 2, 3 ) ), 'Native enrollment should be written.' );
	same( array( 1, 2, 3 ), Access::enrolled_course_ids( 1 ), 'Enrollment IDs should normalize.' );
	same( true, Access::can_access( 2, 1 ), 'Enrolled users should access their courses.' );
	check( Access::enroll_user( 1, array( 2 ) ), 'Repeated enrollment should be idempotent.' );
	same( array( 1, 2, 3 ), Access::enrolled_course_ids( 1 ), 'Repeated enrollment must not duplicate IDs.' );

	$GLOBALS['slms_actions_run'] = array();
	check( Progress::mark_lesson_complete( 202, 2, 1 ), 'A shared lesson should complete in an explicit course context.' );
	same( array( 202 ), Progress::completed_lesson_ids( 1 ), 'Completion should be keyed by the canonical lesson ID.' );
	same( array( 201, 202 ), Progress::lesson_ids_for_course( 1 ), 'Progress alias should use native ordered relationships.' );
	same( 1, Progress::course_progress( 1, 1 )['completed'], 'Canonical completion should count in every sharing course.' );
	same( 1, Progress::course_progress( 2, 1 )['completed'], 'Canonical completion should count in the active 8-hour course.' );
	same( true, Progress::course_progress( 3, 1 )['complete'], 'A one-lesson sharing course should complete from the shared lesson.' );
	check( Progress::mark_lesson_complete( 202, 2, 1 ), 'Repeated completion in another context should remain idempotent.' );
	same( 1, count( $GLOBALS['slms_user_meta'][1]['_slms_completed_lessons'] ), 'Repeated shared completion must not create duplicate lesson records.' );
	$next = Progress::get_next_step_url( 2, 202, 1 );
	check( str_contains( $next, 'lesson' ) && str_contains( $next, 'course_id=2' ), 'Next step must stay in the 8-hour course context.' );
	check( ! str_contains( $next, '10-hour' ), 'Next step must not jump to the 10-hour course.' );
	check( Progress::mark_lesson_complete( 201, 1, 1 ), 'First course lesson should complete.' );
	same( true, Progress::course_progress( 1, 1 )['complete'], 'The 10-hour fixture course should complete.' );
	check( Progress::mark_lesson_complete( 203, 2, 1 ), 'Second course final lesson should complete.' );
	same( true, Progress::course_progress( 2, 1 )['complete'], 'The 8-hour fixture course should complete.' );
	check( str_contains( Progress::get_next_step_url( 2, 203, 1 ), 'simple_lms_course=2' ), 'Completed courses should advance to their own verification URL.' );

	$GLOBALS['slms_current_user'] = 99;
	same( false, Progress::mark_lesson_complete( 202, 2, 99 ), 'Unenrolled users must not complete lessons.' );
	same( false, Progress::mark_lesson_complete( 202, 1, 99 ), 'An unrelated user must not receive shared completion.' );
	$GLOBALS['slms_current_user'] = 1;
	check( Access::unenroll_user( 1, array( 3 ) ), 'Native unenrollment should work.' );
	same( false, Access::can_access( 3, 1 ), 'Removed enrollment should revoke access.' );

	Rest_API::register_routes();
	check( isset( $GLOBALS['slms_routes']['simple-lms/v1']['/courses'] ), 'Curriculum course endpoint should register.' );
	check( isset( $GLOBALS['slms_routes']['simple-lms/v1']['/lessons'] ), 'Curriculum lesson endpoint should register.' );
	$course_response = Rest_API::courses( new WP_REST_Request( array( 'per_page' => 100 ) ) );
	same( 3, count( $course_response->data['courses'] ), 'Course endpoint should list native courses.' );
	$lesson_response = Rest_API::lessons( new WP_REST_Request( array( 'per_page' => 100, 'search' => 'Safety' ) ) );
	same( 202, $lesson_response->data['lessons'][0]['id'], 'Lesson endpoint should search canonical lessons.' );
	$curriculum = Rest_API::update_course_lessons( new WP_REST_Request( array( 'id' => 2, 'lesson_ids' => array( 203, 202 ) ) ) );
	check( ! $curriculum instanceof WP_Error, 'Authorized curriculum updates should succeed.' );
	same( array( 203, 202 ), $curriculum->data['lesson_ids'], 'REST should persist the submitted order.' );
	$GLOBALS['slms_admin'] = false;
	$denied = Rest_API::update_course_lessons( new WP_REST_Request( array( 'id' => 2, 'lesson_ids' => array() ) ) );
	check( $denied instanceof WP_Error, 'Unauthorized curriculum updates should fail.' );
	$GLOBALS['slms_admin'] = true;
	$completion = Rest_API::complete( new WP_REST_Request( array( 'lesson_id' => 202, 'course_id' => 2 ) ) );
	check( ! $completion instanceof WP_Error, 'Context-aware completion should succeed.' );
	same( 2, $completion->data['course_id'], 'REST should return the active course context.' );
	check( str_contains( $completion->data['next_step_url'], 'simple_lms_course=2' ), 'REST next step should preserve completed-course verification context.' );
	$invalid_context = Rest_API::complete( new WP_REST_Request( array( 'lesson_id' => 202, 'course_id' => 999 ) ) );
	check( $invalid_context instanceof WP_Error, 'REST should reject an invalid explicit course context.' );

	echo 'PASS: ' . $GLOBALS['slms_assertions'] . " assertions\n";
}
