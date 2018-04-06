<?php

/**
 Plugin Name: Post Notifications
 Plugin URI: http://github.com/benignware-labs/wp-post-notifications
 Description: Let users subscribe to post updates
 Version: 0.0.2
 Author: Rafael Nowrotek, Benignware
 Author URI: http://benignware.com
 License: MIT
*/

require_once 'post-notifications-helpers.php';

function post_notifications_mail($to, $subject = '', $body = '', $headers = '') {
  $headers = array(
    'Content-Type: text/html; charset=UTF-8',
    'From: '. get_bloginfo('name') .' <'. get_bloginfo('admin_email') .'>'
  );
  // $headers = 'From: '. get_bloginfo('name') .' <'. get_bloginfo('admin_email') .'>' . "\r\n";

  $result = wp_mail( $to, $subject, $body, $headers );
}

function post_notifications_wp_loaded() {
  // Wordpress loaded
}

add_action( 'wp_loaded', 'post_notifications_wp_loaded' );


global $post_notifications_db_version;
$post_notifications_db_version = '1.0';

function post_notifications_install() {
	global $wpdb;
	global $post_notifications_db_version;

	$table_name = $wpdb->prefix . 'post_notifications';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		`id` mediumint(9) NOT NULL AUTO_INCREMENT,
		`created_at` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		`post_id` mediumint(9) NOT NULL,
		`hash` varchar(128) DEFAULT '' NOT NULL,
		`email` varchar(128) DEFAULT '' NOT NULL,
		PRIMARY KEY (`id`),
    UNIQUE KEY `post_id_email_index` (`post_id`,`email`)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'post_notifications_db_version', $post_notifications_db_version );
}
register_activation_hook( __FILE__, 'post_notifications_install' );

function post_notifications_query_vars( $vars ) {
  $vars[] = 'pn_unsubscribe';
  $vars[] = 'pn_mail_test';
  return $vars;
}
add_filter( 'query_vars', 'post_notifications_query_vars' );

function post_notifications_request( $request ) {
  $pn_unsubscribe = $request['pn_unsubscribe'];
  if ($pn_unsubscribe) {
    // Unsubscribe user
    $result = post_notifications_delete($pn_unsubscribe);
    if ($result) {
      // Subscription was successfully deleted
      $url = esc_url( remove_query_arg( 'pn_unsubscribe', $_SERVER['REQUEST_URI'] ) );
      wp_redirect( $url );
      exit;
    } else {
      // TODO: Handle error
    }
  }

  if ($request['pn_mail_test']) {
    $result = post_notifications_mail(get_bloginfo('admin_email'), 'Test', 'This was just a test...');
    echo "TEST MAIL";
    exit;
  }

  return $request;
}
add_filter( 'request', 'post_notifications_request' );

function post_notifications_get_table_name() {
  global $wpdb;

  return $wpdb->prefix . 'post_notifications';
}

function post_notifications_insert($post_id, $email) {
  global $wpdb;

  $table_name = post_notifications_get_table_name();
  $current_time = current_time( 'mysql' );
  $hash = sha1($current_time);

  $result = $wpdb->insert(
		$table_name,
		array(
			'created_at' => $current_time,
      'hash' => $hash,
			'post_id' => $post_id,
			'email' => $email,
		)
	);

  return $result;
}

function post_notifications_get_subscriptions($post_id) {
  global $wpdb;

  $table_name = post_notifications_get_table_name();
  $query = $wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d", $post_id);
  $results = $wpdb->get_results( $query );
  return $results;
}

function post_notifications_get_by_post_id_and_email($post_id, $email) {
  global $wpdb;

  $table_name = post_notifications_get_table_name();
  $query = "SELECT * FROM $table_name WHERE post_id = %d AND email = %s";
  return $wpdb->get_row( $wpdb->prepare($query, $post_id, $email) );
}

