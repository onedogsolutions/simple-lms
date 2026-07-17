<?php
/**
 * WP-CLI commands for SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLI
 */
class CLI {

	/**
	 * Backfill the progress table from legacy metadata.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<batch_size>]
	 * : Number of users to process per batch.
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp slms progress backfill --batch-size=50
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Command line arguments.
	 * @param array $assoc_args Associative command line arguments.
	 * @return void
	 */
	public function backfill( $args, $assoc_args ) {
		$batch_size = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 100;
		$offset     = 0;
		$complete   = false;

		\WP_CLI::line( 'Starting lesson progress backfill...' );

		while ( ! $complete ) {
			$result = Progress::backfill( $batch_size, $offset );

			\WP_CLI::line(
				sprintf(
					'Processed batch: %d users, %d rows inserted (skipped: %d).',
					$result['processed_users'],
					$result['inserted_rows'],
					$result['skipped_entries']
				)
			);

			$offset   = $result['next_offset'];
			$complete = $result['complete'];

			if ( 0 === $result['processed_users'] ) {
				break;
			}
		}

		$parity = Progress::get_parity();
		\WP_CLI::success(
			sprintf(
				'Backfill complete! Table total: %d rows. Parity checks: Meta distinct tuples = %d, Table rows = %d.',
				Progress::row_count(),
				$parity['meta_tuples'],
				$parity['table_rows']
			)
		);
	}
}

\WP_CLI::add_command( 'slms progress', __NAMESPACE__ . '\\CLI' );
