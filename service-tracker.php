<?php
/**
 * Service tracker - A simple plugin designed for business or organisations to add service requests, add a customer, link request to a customer, assign a worker or technician, set a booking time and finally update status.
 *
 * @wordpress-plugin
 * Plugin Name:       Service Tracker
 * Plugin URI:        
 * Description:       A simple plugin designed for business or organisations to add service requests, add a customer, link request to a customer, assign a worker or technician, set a booking time and finally update status.
 * Version:           1.0.0
 * Author:            Satish Kumar
 * Author URI:        https://cloudninestore.com.au/, https://bucklit.com.au/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function servicetracker_post_type_services() {
$supports = array(
	'title', // post title
	'editor', // post content
	'author', // post author
	'thumbnail', // featured images
	'excerpt', // post excerpt
	'custom-fields', // custom fields
	'comments', // post comments
	'revisions', // post revisions
	'post-formats', // post formats
);
$labels = array(
	'name' => _x('Services', 'plural'),
	'singular_name' => _x('Services', 'singular'),
	'menu_name' => _x('Services', 'admin menu'),
	'name_admin_bar' => _x('Services', 'admin bar'),
	'add_new' => _x('Add New', 'add new'),
	'add_new_item' => __('Add New Services'),
	'new_item' => __('New Services'),
	'edit_item' => __('Edit Services'),
	'view_item' => __('View Services'),
	'all_items' => __('All Services'),
	'search_items' => __('Search Services'),
	'not_found' => __('No Services found.'),
);
$args = array(
	'supports' => $supports,
	'labels' => $labels,
	'public' => true,
	'query_var' => true,
	'rewrite' => array('slug' => 'services'),
	'has_archive' => true,
	'hierarchical' => false,
);
register_post_type('services', $args);
}
add_action('init', 'servicetracker_post_type_services');

/* register services custom post as endpoint  */
add_action( 'init', 'servicetracker_add_services_to_json_api', 30 );
function servicetracker_add_services_to_json_api(){
    global $wp_post_types;
    $wp_post_types['services']->show_in_rest = true;
}

/* Add custom post type to query */
add_action( 'pre_get_posts', 'servicetracker_add_service_post_types_to_query' );
 
function servicetracker_add_service_post_types_to_query( $query ) {
    if ( is_home() && $query->is_main_query() )
        $query->set( 'post_type', array( 'post', 'services' ) );
    return $query;
}

/* Create technician roles for the service */
function servicetracker_update_custom_roles() {
	add_role(
		'technician',
		__( 'Technician' ),
		array(
			'read'         => true,  // true allows this capability
			'edit_posts'   => true,
			'delete_posts' => false, // Use false to explicitly deny
		)
	);
	update_option( 'custom_roles_version', 1 );
}
add_action( 'init', 'servicetracker_update_custom_roles' );

/* Create meta box for service post type */
add_action( 'add_meta_boxes_services', 'servicetracker_meta_box_for_services' );
function servicetracker_meta_box_for_services( $post ){
    add_meta_box( 'services_additional_info', __( 'Service Booking - Additional info' ), 'servicetracker_meta_box_html_output', 'services', 'normal', 'low' );
}

/* show service custom fields in admin list */
add_filter( 'manage_services_posts_columns', 'servicetracker_filter_service_posts_columns' );
function servicetracker_filter_service_posts_columns( $columns ) {
	unset($columns['image']);
	unset($columns['author']);
	unset($columns['comments']);
	$columns['service_customer'] = __( 'Customer' );
	$columns['customer_service_address'] = __( 'Customer  Service Address' );
	$columns['service_assigned_to'] = __( 'Assigned To' );
	$columns['service_booked_on'] = __( 'Booked Date' );
	$columns['service_is_work_complete'] = __( 'Service Status' );
	$columns['service_additional_notes'] = __( 'Notes' );
  	return $columns;
}

/* Date Picker initialisation */
function servicetracker_add_e2_date_picker(){
    //jQuery UI date picker file
    wp_enqueue_script('jquery-ui-datepicker');
    //jQuery UI theme css file
    wp_enqueue_style('e2b-admin-ui-css', plugin_dir_url( __FILE__ ) . '/assets/jquery-ui.css',false,"1.9.0",false);
}
add_action('admin_enqueue_scripts', 'servicetracker_add_e2_date_picker');

