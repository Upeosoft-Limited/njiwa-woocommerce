# Njiwa for WooCommerce

WhatsApp your customers when their order is paid, sent or cancelled, and get a
message yourself when one comes in.

## Install

1. Zip the `njiwa-woocommerce` folder, or copy it into `wp-content/plugins/`.
2. Activate **Njiwa for WooCommerce** in Plugins.
3. Go to **WooCommerce → Settings → Njiwa**.

WooCommerce 7.0 or newer, WordPress 6.0 or newer, PHP 7.4 or newer. Works with
the new order tables (HPOS) and with the old ones.

## Set it up

Paste your API key from [console.upeo.ai](https://console.upeo.ai) → API keys,
save, then press **Test connection**. It lists the WhatsApp numbers your Njiwa
account actually has, so you find out now rather than at the moment a customer
should have been messaged.

**Start with a test key.** A key beginning `sk_test_` checks and stores every
message and delivers nothing. Turn on the events you want, place a test order,
read the order notes, and only then swap in the `sk_live_` key.

Then tick the events you want and edit the wording. Every field on the page
explains itself; the short version:

| Setting | What it is for |
| --- | --- |
| Send WhatsApp messages | The master switch. Off keeps every setting and sends nothing. |
| API key | `sk_test_` delivers nothing, `sk_live_` sends for real. |
| Njiwa address | Leave it alone unless you were given your own. |
| Send from | Which of your numbers sends. Empty means the account default. |
| Each event | On, off, and the exact wording. Empty wording sends nothing. |
| Your WhatsApp numbers | Where the new-order alert goes. Several, comma separated. |

## What gets sent, and when

| When the order reaches | Who hears about it |
| --- | --- |
| On hold | The customer: we have your order, waiting for payment |
| Processing | The customer: payment received, getting it ready |
| Completed | The customer: it is done and on its way |
| Cancelled | The customer: cancelled, and you were not charged |
| Refunded | The customer: the money is coming back |
| The first of on-hold, processing or completed | You: a new order came in |

Each one is off until you turn it on.

The alert to you is sent **once per order**, on the first status that means the
order is real. Not when the order row is created, which happens the moment
somebody reaches the payment page and usually means nothing.

## The wording

Plain text with placeholders in braces. The settings page lists them all; they
are `{first_name}`, `{last_name}`, `{customer_name}`, `{order_number}`,
`{order_total}`, `{order_date}`, `{order_status}`, `{payment_method}`,
`{items}`, `{item_count}`, `{shop_name}`, `{order_url}` and `{admin_url}`.

A placeholder that does not exist, `{order_no}` say, is removed before sending
rather than posted to a customer, and a line is written to
**WooCommerce → Status → Logs** telling you where to look.

## Things worth knowing

**The checkout never waits.** Messages are handed to Action Scheduler, which
WooCommerce already runs, and go out a moment later. A slow network, or Njiwa
being down, cannot delay or break an order.

**Every send is written on the order.** Look at any order and the notes say
what went where, with Njiwa's message id, or why it did not. That is also where
"no billing phone number" shows up.

**Nothing is sent twice.** Each message carries an idempotency key made from
the order, the event and the recipient. If a queue job runs twice, Njiwa
replays the first answer instead of messaging the customer again.

**Phone numbers are read against the order's country.** `0712345678` on an
order billed to Kenya becomes `254712345678`. A number already written in full
is left alone. Where the country is missing, the number is passed on as typed
and Njiwa resolves it against your own sending number's country.

**Deleting the plugin deletes the key.** Order notes stay, because they are a
record of what was sent and they belong to the order.

## What it does not do

**It does not receive replies.** Inbound WhatsApp arrives as a webhook and
verifying one needs that number's signing secret, which the console does not
yet show. Until it does, a receiving feature could not check that a request
really came from Njiwa, so there is not one.

**It does not run campaigns.** Bulk sending to past customers is what the Njiwa
console is for, on Business plans and above.

---

Docs: https://docs.njiwa.upeo.ai · Console: https://console.upeo.ai
UPEO.AI · hello@upeo.ai · 0116888777 on WhatsApp
