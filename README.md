# wp-post-notifications

Let users subscribe to post updates

## Usage

Place shortcode inside post content

```
[post_notifications]
```

### Customize

Customize markup by programmatically adding attributes to shortcode

```php
// Customize post notifications
function custom_shortcode_atts_post_notifications($out, $pairs, $atts, $shortcode) {
  $result = array_merge($out, array(
    'label_email' => 'Sign up for notifications',
    'placeholder_email' => 'Email',
    'class' => 'card',
    'form_class' => 'card-body',
    'footer_class' => '',
    'field_class' => 'form-group',
    'input_class' => 'form-control',
    'input_error_class' => 'is-invalid',
    'message_class' => 'invalid-feedback',
    'button_class' => 'btn btn-primary'
  ), $atts);
  return $result;
}
add_filter( 'shortcode_atts_post_notifications', 'custom_shortcode_atts_post_notifications', 10, 4);
```