function post_notifications_delete($hash) {
  global $wpdb;

  $table_name = post_notifications_get_table_name();
  $query = "DELETE FROM $table_name WHERE hash = %s";
  $result = $wpdb->query( $wpdb->prepare($query, $hash) );
  return $result;
}

function post_notifications_get_post_data() {
  $form_data = array();
  // This part fetches everything that has been POSTed, sanitizes them and lets us use them as $form_data['subject']
  foreach ( $_POST as $field => $value ) {
    if ( get_magic_quotes_gpc() ) {
      $value = stripslashes( $value );
    }
    $form_data[$field] = strip_tags( $value );
  }
  return $form_data;
}

function post_notifications_get_updated_message($subscription) {
  $title = 'Hello from ' . get_bloginfo('name') . ',';
  $post_id = $subscription->post_id;
  $hash = $subscription->hash;
  $permalink = get_permalink($post_id);
  $post_title = get_the_title($post_id);
  $unsubscribe_link = add_query_arg( 'pn_unsubscribe', $hash, $permalink );
  $template = dirname(__FILE__) . '/post-notifications-mail-template.php';

  $content = post_notifications_render($template, array(
    'title' => $title,
    'permalink' => $permalink,
    'unsubscribe_link' => $unsubscribe_link,
    'post_title' => $post_title
  ));

  return $content;
}

function post_notifications_post_updated($post_id, $post_after, $post_before) {
  global $__post_notifications_sent;
  $affected_subscriptions = post_notifications_get_subscriptions($post_id);
  foreach ($affected_subscriptions as $subscription) {
    $subject = 'Post has been updated';
    $message = post_notifications_get_updated_message($subscription);
    $to = $subscription->email;
    // Send message to subscriber
    // echo "SEND MESSAGE to {$subscription->email} <pre>$message</pre><br/>";
    // exit;
    post_notifications_mail($to, $subject, $message);
  }
  $_SESSION['POST_NOTIFICATIONS_SENT'] = count($affected_subscriptions);
}
add_action( 'post_updated', 'post_notifications_post_updated', 10, 3 ); //don't forget the last argument to allow all three arguments of the function

function post_notifications_start_session() {
  if(!session_id()) {
    session_start();
  }
}
add_action('init', 'post_notifications_start_session', 1);

function post_notifications_end_session() {
    session_destroy ();
}
add_action('wp_logout', 'post_notifications_end_session');
add_action('wp_login', 'post_notifications_end_session');

function post_notifications_sent_notice() {
  $sent_count = $_SESSION['POST_NOTIFICATIONS_SENT'];
  if ($sent_count > 0) {
    ?>
    <div id="post-notifications-notice-sent" class="notice notice-success is-dismissible">
      <p class="notice-message"><?php printf( __( 'Post notification sent to %d subscribed users.', 'post-notifications' ), $sent_count ); ?></p>
    </div>
    <?php
    $_SESSION['POST_NOTIFICATIONS_SENT'] = 0;
  }
}
add_action( 'admin_notices', 'post_notifications_sent_notice' );


