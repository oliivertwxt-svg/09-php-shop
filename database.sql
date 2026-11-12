
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(90) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int(10) unsigned NOT NULL CHECK (`quantity` > 0),
  PRIMARY KEY (`order_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `status` enum('pending','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(170) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL CHECK (`price` > 0),
  `stock` int(10) unsigned NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- Example catalog data only. Customer orders are deliberately not exported.
INSERT INTO categories (id, name, slug) VALUES (1, 'Phones', 'phones');
INSERT INTO categories (id, name, slug) VALUES (2, 'Laptops', 'laptops');
INSERT INTO categories (id, name, slug) VALUES (3, 'Accessories', 'accessories');
INSERT INTO categories (id, name, slug) VALUES (4, 'Home & Living', 'home-living');
INSERT INTO categories (id, name, slug) VALUES (6, 'Kitchen', 'kitchen');
INSERT INTO categories (id, name, slug) VALUES (7, 'Wellness', 'wellness');
INSERT INTO categories (id, name, slug) VALUES (8, 'Stationery', 'stationery');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (1, 4, 'Sculpted Ceramic Vase', 'sculpted-ceramic-vase', 'A softly sculpted stoneware vase with a matte finish, made to bring a little calm to any shelf.', '18500.00', 12, 'https://placehold.co/600x400?text=Sculpted+Ceramic+Vase', 1, '2026-10-28 20:09:47');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (2, 4, 'Woven Market Basket', 'woven-market-basket', 'A roomy, handwoven basket for market mornings, beach days, and all the bits in between.', '22000.00', 8, 'https://placehold.co/600x400?text=Woven+Market+Basket', 1, '2026-10-29 20:09:48');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (3, 4, 'Linen Cushion Cover', 'linen-cushion-cover', 'A breathable linen cushion cover in a warm natural shade, finished with a hidden zip.', '12500.00', 15, 'https://placehold.co/600x400?text=Linen+Cushion+Cover', 1, '2026-10-30 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (4, 3, 'Everyday Canvas Tote', 'everyday-canvas-tote', 'A sturdy cotton-canvas carryall with comfortable straps and space for your daily essentials.', '9500.00', 24, 'https://placehold.co/600x400?text=Everyday+Canvas+Tote', 1, '2026-10-31 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (5, 3, 'Minimal Leather Wallet', 'minimal-leather-wallet', 'A slim, thoughtfully organised wallet crafted from supple genuine leather.', '28500.00', 6, 'https://placehold.co/600x400?text=Minimal+Leather+Wallet', 1, '2026-11-01 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (6, 3, 'Classic Round Sunglasses', 'classic-round-sunglasses', 'Lightweight frames with UV-protective lenses for bright days and easy weekends.', '19500.00', 0, 'https://placehold.co/600x400?text=Classic+Round+Sunglasses', 1, '2026-11-02 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (7, 6, 'Pour-Over Coffee Set', 'pour-over-coffee-set', 'A ceramic dripper and matching cup for a slower, better morning coffee ritual.', '24000.00', 9, 'https://placehold.co/600x400?text=Pour-Over+Coffee+Set', 1, '2026-11-03 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (8, 6, 'Insulated Travel Flask', 'insulated-travel-flask', 'A double-wall stainless steel flask that keeps drinks hot or cold while you are on the move.', '16500.00', 18, 'https://placehold.co/600x400?text=Insulated+Travel+Flask', 1, '2026-11-04 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (9, 6, 'Acacia Serving Board', 'acacia-serving-board', 'A richly grained acacia board for serving snacks, slicing fruit, or styling your kitchen.', '14500.00', 7, 'https://placehold.co/600x400?text=Acacia+Serving+Board', 1, '2026-11-05 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (10, 7, 'Botanical Soy Candle', 'botanical-soy-candle', 'A clean-burning soy candle with a gentle botanical scent and a reusable glass vessel.', '11000.00', 21, 'https://placehold.co/600x400?text=Botanical+Soy+Candle', 1, '2026-11-06 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (11, 7, 'Daily Ritual Journal', 'daily-ritual-journal', 'An undated journal with guided prompts for reflection, intention, and everyday notes.', '8500.00', 30, 'https://placehold.co/600x400?text=Daily+Ritual+Journal', 1, '2026-11-07 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (12, 7, 'Cotton Bath Towel Set', 'cotton-bath-towel-set', 'A soft, absorbent pair of generously sized cotton towels in a timeless neutral tone.', '26000.00', 5, 'https://placehold.co/600x400?text=Cotton+Bath+Towel+Set', 1, '2026-11-08 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (13, 8, 'Pocket Notebook Trio', 'pocket-notebook-trio', 'Three pocket-sized notebooks with smooth, fountain-pen-friendly recycled paper.', '6500.00', 40, 'https://placehold.co/600x400?text=Pocket+Notebook+Trio', 1, '2026-11-09 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (14, 8, 'Brass Desk Pen', 'brass-desk-pen', 'A satisfying-weight brass pen with a fine black refill for your desk or notebook.', '13000.00', 11, 'https://placehold.co/600x400?text=Brass+Desk+Pen', 1, '2026-11-10 20:09:51');
INSERT INTO products (id, category_id, name, slug, description, price, stock, image_url, is_active, created_at) VALUES (15, 8, 'Weekly Planner Pad', 'weekly-planner-pad', 'A tear-off weekly planner pad with space for priorities, plans, and small reminders.', '7500.00', 0, 'https://placehold.co/600x400?text=Weekly+Planner+Pad', 0, '2026-11-11 20:09:51');
