-- Migration: Add 20 new bakery products
-- Date: 2026-07-26
-- Categories: ngot (16), man (4)
-- All use placeholder images (admin can update via CMS)

INSERT INTO `banh` (`id`, `ten_banh`, `slug`, `gia`, `hinh_anh`, `loai`, `mo_ta`, `is_featured`, `stock`, `is_best_manual`, `best_rank`) VALUES

-- === BÁNH NGỌT (16 sản phẩm) ===

(54, 'Cupcake Red Velvet', 'cupcake-red-velvet-54', 45000.00,
 'assets/uploads/banhngot/placeholder_cupcake_redvelvet.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Cupcake Red Velvet với lớp kem cheese frosting béo ngậy, cốt bánh đỏ mềm mịn. Thích hợp làm quà tặng, tiệc sinh nhật hoặc thưởng thức cùng trà chiều.</p><p><strong>Thành phần:</strong> Bột mì, bơ, trứng, cacao, cream cheese, đường, phẩm màu thực phẩm.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 3 ngày.</p>',
 0, 100, 0, 0),

(55, 'Cupcake Chocolate', 'cupcake-chocolate-55', 40000.00,
 'assets/uploads/banhngot/placeholder_cupcake_chocolate.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Cupcake chocolate đậm đà với lớp ganache chocolate Bỉ bóng mượt phủ trên cốt bánh chocolate xốp mềm. Phù hợp mọi lứa tuổi.</p><p><strong>Thành phần:</strong> Bột mì, bơ, trứng, chocolate đen 70%, cacao, đường.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 3 ngày.</p>',
 0, 100, 0, 0),

(56, 'Cupcake Vani', 'cupcake-vani-56', 35000.00,
 'assets/uploads/banhngot/placeholder_cupcake_vani.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Cupcake vani cổ điển với lớp buttercream vani mịn màng, cốt bánh bơ thơm lừng. Hương vị nhẹ nhàng, thanh ngọt, phù hợp làm quà hoặc trang trí tiệc.</p><p><strong>Thành phần:</strong> Bột mì, bơ, trứng, tinh chất vani, đường, kem tươi.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 3 ngày.</p>',
 0, 100, 0, 0),

(57, 'Macaron hộp 6 vị', 'macaron-hop-6-vi-57', 120000.00,
 'assets/uploads/banhngot/placeholder_macaron.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Hộp 6 viên macaron Pháp với các vị: chocolate, matcha, dâu tây, chanh, vani và caramel muối. Vỏ giòn mỏng, nhân ganache mềm mịn tan trên đầu lưỡi. Đóng hộp sang trọng, lý tưởng làm quà tặng.</p><p><strong>Thành phần:</strong> Bột hạnh nhân, đường bột, lòng trắng trứng, chocolate, matcha, bơ, kem tươi.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 5 ngày.</p>',
 1, 80, 0, 0),

(58, 'Mousse Chocolate', 'mousse-chocolate-58', 90000.00,
 'assets/uploads/banhngot/placeholder_mousse_choco.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Mousse chocolate Bỉ cao cấp với kết cấu mềm mịn như mây, vị đắng nhẹ hòa quyện cùng vị ngọt thanh. Lớp đáy biscuit giòn tạo điểm nhấn kết cấu. Ít ngọt theo xu hướng healthy.</p><p><strong>Thành phần:</strong> Chocolate đen 70%, kem tươi, trứng, gelatin, bơ, biscuit.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 3 ngày.</p>',
 0, 100, 0, 0),

(59, 'Mousse Chanh dây', 'mousse-chanh-day-59', 85000.00,
 'assets/uploads/banhngot/placeholder_mousse_chanhday.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Mousse chanh dây tươi mát với vị chua ngọt cân bằng hoàn hảo. Lớp mousse mềm mịn kết hợp cùng lớp jelly chanh dây bên trên và đáy biscuit giòn. Giải nhiệt tuyệt vời cho mùa hè.</p><p><strong>Thành phần:</strong> Chanh dây tươi, kem tươi, đường, gelatin, bơ, biscuit.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 3 ngày.</p>',
 0, 100, 0, 0),

(60, 'Brownie Chocolate', 'brownie-chocolate-60', 45000.00,
 'assets/uploads/banhngot/placeholder_brownie.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Brownie chocolate đậm đà kiểu Mỹ, bên ngoài hơi giòn, bên trong ẩm mềm fudgy. Sử dụng chocolate đen cao cấp và bơ Pháp. Phần trên rắc thêm hạt óc chó rang thơm bùi.</p><p><strong>Thành phần:</strong> Chocolate đen, bơ, trứng, đường, bột mì, hạt óc chó.</p><p><strong>Bảo quản:</strong> Nhiệt độ phòng 1-2 ngày, tủ lạnh 5 ngày.</p>',
 0, 100, 0, 0),

