-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2025 at 12:16 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `banh_store`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$YvZ5e5e5e5e5e5e5e5e5e5u5e5e5e5e5e5e5e5e5e5e5e5e5e5e5e', '2025-05-04 02:49:31');

-- --------------------------------------------------------

--
-- Table structure for table `banh`
--

CREATE TABLE `banh` (
  `id` int(11) NOT NULL,
  `ten_banh` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `gia` decimal(10,2) NOT NULL,
  `hinh_anh` varchar(255) NOT NULL,
  `loai` varchar(50) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_best_manual` tinyint(1) DEFAULT 0,
  `best_rank` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banh`
--

INSERT INTO `banh` (`id`, `ten_banh`, `gia`, `hinh_anh`, `loai`, `mo_ta`, `is_featured`) VALUES
(1, 'bánh trân châu việt quất', 75000.00, 'img/banhkem/i6.jpg', 'kem', 'Bánh kem trân châu việt quất thơm ngon, kết hợp lớp kem béo ngậy và topping việt quất tươi mát, lý tưởng cho các bữa tiệc sinh nhật hoặc quà tặng đặc biệt.', 0),
(2, 'bánh kem chocolate', 80000.00, 'img/banhkem/k1.jpg', 'kem', 'Bánh kem chocolate đậm đà với lớp kem socola mềm mịn, phủ vụn bánh giòn tan, phù hợp để thưởng thức cùng gia đình hoặc bạn bè.', 0),
(3, 'bánh kem cherry', 300000.00, 'img/banhkem/k3.jpg', 'kem', 'Bánh kem cherry đỏ rực rỡ, kết hợp vị chua nhẹ của cherry tươi và kem béo, hoàn hảo cho những dịp lễ hội sang trọng.', 0),
(4, 'bánh kem Oreo dâu tây', 300000.00, 'img/banhkem/i8.jpg', 'kem', 'Bánh kem Oreo dâu tây độc đáo với lớp Oreo giòn rụm và dâu tây ngọt ngào, mang đến trải nghiệm mới lạ cho người yêu bánh kem.', 0),
(5, 'bánh kem hình thú', 200000.00, 'img/banhkem/k6.jpg', 'kem', 'Bánh kem hình thú dễ thương, được trang trí tinh tế với nhiều hình dáng ngộ nghĩnh, thích hợp làm quà tặng cho trẻ em.', 0),
(6, 'bánh kem chay', 200000.00, 'img/banhkem/i11.jpg', 'kem', 'Bánh kem chay thanh đạm, làm từ nguyên liệu thuần chay, giữ vị béo nhẹ của dừa và cacao, phù hợp cho người ăn kiêng.', 0),
(7, 'bánh kem bơ chảy', 200000.00, 'img/banhkem/k8.jpg', 'kem', 'Bánh kem bơ chảy béo ngậy, kết hợp vị bơ tan chảy và lớp kem mịn, lý tưởng để thưởng thức trong những buổi trà chiều.', 0),
(8, 'bánh kem nơ hoa/trái tim', 300000.00, 'img/banhkem/k9.jpg', 'kem', 'Bánh kem nơ hoa/trái tim lãng mạn, trang trí tinh xảo với hoa tươi và nơ, hoàn hảo cho ngày kỷ niệm hoặc Valentine.', 0),
(9, 'bánh kem cắt sẵn các loại', 95000.00, 'img/banhkem/i17.jpg', 'kem', 'Bánh kem cắt sẵn đa dạng hương vị, tiện lợi để chia sẻ, với lớp kem mềm mịn và topping phong phú, phù hợp cho mọi dịp.', 0),
(10, 'bánh kem biển xanh', 300000.00, 'img/banhkem/i30.jpg', 'kem', 'Bánh kem biển xanh mát mắt, kết hợp màu sắc tự nhiên và vị kem tươi dịu, lý tưởng cho những bữa tiệc ngoài trời.', 0),
(11, 'bánh kem phô mai matcha', 300000.00, 'img/banhkem/k11.jpg', 'kem', 'Bánh kem phô mai matcha thơm lừng, hòa quyện vị đắng nhẹ của matcha và phô mai béo, thích hợp cho người yêu thích hương vị Nhật Bản.', 0),
(12, 'bánh kem bento các loại', 100000.00, 'img/banhkem/k14.jpg', 'kem', 'Bánh kem bento các loại xinh xắn, trang trí như hộp cơm Nhật, mang lại sự thú vị và ngon miệng cho bữa ăn nhẹ.', 1),
(13, 'bánh mì bơ tỏi', 30000.00, 'img/banhman/bmm.jpg', 'man', 'Bánh mì bơ tỏi thơm lừng, lớp bơ vàng óng và tỏi giòn rụm, lý tưởng để ăn sáng hoặc nhâm nhi cùng trà chiều.', 0),
(14, 'bánh bông lan trứng muối', 150000.00, 'img/banhman/i9.jpg', 'man', 'Bánh bông lan trứng muối béo ngậy, kết hợp trứng muối mặn mà và lớp kem ngọt nhẹ, phù hợp làm món tráng miệng sang trọng.', 1),
(15, 'bánh muffins pizza', 30000.00, 'img/banhman/bm3.jpg', 'man', 'Bánh muffins pizza nhỏ xinh, nhân phô mai tan chảy và topping phong phú, tiện lợi cho bữa ăn nhanh hoặc tiệc nhẹ.', 0),
(16, 'bánh bông lan hải sản', 150000.00, 'img/banhman/bm5.jpg', 'man', 'Bánh bông lan hải sản tươi ngon, kết hợp hải sản cao cấp và lớp kem béo, mang đến hương vị độc đáo cho bữa tiệc.', 0),
(17, 'bánh tart trứng mỡ hành', 25000.00, 'img/banhman/bm6.jpg', 'man', 'Bánh tart trứng mỡ hành thơm lừng, lớp vỏ giòn tan và nhân béo ngậy, lý tưởng làm món ăn vặt hoặc quà tặng.', 0),
(18, 'bánh burger cá hồi', 50000.00, 'img/banhman/bm7.jpg', 'man', 'Bánh burger cá hồi mềm mịn, nhân cá hồi tươi ngon và rau củ giòn, phù hợp cho bữa trưa nhẹ nhàng.', 0),
(19, 'bánh mì hoa cúc', 25000.00, 'img/banhmi/m1.jpg', 'mi', 'Bánh mì hoa cúc xốp mềm, trang trí đẹp mắt với hình hoa cúc, thích hợp làm món điểm tâm hoặc quà tặng.', 0),
(20, 'bánh mì sừng trâu', 15000.00, 'img/banhmi/m7.jpg', 'mi', 'Bánh mì sừng trâu giòn rụm, vị bơ béo ngậy, hoàn hảo để thưởng thức cùng cà phê buổi sáng.', 0),
(21, 'bánh mì dài', 15000.00, 'img/banhman/m8.jpg', 'mi', 'Bánh mì dài truyền thống, vỏ giòn ruột mềm, thích hợp cho mọi bữa ăn hoặc sandwich tùy chỉnh.', 0),
(22, 'bánh mì mini', 8000.00, 'img/banhmi/m10.jpg', 'mi', 'Bánh mì mini nhỏ gọn, xốp nhẹ và thơm ngon, lý tưởng cho bữa ăn nhẹ hoặc tiệc buffet.', 0),
(23, 'bánh mì đen', 30000.00, 'img/banhmi/m11.jpg', 'mi', 'Bánh mì đen giàu dinh dưỡng, kết hợp ngũ cốc và hương vị đặc trưng, phù hợp cho người ăn kiêng.', 0),
(24, 'bánh mì mềm', 10000.00, 'img/banhmi/m13.jpg', 'mi', 'Bánh mì mềm thơm ngậy, dễ nhai và bổ dưỡng, lý tưởng để ăn sáng hoặc làm bánh mì kẹp.', 0),
(25, 'bánh muffins matcha', 15000.00, 'img/banhngot/i2.jpg', 'ngot', 'Bánh muffins matcha xanh mát, vị trà xanh thanh khiết và lớp topping ngọt ngào, thích hợp làm món tráng miệng.', 0),
(26, 'bánh cheessecake việt quất', 120000.00, 'img/banhngot/i4.jpg', 'ngot', 'Bánh cheesecake việt quất mềm mịn, hòa quyện vị béo của phô mai và chua ngọt tự nhiên từ việt quất, hoàn hảo cho những buổi trà chiều thư giãn.', 0),
(27, 'bánh mochi dâu tây/hộp', 180000.00, 'img/banhngot/i6.jpg', 'ngot', 'Bánh mochi dâu tây thơm ngon, nhân dâu tây tươi ngọt ngào bên trong lớp vỏ mochi dai mềm, hộp đựng sang trọng thích hợp làm quà tặng.', 0),
(28, 'bánh cupcake sakura', 80000.00, 'img/banhngot/i24.jpg', 'ngot', 'Bánh cupcake sakura nhẹ nhàng, mang hương vị hoa anh đào tinh tế kết hợp kem tươi mịn, lý tưởng cho những dịp lễ hội hoặc tiệc trà.', 0),
(29, 'bánh cupcake trái cây/hộp', 120000.00, 'img/banhngot/i26.jpg', 'ngot', 'Bánh cupcake trái cây hộp xinh xắn, phủ kem tươi và topping trái cây đa dạng, mang đến hương vị tươi mới, phù hợp cho tiệc sinh nhật nhỏ.', 0),
(30, 'bánh nhân kem trái cây', 50000.00, 'img/banhngot/n1.jpg', 'ngot', 'Bánh nhân kem trái cây ngọt ngào, với lớp kem mịn và trái cây tươi, là món tráng miệng lý tưởng cho những ngày hè nóng bức.', 0),
(31, 'bánh tart trái cây', 80000.00, 'img/banhngot/i32.jpg', 'ngot', 'Bánh tart trái cây giòn tan, phủ đầy trái cây tươi như dâu, kiwi, và việt quất, mang đến sự cân bằng giữa vị chua ngọt, hoàn hảo cho mọi dịp.', 1),
(32, 'bánh quy mix hạt/hộp', 100000.00, 'img/banhngot/img3.jpg', 'ngot', 'Bánh quy mix hạt thơm giòn, kết hợp nhiều loại hạt dinh dưỡng như hạnh nhân, óc chó, và hạt điều, hộp đựng tiện lợi để làm quà biếu.', 1),
(33, 'bánh bông cừu/hộp', 85000.00, 'img/banhngot/i29.jpg', 'ngot', 'Bánh bông cừu mềm xốp, ngọt dịu với lớp kem béo nhẹ, đóng hộp đẹp mắt, phù hợp làm món ăn nhẹ hoặc quà tặng dễ thương.', 0);

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `views_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blogs`
--

INSERT INTO `blogs` (`id`, `title`, `content`, `author_id`, `status`, `created_at`, `image`, `updated_at`, `views_count`) VALUES
(1, 'Cách làm bánh kem đơn giản tại nhà', 'Hướng dẫn chi tiết cách làm bánh kem với nguyên liệu dễ tìm...', 1, 'approved', '2025-05-04 02:49:31', NULL, NULL, 0),
(2, 'Top 5 loại bánh mì ngon nhất', 'Danh sách những loại bánh mì bạn không thể bỏ qua...', 2, 'approved', '2025-05-04 02:49:31', NULL, NULL, 0),
(3, 'Bí quyết làm bánh ngọt ít đường', 'Chia sẻ mẹo làm bánh ngọt lành mạnh cho gia đình...', 3, 'approved', '2025-05-04 02:49:31', NULL, NULL, 0),
(4, 'Niềm đam mê với việc làm bánh mì', 'Làm bánh mì là một sở thích mang lại niềm vui và sự thư giãn cho tôi sau những giờ học và làm việc căng thẳng. Từng công đoạn từ nhào bột, ủ bột cho đến nướng bánh đều đòi hỏi sự tỉ mỉ và kiên nhẫn, nhưng cũng chính điều đó khiến tôi cảm thấy yêu thích công việc này. Mỗi lần thấy ổ bánh mì vàng ruộm, thơm lừng được lấy ra từ lò nướng, tôi cảm thấy rất tự hào và hạnh phúc. Làm bánh mì không chỉ giúp tôi thỏa mãn đam mê nấu nướng mà còn mang đến cơ hội để chia sẻ yêu thương – khi tặng bạn bè hoặc người thân những ổ bánh do chính tay mình làm. Ngoài ra, việc thử nghiệm các loại nhân bánh hay cách tạo hình mới cũng khiến tôi không ngừng học hỏi và sáng tạo. Với tôi, làm bánh mì không chỉ là một thú vui mà còn là một cách để thể hiện bản thân và kết nối với mọi người xung quanh.\r\n\r\nBạn đã từng thử tự tay làm bánh mì bao giờ chưa?', 6, 'approved', '2025-05-05 08:46:04', 'uploads/68187acc8d94a_1746434764.jpg', '2025-05-05 08:46:04', 0),
(5, 'công thức bánh bông lan nấu bằng nồi cơm', '🌼 Nguyên liệu:\r\nTrứng gà: 4 quả (nhiệt độ phòng)\r\n\r\nBột mì số 8 (hoặc bột mì đa dụng): 80g\r\n\r\nĐường: 80g\r\n\r\nSữa tươi không đường: 40ml\r\n\r\nDầu ăn: 30ml\r\n\r\nVani: 1 ống hoặc 1 thìa cà phê (tùy chọn)\r\n\r\nMuối: 1 nhúm nhỏ\r\n\r\nBơ (hoặc dầu) để chống dính nồi\r\n\r\n🥣 Cách làm:\r\n1. Tách trứng:\r\nTách riêng lòng đỏ và lòng trắng trứng.\r\n\r\n2. Đánh lòng trắng trứng:\r\nCho một nhúm muối vào lòng trắng, dùng máy đánh trứng đánh ở tốc độ thấp đến cao.\r\n\r\nKhi trứng bắt đầu nổi bọt, cho từ từ đường vào (chia làm 2–3 lần).\r\n\r\nĐánh đến khi tạo chóp mềm, dẻo là đạt.\r\n\r\n3. Đánh hỗn hợp lòng đỏ:\r\nĐánh đều lòng đỏ trứng với vani, dầu ăn và sữa tươi.\r\n\r\nRây bột mì vào hỗn hợp, trộn nhẹ tay cho đến khi mịn.\r\n\r\n4. Trộn hai hỗn hợp:\r\nCho từng phần lòng trắng trứng vào hỗn hợp lòng đỏ, trộn theo kiểu fold (gấp và đảo nhẹ từ dưới lên) để tránh vỡ bọt khí.\r\n\r\n5. Nướng bằng nồi cơm điện:\r\nQuét một lớp dầu hoặc bơ vào lòng nồi cơm để chống dính.\r\n\r\nĐổ hỗn hợp bột vào nồi, gõ nhẹ cho bọt khí vỡ.\r\n\r\nNhấn nút \"Cook\". Khi nồi nhảy sang nút \"Warm\", chờ 10 phút rồi nhấn \"Cook\" lại.\r\n(Làm như vậy khoảng 2–3 lần, tùy nồi).\r\n\r\nDùng tăm xăm vào bánh, thấy tăm khô là bánh chín.\r\n\r\n🍰 Lưu ý:\r\nKhông mở nắp nồi khi bánh chưa chín để tránh bị xẹp.\r\n\r\nCó thể trang trí bánh với kem tươi hoặc trái cây sau khi nguội.', 6, 'pending', '2025-05-05 10:14:23', NULL, '2025-05-05 10:14:23', 0);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `banh_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `banh_id`, `quantity`) VALUES
(1, 6, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `blog_id`, `user_id`, `content`, `created_at`) VALUES
(1, 1, 6, 'wow', '2025-05-05 15:47:07');

-- --------------------------------------------------------

--
-- Table structure for table `likes`
--

CREATE TABLE `likes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `likes`
--

INSERT INTO `likes` (`id`, `user_id`, `blog_id`, `created_at`) VALUES
(1, 6, 3, '2025-05-05 14:36:21');

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `status` enum('success','failed') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `login_time`, `ip_address`, `status`) VALUES
(1, 4, '2025-05-04 08:13:14', '::1', 'success'),
(2, 4, '2025-05-04 08:14:23', '::1', 'success'),
(3, 4, '2025-05-04 09:32:15', '::1', 'success'),
(4, 4, '2025-05-04 10:41:42', '::1', 'success'),
(5, 4, '2025-05-04 11:15:59', '::1', 'success'),
(6, 6, '2025-05-05 01:52:06', '::1', 'failed'),
(7, 6, '2025-05-05 01:52:10', '::1', 'failed'),
(8, 6, '2025-05-05 01:52:15', '::1', 'success'),
(9, 6, '2025-05-05 02:35:26', '::1', 'failed'),
(10, 6, '2025-05-05 02:35:31', '::1', 'failed'),
(11, 6, '2025-05-05 02:35:32', '::1', 'failed'),
(12, 6, '2025-05-05 02:35:36', '::1', 'success'),
(13, 6, '2025-05-05 05:32:16', '::1', 'success'),
(14, 6, '2025-05-05 06:52:17', '::1', 'success'),
(15, 6, '2025-05-05 07:18:16', '::1', ''),
(16, 6, '2025-05-05 07:18:21', '::1', 'success'),
(17, 6, '2025-05-05 07:18:37', '::1', ''),
(18, 6, '2025-05-05 07:18:39', '::1', 'success'),
(19, 6, '2025-05-05 07:18:48', '::1', ''),
(20, 6, '2025-05-05 07:18:51', '::1', 'success'),
(21, 6, '2025-05-05 07:18:58', '::1', ''),
(22, 6, '2025-05-05 07:19:05', '::1', 'success'),
(23, 6, '2025-05-05 07:19:08', '::1', ''),
(24, 6, '2025-05-05 07:19:16', '::1', 'success'),
(25, 6, '2025-05-05 07:19:19', '::1', ''),
(26, 6, '2025-05-05 07:19:21', '::1', 'success'),
(27, 6, '2025-05-05 07:21:45', '::1', ''),
(28, 6, '2025-05-05 07:21:49', '::1', 'success'),
(29, 6, '2025-05-05 07:34:40', '::1', ''),
(30, 6, '2025-05-05 07:34:43', '::1', 'success'),
(31, 6, '2025-05-05 07:34:51', '::1', ''),
(32, 6, '2025-05-05 07:34:55', '::1', 'success'),
(33, 6, '2025-05-05 07:34:59', '::1', ''),
(34, 6, '2025-05-05 07:35:03', '::1', 'success'),
(35, 6, '2025-05-05 07:37:46', '::1', ''),
(36, 6, '2025-05-05 07:39:51', '::1', 'success'),
(37, 6, '2025-05-05 07:45:19', '::1', ''),
(38, 6, '2025-05-05 07:52:13', '::1', 'success'),
(39, 6, '2025-05-05 08:46:10', '::1', ''),
(40, 6, '2025-05-05 08:46:16', '::1', 'success'),
(41, 6, '2025-05-05 09:15:17', '::1', ''),
(42, 6, '2025-05-05 09:15:24', '::1', 'success'),
(43, 6, '2025-05-05 09:49:05', '::1', ''),
(44, 6, '2025-05-05 09:49:13', '::1', 'success');

