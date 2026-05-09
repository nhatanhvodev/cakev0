# PDF Invoice Email Design

Date: 2026-05-09
Project: `cakev0`
Status: Approved for planning

## Goal

Email a PDF invoice to the customer after an order reaches the business-valid point for invoicing:

- `VNPAY`: send after payment is confirmed successful
- `COD`: send after admin confirms the order

The email body should stay short and the PDF should be the primary artifact. The PDF must be generated server-side from a dedicated invoice template, not from the current browser-side `order-detail.php` export flow.

## Confirmed requirements

- Invoice format: PDF attachment
- PDF generation: server-side dedicated template
- Email body: short, no detailed order summary required
- `VNPAY` trigger: payment success
- `COD` trigger: admin confirmation
- Avoid duplicate invoice emails
- Email sending failure must not roll back payment success or order confirmation

## Current state

Relevant codebase behavior:

- `pages/order-detail.php`
  - already has a browser-side invoice layout and PDF export via `jsPDF` / `html2canvas`
  - this is suitable for customer download in the browser, but not for backend email delivery
- `pages/checkout.php`
  - creates `orders`
  - creates `order_items`
  - clears cart
  - redirects to `VNPAY` or back to home depending on payment method
- `admin/admin.php`
  - updates order status from the admin side
- `includes/mailer.php`
  - supports HTML email via SMTP or Gmail API
  - does not yet support file attachments

## Chosen approach

Use a dedicated server-side invoice template rendered to PDF and attach that PDF to outgoing email.

Recommended implementation technology:
- `dompdf/dompdf`

Reasoning:
- simpler than manually drawing PDFs with `TCPDF`/`FPDF`
- better separation than trying to reuse browser invoice rendering
- easier to keep invoice layout maintainable in PHP
- fits the existing project better than building a low-level PDF writer from scratch

## Scope

In scope:
- server-side PDF invoice generation
- attachment support for SMTP and Gmail API mail sending
- invoice email trigger for `VNPAY` success
- invoice email trigger for `COD` admin confirmation
- duplicate-send prevention
- schema changes for invoice-email tracking

Out of scope:
- resend invoice button in admin
- customer-visible invoice-email history UI
- rich HTML email summary
- retry queue / background jobs
- reusing browser PDF rendering

## High-level architecture

Split responsibilities into focused units:

- `includes/invoice_pdf.php`
  - render order data into invoice HTML
  - convert HTML into PDF binary
- `includes/invoice_mailer.php`
  - load order + customer data
  - generate attachment filename
  - call mailer with attachment
  - mark invoice email sent
- `includes/mailer.php`
  - remain the low-level mail transport abstraction
  - gain attachment support for both SMTP and Gmail API

Trigger points:

- `vnpay/vnpay_return.php` or the real payment-success handler used by the project
  - send invoice after successful payment confirmation
- `admin/admin.php`
  - send invoice when a COD order is confirmed

## Data design

### Orders table additions

Add to `orders`:

- `invoice_email_sent_at DATETIME NULL`

Optional future field, but not required now:
- `invoice_email_error TEXT NULL`

Current recommendation:
- only add `invoice_email_sent_at`

Reason:
- enough to prevent duplicate sends
- keeps the first version lean

## Invoice content design

The server-side invoice PDF should include:

- bakery name / brand heading
- invoice number based on order id
- order creation date
- recipient name
- phone number
- address
- payment method
- order status if useful
- product table:
  - product name
  - quantity
  - unit price
  - line total
- coupon code if present
- discount amount if present
- grand total
- short footer note

The template should be independent from `pages/order-detail.php`.

## Email content design

Email should be intentionally short.

Example subject:
- `Hóa đơn đơn hàng #123 từ Gấu Bakery`

Example body intent:
- the order has been paid/confirmed successfully
- the invoice PDF is attached

No need for detailed item summary in the email body.

## Attachment support design

### SMTP path

For PHPMailer SMTP:
- attach the generated PDF binary as a file attachment
- use a deterministic filename like:
  - `hoa-don-<order_id>.pdf`

### Gmail API path

