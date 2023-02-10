<?php defined('C5_EXECUTE') or die(_("Access Denied."));
extract($vars);
?>

<script src ="<?= $host; ?>/modal/btcpay.js"></script>
<script type="text/javascript">

    $(function () {
        $("#store-checkout-redirect-form").submit(function(e){
            e.preventDefault();
        });
        $("#store-checkout-redirect-form .btn").remove();
        var InvoiceId = '<?= $InvoiceId; ?>';
        var returnURL = '<?= $returnURL; ?>';
        var cancelReturn = '<?= $cancelReturn; ?>';
        window.btcpay.showInvoice(InvoiceId);

        window.btcpay.onModalWillLeave(() => {
        // enable the pay button again
        window.location.href = cancelReturn;
        })
    });

</script>