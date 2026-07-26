-- Migration: AI CSKH new features (coupon + dietary tags)
-- Date: 2026-07-26
-- Run on Aiven (database `cake`)

-- === Feature 1: public coupon flag + new-customer coupon ===
-- Existing coupons (is_public=0) stay hidden from AI; only public ones surfaced.
ALTER TABLE `cart_coupons`
  ADD COLUMN `is_public` TINYINT(1) NOT NULL DEFAULT 0;

-- New-customer coupon: 10% off orders >= 100.000đ. Public (AI may disclose).
-- Idempotent: re-running keeps it public + active without resetting used_count.
INSERT INTO `cart_coupons`
  (`code`, `discount_percent`, `min_subtotal`, `usage_limit`, `used_count`, `is_active`, `is_public`)
VALUES
  ('WELCOME10', 10.00, 100000, NULL, 0, 1, 1)
ON DUPLICATE KEY UPDATE
  `discount_percent` = VALUES(`discount_percent`),
  `min_subtotal` = VALUES(`min_subtotal`),
  `is_active` = 1,
  `is_public` = 1;

-- === Feature 6: dietary/allergen tags on products ===
-- Default = contains the allergen (safe for allergy sufferers). co_hat default 0.
ALTER TABLE `banh`
  ADD COLUMN `co_trung`  TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'contains egg',
  ADD COLUMN `co_sua`    TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'contains milk/dairy',
  ADD COLUMN `co_gluten` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'contains gluten/wheat',
  ADD COLUMN `co_hat`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'contains nuts';

-- Per-product allergen tags. Default (contains allergen) is safe; a product is
-- only flagged allergen-free (0) when its description makes that clear.
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=2; -- Bánh Kem Chocolate
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=3; -- Bánh kem Cherry
UPDATE `banh` SET co_trung=0, co_sua=1, co_gluten=1, co_hat=0 WHERE id=13; -- Bánh mì bơ tỏi
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=14; -- Bánh bông lan trứng muối
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=15; -- Bánh mì Hotdog
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=17; -- Bánh Tart trái cây
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=18; -- Bánh Burger BBQ
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=20; -- Bánh Croissant
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=25; -- Tiramisu Matcha
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=26; -- Bánh Cheesecake Cherry
UPDATE `banh` SET co_trung=0, co_sua=0, co_gluten=0, co_hat=0 WHERE id=27; -- Bánh Mochi Dâu tây
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=28; -- Bánh Muffin Cherry
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=29; -- Bánh Muffin Dâu tây
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=30; -- Tiramisu truyền thống
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=31; -- Bánh Tart
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=1 WHERE id=32; -- Bánh Quy mix hạt
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=1 WHERE id=33; -- Bánh Muffin hình nhím
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=34; -- Bánh mì phô mai hải sản
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=35; -- Bánh Muffin Chocolate
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=36; -- Bánh Cheesecake Dâu tây
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=37; -- Bánh kem Oreo Việt quất
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=38; -- Bánh kem Dâu tây
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=39; -- Bánh kem Phô mai Bắp
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=40; -- Bánh kem Valentine
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=1 WHERE id=41; -- Bánh mì hoa Cúc
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=42; -- Bánh mì kem phô mai
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=43; -- Bánh mì Sandwich
UPDATE `banh` SET co_trung=0, co_sua=0, co_gluten=1, co_hat=0 WHERE id=44; -- Bánh mì Việt Nam
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=46; -- Bánh Crepe Sầu riêng
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=47; -- Bánh kem Xoài
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=48; -- Bánh kem Trái cây Mix
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=49; -- Bánh kem hình hoa
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=50; -- Bánh su kem
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=51; -- Cheesecake Việt quất
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=52; -- Cheesecake Chanh dây
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=53; -- Bánh Cheesecake Caramel
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=54; -- Cupcake Red Velvet
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=55; -- Cupcake Chocolate
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=56; -- Cupcake Vani
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=0, co_hat=1 WHERE id=57; -- Macaron hộp 6 vị
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=58; -- Mousse Chocolate
UPDATE `banh` SET co_trung=0, co_sua=1, co_gluten=1, co_hat=0 WHERE id=59; -- Mousse Chanh dây
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=1 WHERE id=60; -- Brownie Chocolate
UPDATE `banh` SET co_trung=1, co_sua=0, co_gluten=1, co_hat=0 WHERE id=61; -- Chiffon cake Vani
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=62; -- Éclair Chocolate
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=63; -- Mille Crepe Matcha
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=64; -- Pain au Chocolat
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=65; -- Danish Trái cây
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=66; -- Donut hộp 4 chiếc
UPDATE `banh` SET co_trung=0, co_sua=1, co_gluten=0, co_hat=0 WHERE id=67; -- Panna Cotta Vani
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=0, co_hat=0 WHERE id=68; -- Bánh Flan Caramel
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=69; -- Cookie Bơ hộp 12
UPDATE `banh` SET co_trung=0, co_sua=1, co_gluten=1, co_hat=0 WHERE id=70; -- Bánh Patê Sô
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=71; -- Quiche Lorraine
UPDATE `banh` SET co_trung=1, co_sua=0, co_gluten=1, co_hat=0 WHERE id=72; -- Bánh Bao nhân thịt
UPDATE `banh` SET co_trung=1, co_sua=1, co_gluten=1, co_hat=0 WHERE id=73; -- Puff Pastry Xúc xích
