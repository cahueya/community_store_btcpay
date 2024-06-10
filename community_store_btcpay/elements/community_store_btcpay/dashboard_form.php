<?php 
defined('C5_EXECUTE') or die(_("Access Denied."));
extract($vars);
?>
<div class="form-group">
    <?= $form->label('btcpayCurrency',t("Currency")); ?>
    <?= $form->select('btcpayCurrency',$currencies,$btcpayCurrency?$btcpayCurrency:'USD');?>
</div>
<div class="form-group">
    <label><?= t("Discount")?><?= " " ?><?= t("in Percent")?></label>
    <input type="number" name="btcpayDiscount" value="<?= $btcpayDiscount?>" class="form-control">
</div>
<div class="form-group">
    <label><?= t("BTC Payserver URL")?></label>
    <input type="url" name="btcpayUrl" value="<?= $btcpayUrl?>" class="form-control">
</div>
<div class="form-group">
    <label><?= t("BTC Payserver ID")?></label>
    <input type="text" name="btcpayId" value="<?= $btcpayId?>" class="form-control">
</div>

<div class="form-group">
    <label><?= t("BTC Payserver API Key")?></label>
    <input type="text" name="btcpayKey" value="<?= $btcpayKey?>" class="form-control">
</div>

<div class="form-group">
    <label><?= t("BTC Payserver Webhook Secret")?></label>
    <input type="text" name="btcpayWebhooksecret" value="<?= $btcpayWebhooksecret?>" class="form-control">
</div>

<div class="form-group">
    <?= $form->label('btcpayMethod',t("Payment Methods")); ?>
    <?= $form->select('btcpayMethod',$paymethods,$btcpayMethod?$btcpayMethod:'BTC');?>
</div>

<div class="form-group">
    <label><?= t("Transaction Description")?></label>
    <?= $form->select('btcpayTransactionDescription',array('order'=>'Show as: "Order from ' . Config::get('concrete.site') .'"' ,'products'=>'List of products and quantities'),$btcpayTransactionDescription);?>
</div>