(61, 'Chiffon cake Vani', 'chiffon-cake-vani-61', 130000.00,
 'assets/uploads/banhngot/placeholder_chiffon.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Chiffon cake kiểu Nhật Bản, nhẹ xốp như bông mây với hương vani tự nhiên. Ít đường, ít dầu, phù hợp người thích vị thanh nhẹ. Size 18cm thích hợp cho 4-6 người. Có thể kèm kem tươi và trái cây theo yêu cầu.</p><p><strong>Thành phần:</strong> Bột mì, trứng, dầu thực vật, đường, tinh chất vani.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 3 ngày.</p>',
 0, 80, 0, 0),

(62, 'Éclair Chocolate', 'eclair-chocolate-62', 35000.00,
 'assets/uploads/banhngot/placeholder_eclair.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Éclair Pháp cổ điển với vỏ choux giòn xốp, nhân kem custard vani béo ngậy, phủ lớp ganache chocolate bóng mượt bên trên. Thưởng thức ngon nhất khi mới làm.</p><p><strong>Thành phần:</strong> Bột mì, bơ, trứng, sữa, chocolate, kem tươi, đường, vani.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 2 ngày.</p>',
 0, 100, 0, 0),

(63, 'Mille Crepe Matcha', 'mille-crepe-matcha-63', 160000.00,
 'assets/uploads/banhngot/placeholder_millecrepe.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Mille Crepe với hơn 20 lớp crepe mỏng mịn xen kẽ lớp kem tươi matcha Nhật Bản. Vị trà xanh đắng nhẹ hòa quyện cùng kem ngọt thanh. Size 16cm cho 4-6 người. Top trending 2025-2026.</p><p><strong>Thành phần:</strong> Bột mì, trứng, sữa, bơ, kem tươi, bột matcha Uji.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 3 ngày.</p>',
 1, 60, 0, 0),

(64, 'Pain au Chocolat', 'pain-au-chocolat-64', 30000.00,
 'assets/uploads/banhngot/placeholder_painauchocolat.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Pain au Chocolat Pháp với lớp bột ngàn lớp giòn xốp bọc hai thanh chocolate đen bên trong. Nướng vàng thơm mỗi sáng, thưởng thức khi còn ấm là ngon nhất. Kết hợp hoàn hảo cùng cà phê.</p><p><strong>Thành phần:</strong> Bột mì, bơ Pháp, chocolate đen, trứng, sữa, men, đường.</p><p><strong>Bảo quản:</strong> Nhiệt độ phòng trong ngày, hâm nóng lại 180°C/3 phút.</p>',
 0, 100, 0, 0),

(65, 'Danish Trái cây', 'danish-trai-cay-65', 35000.00,
 'assets/uploads/banhngot/placeholder_danish.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Bánh Danish giòn xốp với lớp kem custard vani và trái cây tươi theo mùa (dâu tây, việt quất, kiwi). Lớp vỏ nhiều lớp giòn tan, phủ mứt bóng lên trên. Lý tưởng cho bữa sáng hoặc trà chiều.</p><p><strong>Thành phần:</strong> Bột mì, bơ, trứng, sữa, kem custard, trái cây tươi.</p><p><strong>Bảo quản:</strong> Dùng trong ngày để giữ độ giòn.</p>',
 0, 100, 0, 0),

(66, 'Donut hộp 4 chiếc', 'donut-hop-4-chiec-66', 60000.00,
 'assets/uploads/banhngot/placeholder_donut.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Hộp 4 donut mềm xốp với các vị: đường phủ, chocolate, dâu tây và matcha. Vỏ ngoài phủ lớp topping rực rỡ, bên trong mềm nhẹ. Phù hợp chia sẻ cùng gia đình hoặc bạn bè.</p><p><strong>Thành phần:</strong> Bột mì, bơ, trứng, sữa, men, đường, chocolate, topping các vị.</p><p><strong>Bảo quản:</strong> Nhiệt độ phòng trong ngày, tủ lạnh 2 ngày.</p>',
 0, 100, 0, 0),

(67, 'Panna Cotta Vani', 'panna-cotta-vani-67', 45000.00,
 'assets/uploads/banhngot/placeholder_pannacotta.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Panna Cotta Ý truyền thống với kết cấu mềm mượt như lụa, hương vani Madagascar. Phủ lớp coulis dâu tây tươi hoặc caramel tùy chọn. Tráng miệng thanh mát, nhẹ nhàng.</p><p><strong>Thành phần:</strong> Kem tươi, sữa, đường, gelatin, vani, dâu tây.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 3 ngày.</p>',
 0, 100, 0, 0),