// Sanitize form
function post_notifications_sanitize_output($html, $form_name = null, $hidden = array()) {
  $is_valid = $form_name ? false : true;
  if (!$is_valid) {
    // Parse input
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html );
    // Get the container element
    $container = $doc->getElementsByTagName('body')->item(0)->firstChild;
    $container->setAttribute("data-$form_name", 'test');
    // Get the form element
    $form = $doc->getElementsByTagName( 'form' )->item(0);
    if ($form) {
      $action = $form->getAttribute('action') ?: $_SERVER['REQUEST_URI'];
      $form->setAttribute('action', $action);
      $method = $form->getAttribute('method') ?: 'POST';
      $form->setAttribute('method', $method);
      $input_elements = $doc->getElementsByTagName( 'input' );
      $hidden_fields = array();
      // Go through present hidden fields and add update values
      foreach ($input_elements as $input_element) {
        $input_type = $input_element->getAttribute('type');
        $input_name = $input_element->getAttribute('name');
        if ($input_type === 'hidden' && array_key_exists($hidden, $input_name)) {
          $hidden_fields[] = $input_name;
          $input_element->setAttribute($input_name, $hidden[$input_name]);
        }
      }
      // And add the missing hidden fields
      foreach ($hidden as $field => $value) {
        if (!in_array($field, $hidden_fields)) {
          $input_element = $doc->createElement('input');
          $input_element->setAttribute('type', 'hidden');
          $input_element->setAttribute('name', $field);
          $input_element->setAttribute('value', $hidden[$field]);
          $form->appendChild($input_element);
        }
      }
    } else {
      // TODO: Handle error "Output must contain a form"
    }
    if (!$is_valid) {
      $html = preg_replace('~(?:<\?[^>]*>|<(?:!DOCTYPE|/?(?:html|head|body))[^>]*>)\s*~i', '', $doc->saveHTML());
    }
  }
  return $html;
}



/**
 * Post Notifications Shortcode
 */
function post_notifications_shortcode( $atts = array() ) {
  $messages = array(
    'empty' => __('This field cannot be empty', 'post-notifications'),
    'email_invalid' => __('You have to enter a valid e-mail address', 'post-notifications')
  );

  $atts = shortcode_atts(array(
    'post_id' => get_the_ID(),
    'fields' => 'email',
    'required' => 'email',
    'title' => __('Subscribe to this post!', 'post-notifications'),
    'description' => __('Get notified on updates', 'post-notifications'),
    'template' => dirname(__FILE__) . '/post-notifications-template.php'
  ), $atts, 'post_notifications');

  $post_id = $atts['post_id'];

  // Get arrays from string lists
  if (is_string($atts['fields'])) {
    $atts['fields'] = post_notifications_split_val($atts['fields']);
  }

  if (is_string($atts['required'])) {
    $atts['required'] = post_notifications_split_val($atts['required']);
  }

  // Extract attributes
  foreach($atts as $key => $value) {
    $$key = $atts[$key];
  }

  // Get request data
  $request = post_notifications_get_request();

  $errors = array();

  // Check against method
  if ( $request['method'] === 'POST') {
    $data = $request['data'];

    // If the required fields are empty, switch $error to TRUE and set the result text to the shortcode attribute named 'error_empty'
    foreach ( $required as $key ) {
      $value = trim( $data[$key] );
      if ( empty( $value ) ) {
        $errors[$key] = $messages['empty'];
      }
    }

    // Check for post id
    if ( empty( $data['post_id'] ) ) {
      $errors['post_id'] = $messages['empty'];
    }

    // And if the e-mail is not valid, switch $error to TRUE and set the result text to the shortcode attribute named 'error_noemail'
    if ( !$errors['email'] && !is_email( $data['email'] ) ) {
      $errors['email'] = $messages['email_invalid'];
    }

    if ( !count(array_keys($errors)) ) {
      // Actually subscribe the user's email to post updates
      post_notifications_insert($data['post_id'], $data['email']);

      // Success
      $success = true;
    }
  }

  $output = post_notifications_render($template, array(
    'title' => $title,
    'description' => $description,
    'required' => $required,
    'fields' => $fields,
    'success' => $success,
    'errors' => $errors,
    'action' => $action,
    'data' => $data
  ));

  // Sanitize form with identifier and add hidden fields
  $output = post_notifications_sanitize_output($output, 'post-notifications', array(
    'post_id' => $post_id
  ));

  return $output;
}
add_shortcode( 'post_notifications', 'post_notifications_shortcode' );


/** Enqueue Scripts */
function post_notifications_enqueue_scripts() {
  wp_enqueue_script( 'post-notifications', plugin_dir_url( __FILE__ ) . 'dist/post-notifications.js' );
}
add_action('wp_enqueue_scripts', 'post_notifications_enqueue_scripts');
?>