function servicetracker_meta_box_html_output( $post ) {
    wp_nonce_field( basename( __FILE__ ), 'servicetracker_meta_box_nonce' ); //used later for security
	$incomplete = ''; $complete = ''; $pending = '';
	if(get_post_meta($post->ID, 'service_is_work_complete', true) == 'InComplete')
	{
		$incomplete = 'selected';
	}
	else if(get_post_meta($post->ID, 'service_is_work_complete', true) == 'Complete')
	{
		$complete = 'selected';
	}
	else if(get_post_meta($post->ID, 'service_is_work_complete', true) == 'Pending')
	{
		$pending = 'selected';
	}
	else if(get_post_meta($post->ID, 'service_is_work_complete', true) == 'Assigned')
	{
		$assigned = 'selected';
	}
	else if(get_post_meta($post->ID, 'service_is_work_complete', true) == 'InTransit')
	{
		$intransit = 'selected';
	}
    echo '<p><label for="service_is_work_complete">'.__('Work Status?').'</label>&nbsp;&nbsp;&nbsp;
	<select name="service_is_work_complete"> 
	<option>Select a status</option>
	<option '.$pending.'>Pending</option>
	<option '.$assigned.'>Assigned</option>
	<option '.$intransit.'>InTransit</option>
	<option '.$incomplete.'>InComplete</option>
	<option '.$complete.'>Complete</option>
	</select>
	</p>';

	echo '<p><label for="service_additional_notes">'.__('Additional Notes').'</label>&nbsp;&nbsp;&nbsp;<textarea name="service_additional_notes">'.get_post_meta($post->ID, 'service_additional_notes', true).'</textarea></p>';

	
	$args = array(
		'role'    => 'subscriber',
		'orderby' => 'display_name',
		'order'   => 'ASC'
	);
	$customers = get_users( $args );
	
	echo '<p><label for="service_customer">'.__('Customer').'</label>&nbsp;&nbsp;&nbsp;
	<select name="service_customer"><option>Select a customer</option>';
	foreach ( $customers as $user ) {
		$selected = '';
		if ( get_post_meta($post->ID, 'service_customer', true) == esc_html($user->ID) )
		{
			$selected = 'selected';
		}
		echo '<option value="'.esc_html( $user->ID ).'" '.$selected.'>' . esc_html( $user->display_name ) . '[' . esc_html( $user->user_email ) . ']</option>';
	}
	echo '</select></p>';

	echo '<p><label for="customer_service_phone">'.__('Customer Phone').'</label>&nbsp;&nbsp;&nbsp;
	<input type="text" id="customer_service_phone" name="customer_service_phone" value="'.get_post_meta($post->ID, 'customer_service_phone', true).'"/></p>';
	
	echo '<p><label for="customer_service_address">'.__('Customer Address').'</label>&nbsp;&nbsp;&nbsp;<textarea name="customer_service_address">'.get_post_meta($post->ID, 'customer_service_address', true).'</textarea></p>';

	$args = array(
		'role'    => 'technician',
		'orderby' => 'display_name',
		'order'   => 'ASC'
	);
	$users = get_users( $args );
	
	echo '<p><label for="service_assigned_to">'.__('Assigned service to a technician').'</label>&nbsp;&nbsp;&nbsp;
	<select name="service_assigned_to"><option>Select a technician</option>';
	foreach ( $users as $user ) {
		$selected = '';
		if ( get_post_meta($post->ID, 'service_assigned_to', true) == esc_html($user->ID) )
		{
			$selected = 'selected';
		}
		echo '<option value="'.esc_html( $user->ID ).'" '.$selected.'>' . esc_html( $user->display_name ) . '[' . esc_html( $user->user_email ) . ']</option>';
	}
	echo '</select></p>';

	echo '<p><label for="technician_service_phone">'.__('Technician Phone').'</label>&nbsp;&nbsp;&nbsp;
	<input type="text" id="technician_service_phone" name="technician_service_phone" value="'.get_post_meta($post->ID, 'technician_service_phone', true).'"/></p>';
	
	echo "<script>
	jQuery(document).ready(function() {
		jQuery('#service_booked_on').datepicker({
			isRTL: true,
                dateFormat: 'yy/mm/dd',
                changeMonth: true,
                changeYear: true
		});
	});
	</script>";

	echo '<p><label for="service_booked_on">'.__('Service Booking Date').'</label>&nbsp;&nbsp;&nbsp;
	<input type="text" id="service_booked_on" name="service_booked_on" value="'.get_post_meta($post->ID, 'service_booked_on', true).'"/></p>';
	
	echo '<p><label for="service_booked_on_time">'.__('Service Booking Time').'</label>&nbsp;&nbsp;&nbsp;
	<select name="service_booked_on_time_hr"><option>Select hour</option>';
	for($i = 0; $i<=23 ; $i++) {
		$selected = '';
		$value = str_pad($i, 2, "0", STR_PAD_LEFT);
		if ( get_post_meta($post->ID, 'service_booked_on_time_hr', true) == $value )
		{
			$selected = 'selected';
		}
		echo '<option value="'.$value.'" '.$selected.'>' . $value . '</option>';
	}
	echo '</select>';
	
	echo '<select name="service_booked_on_time_min"><option>Select min</option>';
	for($i = 0; $i<=59 ; $i++) {
		$selected = '';
		$value = str_pad($i, 2, "0", STR_PAD_LEFT);
		if ( get_post_meta($post->ID, 'service_booked_on_time_min', true) == $value )
		{
			$selected = 'selected';
		}
		echo '<option value="'.$value.'" '.$selected.'>' . $value . '</option>';
	}
	echo '</select></p>';

	echo '
	<input type="hidden" id="service_latitude" name="service_latitude" value="'.get_post_meta($post->ID, 'service_latitude', true).'"/>';
	
	echo '
	<input type="hidden" id="service_longitude" name="service_longitude" value="'.get_post_meta($post->ID, 'service_longitude', true).'"/>';
		
	echo '<p style="color: #0000FF;font-size: 15px">If you have questions, concerns or customisation, feel free to email at <a href="mailto:info@bucklit.com.au">info@bucklit.com.au</a></p>';
	
}

