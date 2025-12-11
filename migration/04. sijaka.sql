-- Table Employee
CREATE TABLE employee (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_employee INT NOT NULL UNIQUE,
  name VARCHAR(255),
  email VARCHAR(200),
  phone VARCHAR(50),
  barcode VARCHAR(100) NOT NULL UNIQUE,
  image_1920 LONGBLOB,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel master stations
CREATE TABLE stations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  station_code VARCHAR(50) NOT NULL UNIQUE,
  station_name VARCHAR(100) NOT NULL
);

-- Tabel absensi aktif (hanya data terbaru yang sedang aktif per karyawan)
CREATE TABLE production_employee (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_employee INT NOT NULL,
  employee_nik VARCHAR(100) NOT NULL,
  employee_fullname VARCHAR(255),
  employee_img LONGBLOB,
  status INT,
  process ENUM('order','product') DEFAULT NULL,
  customer_name VARCHAR(255),
  production_code VARCHAR(100),
  station_code VARCHAR(50),
  scan_in DATETIME,
  scan_out DATETIME,
  duration_seconds INT DEFAULT 0,
  work VARCHAR(50),
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_employee_nik (employee_nik),
  INDEX idx_status (status),
  FOREIGN KEY (id_employee) REFERENCES employee(id_employee),
  FOREIGN KEY (station_code) REFERENCES stations(station_code)
);

-- Tabel history absensi (semua riwayat masuk di sini)
CREATE TABLE production_employee_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_employee INT NOT NULL,
  employee_nik VARCHAR(100) NOT NULL,
  employee_fullname VARCHAR(255),
  employee_img LONGBLOB,
  process ENUM('order','product') DEFAULT NULL,
  customer_name VARCHAR(255),
  production_code VARCHAR(100),
  station_code VARCHAR(50),
  scan_in DATETIME NOT NULL,
  scan_out DATETIME NOT NULL,
  duration_seconds INT DEFAULT 0,
  work VARCHAR(50),
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_employee_nik (employee_nik),
  INDEX idx_scan_in (scan_in),
  FOREIGN KEY (id_employee) REFERENCES employee(id_employee),
  FOREIGN KEY (station_code) REFERENCES stations(station_code)
);

-- Table production lot (untuk tracking posisi barang)
CREATE TABLE production_lots (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  customer_name   VARCHAR(255) NOT NULL,
  so_name       VARCHAR(50) NOT NULL,
  product_code INT NOT NULL,
  production_code VARCHAR(50) NOT NULL,
  station_code    VARCHAR(50) NOT NULL,
  scan_in         DATETIME NOT NULL,
  scan_out        DATETIME,
  FOREIGN KEY (production_code) REFERENCES barcode_item(barcode),
  FOREIGN KEY (station_code) REFERENCES stations(station_code),
  FOREIGN KEY (product_code) REFERENCES barcode_item(product_id)
);

-- History posisi product
CREATE TABLE production_lots_history (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  customer_name   VARCHAR(255) NOT NULL,
  so_name       VARCHAR(50) NOT NULL,
  product_code INT NOT NULL,
  production_code VARCHAR(50) NOT NULL,
  station_code    VARCHAR(50) NOT NULL,
  scan_in         DATETIME NOT NULL,
  scan_out        DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (production_code) REFERENCES barcode_item(barcode),
  FOREIGN KEY (station_code) REFERENCES stations(station_code),
  FOREIGN KEY (product_code) REFERENCES barcode_item(product_id)
);

-- Semua product yang sudah di delivery
CREATE TABLE production_lots_delivery (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  delivery_code   VARCHAR(50),
  customer_name   VARCHAR(255),
  so_name         VARCHAR(100),
  product_code INT NOT NULL,
  production_code VARCHAR(50) UNIQUE,
  date_delivery DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (production_code) REFERENCES barcode_item(barcode),
  FOREIGN KEY (product_code) REFERENCES barcode_item(product_id)
);


-- Insert sample data for testing
INSERT INTO stations (station_code, station_name) VALUES 
('OM1', 'PROCESSING'),
('OM2', 'PROCESSING'),
('OM3', 'PROCESSING'),
('RCV', 'RECEIVE IN'),
('ASM', 'ASSEMBLY'),
('OM4', 'SANDING'),
('CS2', 'RETRO'),
('QCN', 'QC IN'),
('CS3', 'COLORING'),
('QCL', 'QC Coloring'),
('PA1', 'PACKING FPS'),
('PA2', 'PACKING FPS'),
('QCF', 'QC FNPACK'),
('SP1', 'SMALL PRODUCTION'),
('STR', 'STORAGE');