-- --------------------------------------------------------

--
-- Table structure for table `login_tokens`
--

CREATE TABLE `login_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(32) NOT NULL,
  `expiry` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_tokens`
--

INSERT INTO `login_tokens` (`id`, `user_id`, `token`, `expiry`) VALUES
(1, 4, 'e9cb9b0f41e74d73dfbd19d9ca799b6a', '2025-06-03 08:13:14'),
(2, 4, 'd4f0f5ee744c147d40464be579e548cb', '2025-06-03 08:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `note` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(50) NOT NULL,
  `status` varchar(20) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `recipient_name`, `phone`, `address`, `total_amount`, `created_at`, `payment_method`, `status`) VALUES
(1, 1, 'Tên mới', 'Số mới', 'Địa chỉ mới', 135000.00, '2025-05-04 02:49:32', 'Tiền mặt', 'pending'),
(2, 2, 'Trần Thị B', '0912345678', '456 Nguyễn Trãi, TP.HCM', 300000.00, '2025-05-04 02:49:32', 'Tiền mặt', 'pending'),
(8, 6, 'Minh Anh', '0366624578', 'TP HCM', 120000.00, '2025-05-05 01:20:36', 'Tiền mặt', 'approved'),
(9, 6, 'Ngọc Anh', '0366624578', 'TpHCM', 120000.00, '2025-05-05 01:49:25', 'Tiền mặt', 'pending'),
(10, 6, 'Minh Anh', '0366624578', 'TPHCM', 120000.00, '2025-05-05 02:03:29', 'Tiền mặt', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `banh_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `banh_id`, `quantity`, `price`) VALUES
(1, 1, 1, 1, 75000.00),
(2, 1, 25, 1, 15000.00),
(3, 2, 3, 1, 300000.00),
(4, 8, 26, 1, 120000.00),
(5, 9, 26, 1, 120000.00),
(6, 10, 26, 1, 120000.00);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reset_token` varchar(255) NOT NULL,
  `new_password` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_requests`
--

INSERT INTO `password_reset_requests` (`id`, `user_id`, `reset_token`, `new_password`, `status`, `created_at`, `approved_at`) VALUES
(1, 6, '54e9506ba37b2ff1444a481a051bfc50', '$2y$10$0mV6QhxCCQ3UK10cT8G2WuK20GekbrNdPTKP9imjqzUJzk0hN5Hje', 'pending', '2025-05-05 05:47:50', NULL),
(2, 6, '8050bc96b30eff4456578b4b8844776f', '$2y$10$w85fv/81WmNTaxd2LoRfa.3ol8xoqxKkkaUKCGHs20cQu4Vr18HMm', 'pending', '2025-05-05 05:51:28', NULL),
(3, 6, '7245ec6927356dc8f0b3c336390a0f07', '$2y$10$ZwfANbDZgs0oN6aDDSXvpOzGPttT6AJwMq3b7MgCmp6abo1RUa4WW', 'approved', '2025-05-05 05:51:32', '2025-05-05 07:24:14');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `banh_id` int(11) NOT NULL,
  `gia_khuyen_mai` decimal(10,2) NOT NULL,
  `ngay_bat_dau` date NOT NULL,
  `ngay_ket_thuc` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`id`, `banh_id`, `gia_khuyen_mai`, `ngay_bat_dau`, `ngay_ket_thuc`) VALUES
(1, 1, 60000.00, '2025-05-01', '2025-05-10'),
(2, 2, 70000.00, '2025-05-01', '2025-05-10'),
(3, 25, 12000.00, '2025-05-01', '2025-05-10'),
(6, 32, 85000.00, '2025-05-01', '2025-05-10');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `stars` varchar(10) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `timestamp` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `name`, `text`, `stars`, `user_id`, `status`, `timestamp`) VALUES
(1, 'Nguyễn Văn A', 'Bánh rất ngon, giao hàng nhanh!', '★★★★★', 1, 'approved', 1714719600000),
(2, 'Trần Thị B', 'Bánh kem hơi ngọt quá, nhưng nhìn rất đẹp.', '★★★★', 2, 'pending', 1714723200000),
(3, 'Lê Minh C', 'Bánh mì mềm và thơm, sẽ mua lại!', '★★★★★', 3, 'approved', 1714726800000);

-- --------------------------------------------------------
--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_reviews_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipping_info`
--

CREATE TABLE `shipping_info` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `recipient_address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `remember_token`, `created_at`, `phone`) VALUES
(1, 'user1', '$2y$10$XvZ5e5e5e5e5e5e5e5e5e5u5e5e5e5e5e5e5e5e5e5e5e5e5e5e5e', 'user1@example.com', NULL, '2025-05-04 02:49:31', NULL),
(2, 'user2', '$2y$10$XvZ5e5e5e5e5e5e5e5e5e5u5e5e5e5e5e5e5e5e5e5e5e5e5e5e5e', 'user2@example.com', NULL, '2025-05-04 02:49:31', NULL),
(3, 'user3', '$2y$10$XvZ5e5e5e5e5e5e5e5e5e5u5e5e5e5e5e5e5e5e5e5e5e5e5e5e5e', 'user3@example.com', NULL, '2025-05-04 02:49:31', NULL),
(4, 'kieumy132', '$2y$10$l2VkRA2DJJFwStKxK.EY/umjYiFtPSenEF3Zy1OlbWfMysa4riGMe', 'kyomi13026@gmail.com', NULL, '2025-05-03 23:09:39', NULL),
(5, '075305003342', '$2y$10$HC80.A92i8JMCK.0OLOXCOXw9eyTe.PBFKVe5D9zS1aBPiseRVu.q', 'ashymy1302@gmail.com', NULL, '2025-05-04 06:13:40', NULL),
(6, '123', '$2y$10$ZwfANbDZgs0oN6aDDSXvpOzGPttT6AJwMq3b7MgCmp6abo1RUa4WW', 'ngocluong2388@gmail.com', NULL, '2025-05-04 11:12:30', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `banh`
--
ALTER TABLE `banh`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `banh_id` (`banh_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blog_id` (`blog_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `blog_id` (`blog_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `login_tokens`
--
ALTER TABLE `login_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `banh_id` (`banh_id`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `banh_id` (`banh_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `shipping_info`
--
ALTER TABLE `shipping_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `banh`
--
ALTER TABLE `banh`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `likes`
--
ALTER TABLE `likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `login_tokens`
--
ALTER TABLE `login_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `shipping_info`
--
ALTER TABLE `shipping_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blogs`
--
ALTER TABLE `blogs`
  ADD CONSTRAINT `blogs_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`banh_id`) REFERENCES `banh` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`);

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `login_tokens`
--
ALTER TABLE `login_tokens`
  ADD CONSTRAINT `login_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`banh_id`) REFERENCES `banh` (`id`);

--
-- Constraints for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD CONSTRAINT `password_reset_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `promotions`
--
ALTER TABLE `promotions`
  ADD CONSTRAINT `promotions_ibfk_1` FOREIGN KEY (`banh_id`) REFERENCES `banh` (`id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `shipping_info`
--
ALTER TABLE `shipping_info`
  ADD CONSTRAINT `shipping_info_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