add_action( 'save_post_services', 'servicetracker_save_meta_boxes_data', 10, 2 );
function servicetracker_save_meta_boxes_data( $post_id ){
    // check for nonce to top xss
    if ( !isset( $_POST['servicetracker_meta_box_nonce'] ) || !wp_verify_nonce( $_POST['servicetracker_meta_box_nonce'], basename( __FILE__ ) ) ){
        return;
    }

    // check for correct user capabilities - stop internal xss from customers
    if ( ! current_user_can( 'edit_post', $post_id ) ){
        return;
    }

    // update status
    if ( isset( $_REQUEST['service_is_work_complete'] ) ) {
        update_post_meta( $post_id, 'service_is_work_complete', sanitize_text_field( $_POST['service_is_work_complete'] ) );
    }
	// update notes
    if ( isset( $_REQUEST['service_additional_notes'] ) ) {
        update_post_meta( $post_id, 'service_additional_notes', sanitize_text_field( $_POST['service_additional_notes'] ) );
    }
	// update customer
    if ( isset( $_REQUEST['service_customer'] ) ) {
        update_post_meta( $post_id, 'service_customer', sanitize_text_field( $_POST['service_customer'] ) );
    }

	// update customer
    if ( isset( $_REQUEST['customer_service_address'] ) ) {
        update_post_meta( $post_id, 'customer_service_address', sanitize_text_field( $_POST['customer_service_address'] ) );
    }

	// update customer phone
    if ( isset( $_REQUEST['customer_service_phone'] ) ) {
        update_post_meta( $post_id, 'customer_service_phone', sanitize_text_field( $_POST['customer_service_phone'] ) );
    }

	// update assigned to
    if ( isset( $_REQUEST['service_assigned_to'] ) ) {
        update_post_meta( $post_id, 'service_assigned_to', sanitize_text_field( $_POST['service_assigned_to'] ) );
    }

	// update technician phone
    if ( isset( $_REQUEST['technician_service_phone'] ) ) {
        update_post_meta( $post_id, 'technician_service_phone', sanitize_text_field( $_POST['technician_service_phone'] ) );
    }

	// update date
    if ( isset( $_REQUEST['service_booked_on'] ) ) {
        update_post_meta( $post_id, 'service_booked_on', sanitize_text_field( $_POST['service_booked_on'] ) );
    }
	// update time hour
    if ( isset( $_REQUEST['service_booked_on_time_hr'] ) ) {
        update_post_meta( $post_id, 'service_booked_on_time_hr', sanitize_text_field( $_POST['service_booked_on_time_hr'] ) );
    }
	// update time min
    if ( isset( $_REQUEST['service_booked_on_time_min'] ) ) {
        update_post_meta( $post_id, 'service_booked_on_time_min', sanitize_text_field( $_POST['service_booked_on_time_min'] ) );
    }
	
	// update service_latitude
    if ( isset( $_REQUEST['service_latitude'] ) ) {
        update_post_meta( $post_id, 'service_latitude', sanitize_text_field( $_POST['service_latitude'] ) );
    }	
	// update service_longitude
    if ( isset( $_REQUEST['service_longitude'] ) ) {
        update_post_meta( $post_id, 'service_longitude', sanitize_text_field( $_POST['service_longitude'] ) );
    }
	
}

