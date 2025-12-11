-- Tabel barcode per lot (Bisa banyak Item)
CREATE TABLE `barcode_lot` (
  `id` int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  `sale_order_id` int(11) DEFAULT NULL COMMENT 'Relasi dengan sales (sale.order)',
  `sale_order_line_id` int(11) DEFAULT NULL COMMENT 'Relasi dengan sale.order.line',
  `sale_order_name` varchar(100) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL COMMENT 'Relasi dengan product.product',
  `product_name` varchar(200) DEFAULT NULL,
  `prefix_code` int(11) DEFAULT NULL COMMENT 'Unix ID unik contoh: 0000123',
  `qty_order` int(11) DEFAULT NULL COMMENT 'Total qty order (misal 100)',
  `last_number` int(11) DEFAULT 0 COMMENT 'Urutan terakhir yang sudah dibuat, misal 5',
  `order_date` datetime DEFAULT NULL COMMENT 'Order date from sale.order',
  `order_due_date` datetime DEFAULT NULL COMMENT 'Due date from sale.order',
  `country` varchar(100) DEFAULT NULL COMMENT 'Country from sale.order partner',
  `info_to_production` text DEFAULT NULL COMMENT 'Per line item description to production',
  `info_to_buyer` text DEFAULT NULL COMMENT 'Per line item description to buyer',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel barcode per Item
CREATE TABLE `barcode_item` (
  `id` int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  `lot_id` int(11) NOT NULL COMMENT 'Relasi ke barcode_lot',
  `barcode` varchar(50) NOT NULL UNIQUE COMMENT 'Barcode full contoh: 0000123-00006-00100',
  `sequence_no` int(11) NOT NULL COMMENT 'Nomor urut contoh: 6',
  `product_id` int(11) DEFAULT NULL COMMENT 'Relasi dengan product.product',
  `mrp_id` int(11) DEFAULT NULL COMMENT 'Relasi dengan sales (mrp.production)',
  `sale_order_id` int(11) DEFAULT NULL COMMENT 'Relasi dengan sales (sale.order)',
  `customer_name` varchar(255) DEFAULT NULL,
  `sale_order_line_id` int(11) DEFAULT NULL COMMENT 'Relasi dengan sale.order.line',
  `status` varchar(20) DEFAULT 'generated' COMMENT 'Default generated (nanti bisa sanding/packing/done)',
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL COMMENT 'User/operator',
  KEY idx_barcode_item_product_id (`product_id`)
  FOREIGN KEY (`lot_id`) REFERENCES `barcode_lot`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

