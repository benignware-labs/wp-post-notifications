<div class="pn">
  <?php if ($title): ?>
    <h3 class="pn-title"><?= $title ?></h3>
  <?php endif; ?>
  <?php if ($description): ?>
    <p class="pn-description"><?= $description ?></p>
  <?php endif; ?>
  <?php if ($success): ?>
    <p>
      <?= __('Successfully subscribed to this post.', 'post-notifications'); ?>
    </p>
  <?php else: ?>
    <form class="pn-form" method="POST">
      <?php if (in_array('email', $fields)) : ?>
        <p class="pn-field">
          <label class="pn-label">
            <?= __('E-mail', 'post-notifications') ?><?= in_array('email', $required) ? '*' : ''; ?>
          </label>
          <input class="pn-input-text pn-email <?= $errors['email'] ? 'pn-error' : '' ?>" placeholder="<?= __('Please enter your e-mail address', 'post-notifications') ?>" type="text" name="email" size="50" maxlength="80" value="<?= $data['email'] ?>" />
          <?php if (array_key_exists('email', $errors)): ?>
            <span class="pn-message pn-error"><?= $errors['email'] ?></span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
      <p class="pn-footer">
        <button class="pn-submit" type="submit"><?= __('Send', 'post-notifications'); ?></button>
      </p>
    </form>
  <?php endif; ?>
</div>
