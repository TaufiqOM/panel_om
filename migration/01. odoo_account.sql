CREATE TABLE `user_accounts` (
  `id` int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  `username` varchar(100) NOT NULL,
  `uid` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_role` TINYINT(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci