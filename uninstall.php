<?php
/**
 * Swim Log and Evaluation uninstall handler.
 *
 * Historical data is preserved by default. Permanent removal must never occur
 * unless an administrator has explicitly enabled the destructive removal option.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

// Intentionally preserve all plugin data by default.
