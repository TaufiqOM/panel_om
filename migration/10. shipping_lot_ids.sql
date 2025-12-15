-- Migration untuk menyimpan lot/serial number dari stock.picking
-- Tabel untuk menyimpan lot_ids per picking

CREATE TABLE IF NOT EXISTS shipping_lot_ids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    picking_id INT NOT NULL,
    picking_name VARCHAR(255),
    lot_id INT,
    lot_name VARCHAR(255),
    product_id INT,
    product_name VARCHAR(255),
    qty_done DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (picking_id) REFERENCES shipping_detail(id) ON DELETE CASCADE,
    INDEX idx_picking_id (picking_id),
    INDEX idx_lot_id (lot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