/* show service column values in the admin grid */
add_action( 'manage_services_posts_custom_column', 'servicetracker_tracking_column', 10, 2);
function servicetracker_tracking_column( $column, $post_id ) {
  if ( 'service_customer' === $column ) {
    echo getUserName(get_post_meta( $post_id , 'service_customer', true ));
  }
  else if ( 'service_assigned_to' === $column ) {
    echo getUserName(get_post_meta( $post_id , 'service_assigned_to', true ));
  }
  else if ( 'service_booked_on' === $column ) {
    echo get_post_meta( $post_id , 'service_booked_on', true ) . ' '. get_post_meta( $post_id , 'service_booked_on_time_hr', true ) .':'.get_post_meta( $post_id , 'service_booked_on_time_min', true );
  }
  else
  {
	echo get_post_meta( $post_id , $column, true );
  }
}

function getUserName($id)
{
	$user_info = get_userdata($id);
	return $user_info->user_login;
}
  
function servicetracker_json_response($code = 200, $message = null)
{
    // clear the old headers
    header_remove();
    // set the actual code
    http_response_code($code);
    // set the header to make sure cache is forced
    header("Cache-Control: no-transform,public,max-age=300,s-maxage=900");
    // treat this as json
    header('Content-Type: application/json');
    $status = array(
        200 => '200 OK',
        400 => '400 Bad Request',
        422 => 'Unprocessable Entity',
        500 => '500 Internal Server Error'
        );
    // ok, validation error, or failure
    header('Status: '.$status[$code]);
    // return the encoded json
    return json_encode(array(
        'status' => $code < 300, // success or not?
        'message' => $message
        ));
}

/*
 * Insert some additional data to the JWT Auth plugin
 */
function servicetracker_auth_function($data, $user) { 
    $data['user_role'] = $user->roles; 
    $data['user_id'] = $user->ID; 
    $data['avatar']= get_avatar_url($user->ID);
    return $data; 
} 
add_filter( 'jwt_auth_token_before_dispatch', 'servicetracker_auth_function', 10, 2 );

//{{site}}/wp-json/nonce/v2/get
$nonce = 'invalid';
add_action('wp_loaded', function () {
  global $nonce;
  $nonce = wp_create_nonce('wp_rest');
});

add_action('rest_api_init', function () {

  $json = file_get_contents('php://input');
  $obj = json_decode($json, TRUE);
  $post_id = sanitize_text_field($obj['post_id']);	
  $loggedinid = sanitize_text_field($obj['loggedinid']);	
  $status = sanitize_text_field($obj['status']);
  $notes = sanitize_text_field($obj['notes']);

//{siteurl}/wp-json/service/v2/update
  register_rest_route('service/v2', 'update', [
    'methods' => 'POST',
    'callback' => function () use ($post_id, $loggedinid, $status, $notes) {
	
	// check for correct user capabilities - stop internal xss from customers
	// if ( ! current_user_can( 'edit_post', $post_id ) ){
	// 	$res = ['statusCode' => 400, 'message' => 'Contact web administrator - User should have edit post permission.'];
	// 	return $res;
	// }

	// update status
	if ( $status != '' ) {
		update_post_meta( $post_id, 'service_is_work_complete', $status );
	}
	
	// update notes
	if ( $notes != '' ) {
		$history = get_post_meta( $post_id, 'service_additional_notes', true );
		update_post_meta( $post_id, 'service_additional_notes',  $history . PHP_EOL . '<br>' . '['.getUserName($loggedinid).']' . ': ' . $notes . PHP_EOL . '<br>' );
	}
	
	$res = ['statusCode' => 200, 'message' => 'updated'];
	return servicetracker_json_response($res);

    },
  ]);


/*
{{site}}/wp-json/wp/v2/services/?orderby=title&order=asc&search=&meta_key=service_customer&meta_value=3
*/
  register_rest_route('service/v2', 'list', [
    'methods' => 'GET',
    'callback' => function () use ($post_id, $status, $notes) {
	$res = ['statusCode' => 200, 'message' => 'updated'];
	return servicetracker_json_response($res);

    },
  ]);

//add custom fields to rest response - mobile app
  register_rest_field(
	'services', 
	'custom_fields',
	array(
		'get_callback'    => 'servicetracker_get_custom_service_fields', // custom function name 
		'update_callback' => null,
		'schema'          => null,
		 )
	);

  register_rest_route('nonce/v2', 'verify', [
    'methods' => 'GET',
    'callback' => function () use ($user) {
      $nonce = !empty($_GET['nonce']) ? $_GET['nonce'] : false;
      error_log("verify $nonce $user");
      return [
        'valid' => (bool) wp_verify_nonce($nonce, 'wp_rest'),
        'user' => $user,
      ];
    },
  ]);

//wp-json/userd/v2/list/?email=
  register_rest_route('userd/v2', 'list', [
    'methods' => 'GET',
    'callback' => function ()  {
      $email = !empty(sanitize_email($_GET['email'])) ? sanitize_email($_GET['email']) : false;
	  $user = get_user_by( 'email', $email );
      error_log("verify user data");
      return [
        'userId' =>  $user->ID
      ];
    },
  ]);
});

