<?php

defined('C5_EXECUTE') or die('Access Denied.');
extract($vars);
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php else: ?>
    <script src="<?= h($host) ?>/modal/btcpay.js"></script>
    <script>
    $(function () {
        var form = $('#store-checkout-redirect-form');
        var invoiceId = <?= json_encode((string) $invoiceId, JSON_UNESCAPED_SLASHES) ?>;
        var cancelReturn = <?= json_encode((string) $cancelReturn, JSON_UNESCAPED_SLASHES) ?>;

        form.on('submit', function (event) {
            event.preventDefault();
        });
        form.find('.btn').remove();

        window.btcpay.showInvoice(invoiceId);
        window.btcpay.onModalWillLeave(function () {
            window.location.href = cancelReturn;
        });
    });
    </script>
<?php endif; ?>
