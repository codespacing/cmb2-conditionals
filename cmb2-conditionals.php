<?php
/**
 * Plugin Name: CMB2 Conditionals
 * Plugin URI: https://github.com/jcchavezs/cmb2-conditionals
 * Description: Plugin to stablish conditional relationships between fields in a CMB2 metabox.
 * Author: José Carlos Chávez <jcchavezs@gmail.com>
 * Author URI: http://github.com/jcchavezs
 * Github Plugin URI: https://github.com/jcchavezs/cmb2-conditionals
 * Github Branch: master
 * Version: 1.0.4
*/

add_action('plugins_loaded', 'cmb2_conditionals_load_actions');

function cmb2_conditionals_load_actions()
{
	if(!defined('CMB2_LOADED') || false === CMB2_LOADED) {
		return;
	}

	define('CMB2_CONDITIONALS_PRIORITY', 99999);

	add_action('admin_init', 'cmb2_conditionals_hook_data_to_save_filtering', CMB2_CONDITIONALS_PRIORITY);

	add_action('admin_enqueue_scripts', 'cmb2_conditionals_register_script', CMB2_CONDITIONALS_PRIORITY);
	add_action('admin_footer', 'cmb2_conditionals_enqueue_script', CMB2_CONDITIONALS_PRIORITY);
	add_filter('cmb2_field_arguments', 'cmb2_conditionals_has_conditions', CMB2_CONDITIONALS_PRIORITY, 2 );
}

/**
 * 'Abuse' a filter to determine whether the script needs to be loaded.
 *
 * Checks whenever a field is registered in CMB2 whether it has conditions and if so, ensures
 * that the script will be enqueued.
 *
 * @param array             $args  Metabox field config array after processing.
 * @param CMB2_Field object $field CMB2 Field object.
 * @return array Unchanged $args.
 */
function cmb2_conditionals_has_conditions( $args, $field_object )
{
	if ( false === has_filter( 'cmb2-conditionals-enqueue_script-' . $field_object->object_type, '__return_true' ) && ( isset( $args['required'] ) || isset( $args['attributes']['data-conditional-id'] ) || isset( $args['attributes']['required'] ) ) ) {
		add_filter( 'cmb2-conditionals-enqueue_script-' . $field_object->object_type, '__return_true' );
	}

	return $args;
}


/**
 * Register the script.
 */
function cmb2_conditionals_register_script()
{
	$suffix = ( ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? '' : '.min' );

	wp_register_script(
		'cmb2-conditionals', // ID.
		plugins_url( 'js/cmb2-conditionals' . $suffix . '.js', __FILE__ ), // URL.
		array( 'jquery' ), // Dependants.
		'1.0.2', // Version.
		true // Load in footer ?
	);
}

/**
 * Enqueue the script only when needed.
 */
function cmb2_conditionals_enqueue_script()
{
	$screen = get_current_screen();

	if ( ! property_exists( $screen, 'base' ) || ! property_exists( $screen, 'parent_base' ) ) {
		return;
	}

	$object_type = '';

	if ( 'post' === $screen->base && 'edit' === $screen->parent_base ) {
		$object_type = 'post';
	} else if ( 'edit-tags' === $screen->base && 'edit' === $screen->parent_base ) {
		$object_type = 'term';
	} else if ( in_array( $screen->base, array( 'user', 'profile' ), true ) && 'users' === $screen->parent_base ) {
		$object_type = 'user';
	} else if ( 'comment' === $screen->base && 'edit-comments' === $screen->parent_base ) {
		$object_type = 'comment';
	}

	if ( ( '' === $object_type || false === apply_filters( 'cmb2-conditionals-enqueue_script-' . $object_type, false ) ) && true !== apply_filters( 'cmb2-conditionals-enqueue-script', false ) ) {
		return;
	}

	wp_enqueue_script( 'cmb2-conditionals' );
}

/**
 * Hooks the filtering of the data being saved.
 */
function cmb2_conditionals_hook_data_to_save_filtering()
{
	$cmb2_boxes = CMB2_Boxes::get_all();

	foreach($cmb2_boxes as $cmb_id => $cmb2_box) {
		add_action("cmb2_{$cmb2_box->object_type()}_process_fields_{$cmb_id}", 'cmb2_conditional_filter_data_to_save', CMB2_CONDITIONALS_PRIORITY, 2);
	}
}

/**
 * Filters the data to remove those values which are not suppose to be enabled to edit according to the declared conditionals.
 */
function cmb2_conditional_filter_data_to_save(CMB2 $cmb2, $object_id)
{
	foreach ( $cmb2->prop( 'fields' ) as $field_args ) {
		if(!(array_key_exists('attributes', $field_args) && array_key_exists('data-conditional-id', $field_args['attributes']))) {
			continue;
		}

		$field_id = $field_args['id'];
		$conditional_id = $field_args['attributes']['data-conditional-id'];

		if(
			array_key_exists('data-conditional-value', $field_args['attributes'])
		) {
			$conditional_value = $field_args['attributes']['data-conditional-value'];

			$conditional_value = ($decoded_conditional_value = @json_decode($conditional_value)) ? $decoded_conditional_value : $conditional_value;

			if(!isset($cmb2->data_to_save[$conditional_id])) {
				unset($cmb2->data_to_save[$field_id]);
				continue;
			}

			if(is_array($conditional_value) && !in_array($cmb2->data_to_save[$conditional_id], $conditional_value)) {
				unset($cmb2->data_to_save[$field_id]);
				continue;
			}

			if(!is_array($conditional_value) && $cmb2->data_to_save[$conditional_id] != $conditional_value) {
				unset($cmb2->data_to_save[$field_id]);
				continue;
			}
		}

		if(!isset($cmb2->data_to_save[$conditional_id]) || !$cmb2->data_to_save[$conditional_id]) {
			unset($cmb2->data_to_save[$field_id]);
			continue;
		}
	}
}
