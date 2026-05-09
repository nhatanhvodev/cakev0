ALTER TABLE `orders`
  ADD COLUMN `invoice_email_sent_at` DATETIME NULL AFTER `status`;
