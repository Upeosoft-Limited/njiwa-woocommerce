=== Njiwa for WooCommerce ===
Contributors: upeoai
Tags: whatsapp, woocommerce, order notifications, sms, kenya
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

WhatsApp your customers when their order is paid, sent or cancelled, and get a message yourself when one comes in.

== Description ==

Njiwa for WooCommerce sends a WhatsApp message when an order changes status,
and tells you when a new order arrives.

* Six events, each with its own on switch and its own wording
* Placeholders filled from the order: name, number, total, items, links
* Sent in the background, so the checkout never waits
* Every send written on the order as a note, with Njiwa's message id
* Nothing sent twice, even if a queue job runs twice
* Local phone numbers resolved against the order's own country

It needs a Njiwa account. Start with a test key: everything works, every
message is stored, and nothing reaches a real phone until you swap the key.

== Installation ==

1. Upload the plugin to wp-content/plugins and activate it.
2. Go to WooCommerce, Settings, Njiwa.
3. Paste your API key, save, and press Test connection.

== Frequently Asked Questions ==

= Does it slow down the checkout? =

No. Messages are queued through Action Scheduler and sent after the customer
has been sent on their way.

= What if a customer has no phone number? =

Nothing is sent, and a note saying so is added to the order.

= Can it receive replies? =

Not yet. Verifying an inbound webhook needs a signing secret the console does
not yet show, and receiving without verifying would mean trusting anybody who
found the address.

== Changelog ==

= 0.1.0 =
* First release.
