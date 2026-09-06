<?php

defined('C5_EXECUTE') or die('Access Denied.');
extract($vars);
?>

<div class="alert alert-info">
    <?= t('Payments use the Community Store currency: %s. Currency conversion is not performed by this payment method.', '<strong>' . h($storeCurrency) . '</strong>') ?>
</div>

<div class="form-group">
    <?= $form->label('btcpayUrl', t('BTCPay Server URL')) ?>
    <?= $form->url('btcpayUrl', $btcpayUrl, ['placeholder' => 'https://btcpay.example.com']) ?>
</div>

<div class="form-group">
    <?= $form->label('btcpayId', t('BTCPay Server Store ID')) ?>
    <?= $form->text('btcpayId', $btcpayId) ?>
</div>

<div class="form-group">
    <?= $form->label('btcpayKey', t('BTCPay Server API key')) ?>
    <?= $form->password('btcpayKey', '', ['autocomplete' => 'new-password']) ?>
    <div class="form-text">
        <?= $btcpayKeyConfigured
            ? t('An API key is already configured. Leave this field blank to keep it unchanged.')
            : t('Enter an API key that can create and view invoices for this BTCPay Server store.') ?>
    </div>
</div>

<div class="form-group">
    <?= $form->label('btcpayWebhooksecret', t('BTCPay Server webhook secret')) ?>
    <?= $form->password('btcpayWebhooksecret', '', ['autocomplete' => 'new-password']) ?>
    <div class="form-text">
        <?= $btcpayWebhookSecretConfigured
            ? t('A webhook secret is already configured. Leave this field blank to keep it unchanged.')
            : t('Create a webhook in BTCPay Server and enter its secret here.') ?>
    </div>
</div>

<div class="form-group">
    <label class="form-label"><?= t('Webhook URL') ?></label>
    <input type="text" class="form-control font-monospace" value="<?= h($webhookUrl) ?>" readonly>
    <div class="form-text">
        <?= t('Configure this URL in BTCPay Server for the InvoiceSettled, InvoiceExpired and InvoiceInvalid events.') ?>
    </div>
</div>
