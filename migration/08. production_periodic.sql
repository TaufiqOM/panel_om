-- Table to track synchronization status of production periodic info with Odoo
CREATE TABLE `production_periodic_sync` (
  `id` int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  `id_so` INT,
  `so_name` varchar(100) NOT NULL,
  `production_date` date NOT NULL,
  `synced_at` datetime DEFAULT current_timestamp(),
  UNIQUE KEY `unique_so_date` (`so_name`, `production_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table to store production periodic information locally
CREATE TABLE `production_periodic_info` (
  `id` int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  `so_name` varchar(100) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `product_id` varchar(100) NOT NULL,
  `sale_order_line_id` int(11),
  `production_date` date NOT NULL,
  `TO` int(11) DEFAULT 0,
  `OPN` int(11) DEFAULT 0,
  `AV` int(11) DEFAULT 0,
  `RAW` int(11) DEFAULT 0,
  `SND` int(11) DEFAULT 0,
  `RTR` int(11) DEFAULT 0,
  `QCIN` int(11) DEFAULT 0,
  `CLR` int(11) DEFAULT 0,
  `QCL` int(11) DEFAULT 0,
  `FinP` int(11) DEFAULT 0,
  `QFin` int(11) DEFAULT 0,
  `STR` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  UNIQUE KEY `unique_so_product_date` (`so_name`, `product_id`, `production_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