(68, 'Bánh Flan Caramel', 'banh-flan-caramel-68', 20000.00,
 'assets/uploads/banhngot/placeholder_flan.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Bánh flan caramel truyền thống Việt Nam, mềm mịn thơm béo mùi trứng và sữa. Lớp caramel đắng nhẹ bên dưới cân bằng vị ngọt. Ăn lạnh hoặc thêm đá đều ngon. Món tráng miệng quen thuộc mọi nhà.</p><p><strong>Thành phần:</strong> Trứng, sữa tươi, sữa đặc, đường, vani.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 3 ngày.</p>',
 0, 200, 0, 0),

(69, 'Cookie Bơ hộp 12', 'cookie-bo-hop-12-69', 80000.00,
 'assets/uploads/banhngot/placeholder_cookie.jpg', 'ngot',
 '<p><strong>Mô tả sản phẩm:</strong> Hộp 12 cookie bơ thơm giòn với hai vị: bơ truyền thống và chocolate chip. Sử dụng bơ Pháp và chocolate chip Bỉ. Đóng hộp đẹp, thích hợp làm quà biếu, quà tặng hoặc ăn vặt hàng ngày.</p><p><strong>Thành phần:</strong> Bột mì, bơ Pháp, đường nâu, trứng, chocolate chip, vani, muối.</p><p><strong>Bảo quản:</strong> Nhiệt độ phòng mát 7-10 ngày, hộp kín.</p>',
 0, 100, 0, 0),

-- === BÁNH MẶN (4 sản phẩm) ===

(70, 'Bánh Patê Sô', 'banh-pate-so-70', 25000.00,
 'assets/uploads/banhman/placeholder_pateso.jpg', 'man',
 '<p><strong>Mô tả sản phẩm:</strong> Bánh patê sô truyền thống Việt Nam với lớp vỏ puff pastry giòn ngàn lớp, nhân patê gan heo thơm béo kết hợp thịt heo xay. Nướng vàng giòn mỗi ngày. Ăn sáng hoặc ăn vặt đều phù hợp.</p><p><strong>Thành phần:</strong> Bột mì, bơ, patê gan, thịt heo, hành, tiêu, nước mắm.</p><p><strong>Bảo quản:</strong> Nhiệt độ phòng trong ngày, hâm nóng 180°C/5 phút.</p>',
 0, 150, 0, 0),

(71, 'Quiche Lorraine', 'quiche-lorraine-71', 55000.00,
 'assets/uploads/banhman/placeholder_quiche.jpg', 'man',
 '<p><strong>Mô tả sản phẩm:</strong> Quiche Lorraine Pháp với vỏ tart bơ giòn, nhân trứng kem béo ngậy kết hợp thịt xông khói, phô mai Gruyère và hành tây caramel. Size 12cm cho 1-2 người. Phù hợp bữa brunch hoặc ăn nhẹ.</p><p><strong>Thành phần:</strong> Bột mì, bơ, trứng, kem tươi, thịt xông khói, phô mai Gruyère, hành tây.</p><p><strong>Bảo quản:</strong> Ngăn mát tủ lạnh 2-8°C, dùng trong 2 ngày. Hâm nóng 180°C/5 phút.</p>',
 0, 80, 0, 0),

(72, 'Bánh Bao nhân thịt', 'banh-bao-nhan-thit-72', 20000.00,
 'assets/uploads/banhman/placeholder_banhbao.jpg', 'man',
 '<p><strong>Mô tả sản phẩm:</strong> Bánh bao truyền thống với vỏ bột mềm trắng mịn, nhân thịt heo xay kết hợp trứng cút, nấm mèo và miến. Hấp nóng tại tiệm mỗi ngày. Bữa sáng tiện lợi, no lâu.</p><p><strong>Thành phần:</strong> Bột mì, thịt heo, trứng cút, nấm mèo, miến, hành, tiêu.</p><p><strong>Bảo quản:</strong> Ngăn mát 2 ngày, ngăn đông 2 tuần. Hấp lại 10 phút trước khi ăn.</p>',
 0, 200, 0, 0),

(73, 'Puff Pastry Xúc xích', 'puff-pastry-xuc-xich-73', 30000.00,
 'assets/uploads/banhman/placeholder_puffxucxich.jpg', 'man',
 '<p><strong>Mô tả sản phẩm:</strong> Puff pastry cuộn xúc xích Đức nguyên cây, vỏ bột ngàn lớp giòn rụm bọc xúc xích thịt heo thơm ngon. Nướng vàng giòn, phủ mè trắng bên ngoài. Ăn nóng là ngon nhất, phù hợp ăn sáng hoặc ăn vặt.</p><p><strong>Thành phần:</strong> Bột mì, bơ, xúc xích Đức, trứng, mè trắng.</p><p><strong>Bảo quản:</strong> Nhiệt độ phòng trong ngày, hâm nóng 180°C/5 phút.</p>',
 0, 150, 0, 0);