For Gmail API:
- current implementation builds a simple HTML-only raw message
- it must be upgraded to build a proper multipart MIME message with:
  - text/html body part
  - PDF attachment part

This is the most important low-level change in the mailer.

## Order data source

Invoice email generation should load authoritative data directly from the database:

- `orders`
- `order_items`
- `banh`
- `users` for customer email where needed

Do not depend on session data or browser state.

## Trigger flow: VNPAY

When payment is confirmed successful:

1. verify the payment callback/return data
2. update order payment state as the project already does
3. check `invoice_email_sent_at`
4. if `invoice_email_sent_at IS NULL`
   - load order data
   - generate invoice PDF
   - send email with attachment
   - on success, set `invoice_email_sent_at = NOW()`
5. if sending fails
   - log the failure
   - do not revert payment success

## Trigger flow: COD

When admin confirms a COD order:

1. update order status to confirmed/approved using current admin flow
2. check `invoice_email_sent_at`
3. if `invoice_email_sent_at IS NULL`
   - load order data
   - generate invoice PDF
   - send email with attachment
   - on success, set `invoice_email_sent_at = NOW()`
4. if sending fails
   - log the failure
   - do not revert order confirmation

## Duplicate-send prevention

Duplicate send prevention must rely on database state, not only branching logic.

Rule:
- only send when `invoice_email_sent_at IS NULL`

This protects against:
- repeated `VNPAY` callback hits
- admin repeating status changes
- accidental re-entry into the same code path

## Error handling

### Missing or invalid recipient email

If the order cannot resolve a valid customer email:
- skip sending
- log the failure
- do not fail order/payment flow

### PDF generation failure

If the PDF cannot be generated:
- log the failure
- do not mark `invoice_email_sent_at`
- do not roll back payment or admin confirmation

### Email send failure

If the mailer fails:
- log the failure
- do not mark `invoice_email_sent_at`
- do not roll back payment or admin confirmation

## File-level change plan

Expected files to add or modify:

- add `includes/invoice_pdf.php`
- add `includes/invoice_mailer.php`
- update `includes/mailer.php`
- update `admin/admin.php`
- update `vnpay/vnpay_return.php` or equivalent success handler
- add migration in `database/migrations/`
- update `database/banh_store.sql`
- update `README.md`
- update `composer.json` and `composer.lock` if `dompdf/dompdf` is added

## SQL change outline

Migration should add:

```sql
ALTER TABLE `orders`
  ADD COLUMN `invoice_email_sent_at` DATETIME NULL AFTER `status`;
```

If the actual column ordering is cleaner elsewhere in the project schema, keep it consistent with local conventions.

## Testing strategy

### Unit / focused tests

Where practical, add focused tests for:
- invoice filename generation
- invoice HTML rendering
- duplicate-send guard behavior

If the repo still lacks a full PHP test harness for integration-level flows, keep tests focused and rely on lint/manual verification for legacy pages.

### Mail attachment verification

Verify:
- SMTP sends attachment correctly
- Gmail API sends attachment correctly
- attachment filename is correct
- PDF opens successfully after receipt

### Trigger verification

Verify:
- `VNPAY` success sends invoice once
- repeated `VNPAY` success path does not resend after `invoice_email_sent_at` is set
- `COD` admin confirmation sends invoice once
- repeated admin status changes do not resend

### Failure verification

Verify:
- mail failure does not undo order confirmation/payment success
- `invoice_email_sent_at` remains `NULL` on failure

### Manual verification

Manual pass should confirm:
- invoice PDF content matches real order totals
- Vietnamese text renders correctly in PDF
- email body remains short

## Risks and implementation notes

- Gmail API multipart attachment support is the riskiest implementation area.
- `admin/admin.php` is large; the trigger insertion must be narrowly scoped.
- `vnpay` callback logic must not double-send invoices.
- PDF font support for Vietnamese must be confirmed in the selected PDF renderer.

## Decisions recorded

- Use server-side dedicated PDF template
- Do not reuse browser `order-detail.php` invoice export
- Send invoice only after payment/business confirmation point
- `VNPAY`: send on payment success
- `COD`: send on admin confirmation
- Email body stays short
- Prevent duplicates with `orders.invoice_email_sent_at`
