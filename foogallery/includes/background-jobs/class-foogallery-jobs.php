<?php
/** Shared Action Scheduler adapter. Feature state never belongs in queue arguments.
 *
 * @package FooGallery
 */

defined( 'ABSPATH' ) || exit;

/** FooGallery feature service with private application-owned state. */
class FooGallery_Jobs {
	/**
	 * Schedule unique work after Action Scheduler initialization.
	 *
	 * @param string $hook Hook.
	 * @param array|null $args Args.
	 * @param string $group Group.
	 * @param int $when When.
	 * @param int $interval Interval.
	 * @return mixed
	 */
	public static function schedule( $hook, $args = array(), $group = 'foogallery-improve', $when = 0, $interval = 0 ) {
		if ( ! did_action( 'action_scheduler_init' ) || ! in_array( $group, array( 'foogallery-improve', 'foogallery-media-audit' ), true ) || 0 !== strpos( $hook, 'foogallery_' ) ) {
			return 0;
		}
		foreach ( $args as $value ) {
			if ( ! is_int( $value ) && ( ! is_string( $value ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $value ) ) ) {
				return 0;
			}
		}
		if ( as_has_scheduled_action( $hook, $args, $group ) ) {
			return 0;
		}
		if ( $interval > 0 ) {
			return as_schedule_recurring_action( max( time(), $when ), $interval, $hook, $args, $group, true );
		}
		return $when > time() ? as_schedule_single_action( $when, $hook, $args, $group, true ) : as_enqueue_async_action( $hook, $args, $group, true );
	}

	/**
	 * Cancel matching work in its feature group.
	 *
	 * @param string $hook Hook.
	 * @param string $group Group.
	 * @param array|null $args Args.
	 * @return mixed
	 */
	public static function cancel( $hook, $group = 'foogallery-improve', $args = null ) {
		if ( did_action( 'action_scheduler_init' ) ) {
			as_unschedule_all_actions( $hook, $args, $group );
		}
	}

	/**
	 * Check whether matching work is pending or running.
	 *
	 * @param string $hook Hook.
	 * @param string $group Group.
	 * @param array|null $args Args.
	 * @return mixed
	 */
	public static function pending( $hook, $group = 'foogallery-improve', $args = null ) {
		return did_action( 'action_scheduler_init' ) && as_has_scheduled_action( $hook, $args, $group );
	}
}
