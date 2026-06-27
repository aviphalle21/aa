<?php
// Payment gateway / UPI settings for the user checkout flow.
// Direct UPI does not provide a browser callback, so this app can auto-confirm
// after a short payment window. Set PAYMENT_AUTO_CONFIRM_DEMO to false in production
// when a real gateway/webhook updates payments.payment_status.
const PAYMENT_UPI_ID = 'pratikshingare2002@okicici';
const PAYMENT_PAYEE_NAME = 'Saraswati Abhyasika';
const PAYMENT_CURRENCY = 'INR';
const PAYMENT_AUTO_CONFIRM_DEMO = true;
const PAYMENT_AUTO_CONFIRM_AFTER_SECONDS = 15;
// Set PAYMENT_AUTO_CONFIRM_DEMO to false in production and update payments.payment_status
// from your real gateway webhook/settlement script when money is received.
const PAYMENT_UPI_ID = 'pratikshingare2002@okicici';
const PAYMENT_PAYEE_NAME = 'Saraswati Abhyasika';
const PAYMENT_CURRENCY = 'INR';
const PAYMENT_AUTO_CONFIRM_DEMO = false;
const PAYMENT_POLL_SECONDS = 5;
const PAYMENT_WEBHOOK_SECRET = 'change-this-secret';
?>
