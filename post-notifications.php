<?php

/**
 Plugin Name: Post notifications
 Plugin URI: http://github.com/benignware-labs/wp-post-notifications
 Description: Let users subscribe to post updates
 Version: 0.0.1
 Author: Rafael Nowrotek, Benignware
 Author URI: http://benignware.com
 License: MIT
*/

function post_notifications_mail($to, $subject = '', $body = '', $headers = '') {
  $headers = array(
    'Content-Type: text/html; charset=UTF-8',
    'From: '. get_bloginfo('name') .' <'. get_bloginfo('admin_email') .'>'
  );
  $headers = 'From: '. get_bloginfo('name') .' <'. get_bloginfo('admin_email') .'>' . "\r\n";

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

  $content = <<< EOT
$title\n
\n
"$post_title" has been updated:\n
$permalink\n
\n
Unsubscribe:\n
$unsubscribe_link\n
EOT;

  return $content;
}

function post_notifications_post_updated($post_id, $post_after, $post_before) {
  $affected_subscriptions = post_notifications_get_subscriptions($post_id);
  foreach ($affected_subscriptions as $subscription) {
    $subject = 'Post has been updated';
    $message = post_notifications_get_updated_message($subscription);
    $to = $subscription->email;
    // Send message to subscriber
    // echo "SEND MESSAGE to {$subscription->email} <pre>$message</pre><br/>";
    post_notifications_mail($to, $subject, $message);
  }
}
add_action( 'post_updated', 'post_notifications_post_updated', 10, 3 ); //don't forget the last argument to allow all three arguments of the function


/**
 * Post Notifications Shortcode
 */
function post_notifications_shortcode( $atts = array() ) {
  $current_user = wp_get_current_user();

  $atts = shortcode_atts(array(
    'post_id' => '',
    'class' => 'post-notifications',
    'form_class' => '',
    'form_error_class' => '',
    'field_class' => 'field',
    'field_error_class' => '',
    "label_email" => "Email",
    "label_submit" => "Send",
    "input_class" => 'input',
    "input_error_class" => "error",
    "message_class" => "message",
    "message_error_class" => "error",
    "button_class" => "button",
    "footer_class" => "",
    // the error message when at least one of the required fields are empty:
    "message_error_empty" => "Please fill in all the required fields.",
    // the error message when the e-mail address is not valid:
    "message_error_email_invalid" => "Please enter a valid e-mail address.",
    // the error message when the e-mail address has already been subscribed to the post
    "message_error_email_exists" => "E-mail has already been subscribed to this post.",
    // and the success message when the e-mail is sent:
    "message_success" => "Thanks for your e-mail! We'll get back to you as soon as we can.",
    'message_success_class' => 'success'
  ), $atts, 'post_notifications');

  // Extract atts
  $post_id = $atts['post_id'];
  $class = $atts['class'];
  $form_class = $atts['form_class'];
  $form_error_class = $atts['form_error_class'];
  $footer_class = $atts['footer_class'];
  $field_class = $atts['field_class'];
  $field_error_class = $atts['field_error_class'];
  $email = $atts['email'];
  $label_email = $atts['label_email'];
  $label_submit = $atts['label_submit'];
  $placeholder_email = $atts['placeholder_email'];
  $input_class = $atts['input_class'];
  $input_error_class = $atts['input_error_class'];
  $message_class = $atts['message_class'];
  $message_error_class = $atts['message_error_class'];
  $message_error_empty = $atts['message_error_empty'];
  $message_error_email_invalid = $atts['message_error_email_invalid'];
  $message_error_email_exists = $atts['message_error_email_exists'];
  $message_success = $atts['message_success'];
  $message_success_class = $atts['message_success_class'];
  $button_class = $atts['button_class'];

  $post_id = $atts['post_id'] ? $atts['post_id'] : get_the_ID();

  // Get classes
  $form_classes = explode(' ', $form_class);
  $field_classes = explode(' ', $field_class);
  $input_classes = explode(' ', $input_class);
  $message_classes = explode(' ', $message_class);

  // If the <form> element is POSTed, run the following code
  if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {

      $error = false;
      $form_data = post_notifications_get_post_data();
      $required_fields = array( 'email', 'post_id' ); // Set required fields

      // If the required fields are empty, switch $error to TRUE and set the result text to the shortcode attribute named 'error_empty'
      foreach ( $required_fields as $required_field ) {
        $value = trim( $form_data[$required_field] );
        if ( empty( $value ) ) {
            $error = true;
            $message = $essage_error_empty;
        }
      }

      // And if the e-mail is not valid, switch $error to TRUE and set the result text to the shortcode attribute named 'error_noemail'
      if ( ! is_email( $form_data['email'] ) ) {
        $error = true;
        $message = $essage_error_email_invalid;
      }

      if ( !$error ) {
        // Validate uniqueness against database
        $row = post_notifications_get_by_post_id_and_email($form_data['post_id'], $form_data['email']);
        if ($row) {
          $error = true;
          $message = $message_error_email_exists;
          // TODO: Unsubscribe with confirmation mail
        }
      }

      if ( !$error ) {
        // Success
        $message = $message_success;

        // Add success classes
        array_push($message_classes, $message_success_class);

        // Actually subscribe the user's email to post updates
        post_notifications_insert($form_data['post_id'], $form_data['email']);
      } else {
        // Error

        // Add error classes
        array_push($form_classes, $form_error_class);
        array_push($field_classes, $field_error_class);
        array_push($input_classes, $input_error_class);
        array_push($message_classes, $message_error_class);
      }
    }

    // Set up css classes
    $form_class = implode(' ', $form_classes);
    $field_class = implode(' ', $field_classes);
    $input_class = implode(' ', $input_classes);
    $message_class = implode(' ', $message_classes);

    // TODO: Allow for dynamic templates
    $email_form = '<div class="' . $class . '">
      <form class="' . $form_class . '" method="post" action="' . get_permalink() . '">
        <div class="' . $field_class . '">
          <label for="cf_email">' . $label_email . '</label>
          <input class="' . $input_class . '" placeholder="' . $placeholder_email . '" type="text" name="email" id="cf_email" size="50" maxlength="50" value="' . $form_data['email'] . '" />
          ' . ($message ? '<div class="' . $message_class . '">' . $message . '</div>' : '') . '
          <input type="hidden" name="post_id" value="' . $post_id . '"/>
        </div>
        <div class="' . $footer_class . '">
          <input class="' . $button_class . '" type="submit" value="' . $label_submit . '" name="send" id="cf_send" />
        </div>
      </form>
    </div>';

    return $email_form;
}
add_shortcode( 'post_notifications', 'post_notifications_shortcode' );

?>
