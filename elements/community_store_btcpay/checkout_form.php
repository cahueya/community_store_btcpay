<?php

defined('C5_EXECUTE') or die('Access Denied.');
extract($vars);
?>

<p><?= t('Click "Complete Order" to proceed to BTCPay Server.') ?></p>

<script>
$(function () {
    var form = $('#store-checkout-form-group-payment');
    var submitButton = form.find('[data-payment-method-id="<?= (int) $pmID ?>"] .store-btn-complete-order');

    form.on('submit', function () {
        submitButton.prop('disabled', true);
    });
});
</script>