function servicetracker_get_custom_service_fields( $post, $field_name, $request) {
	$result = [];

	/*service customer details */
	$service_customer_id =  get_post_meta( get_the_ID(), 'service_customer', true );
	$service_customer = get_user_by( 'id', $service_customer_id );
	$result['service_customer']['username'] = $service_customer->user_login;
	$result['service_customer']['email'] = $service_customer->user_email;
	$result['service_customer']['first_name'] = $service_customer->user_firstname;
	$result['service_customer']['last_name'] = $service_customer->user_lastname;
	$result['service_customer']['display_name'] = $service_customer->display_name;
	$result['service_customer']['id'] = $service_customer->ID;

	/* technician details */
	$service_technician_id = get_post_meta( get_the_ID(), 'service_assigned_to', true );
	$service_technician = get_user_by( 'id', $service_technician_id );
	$result['service_technician']['username'] = $service_technician->user_login;
	$result['service_technician']['email'] = $service_technician->user_email;
	$result['service_technician']['first_name'] = $service_technician->user_firstname;
	$result['service_technician']['last_name'] = $service_technician->user_lastname;
	$result['service_technician']['display_name'] = $service_technician->display_name;
	$result['service_technician']['id'] = $service_technician->ID;

	$result['customer_service_phone'] = get_post_meta( get_the_ID(), 'customer_service_phone', true );	
	$result['technician_service_phone'] = get_post_meta( get_the_ID(), 'technician_service_phone', true );	

	$result['customer_service_address'] = get_post_meta( get_the_ID(), 'customer_service_address', true );
	$result['service_booked_on'] = get_post_meta( get_the_ID(), 'service_booked_on', true );
	
	$result['service_booked_on_time_hr'] = get_post_meta( get_the_ID(), 'service_booked_on_time_hr', true );
	$result['service_booked_on_time_min'] = get_post_meta( get_the_ID(), 'service_booked_on_time_min', true );
	
	$result['service_booked_on_datetime'] = "Date not set";
	if(!empty($result['service_booked_on']))
	{
		$service_datetime = new DateTime($result['service_booked_on'].' '.$result['service_booked_on_time_hr'] .':'. $result['service_booked_on_time_min'] . ':00' );
		$result['service_booked_on_datetime'] = $service_datetime->format('d-m-Y g:i A');;
	}
	/*after service fields */	
	$result['service_is_work_complete'] = strtoupper( get_post_meta( get_the_ID(), 'service_is_work_complete', true ) );
	
	if($result['service_is_work_complete'] == 'SELECT A STATUS')
	$result['service_is_work_complete'] = 'Status not set';
	
	$result['service_additional_notes'] = get_post_meta( get_the_ID(), 'service_additional_notes', true );

	$result['service_latitude'] = get_post_meta( get_the_ID(), 'service_latitude', true );
	$result['service_longitude'] = get_post_meta( get_the_ID(), 'service_longitude', true );
	
	return $result;
}

// add meta fields in the rest response
if( ! function_exists( 'post_meta_request_params' ) ) :
	function post_meta_request_params( $args, $request )
	{
		$args += array(
			'meta_key'   => $request['meta_key'],
			'meta_value' => $request['meta_value'],
			'meta_query' => $request['meta_query'],
		);

	    return $args;
	}
	add_filter( 'rest_post_query', 'post_meta_request_params', 99, 2 );
	add_filter( 'rest_page_query', 'post_meta_request_params', 99, 2 ); // Add support for `page`
	add_filter( 'rest_services_query', 'post_meta_request_params', 99, 2 ); // Add support for `my-custom-post`
endif;
