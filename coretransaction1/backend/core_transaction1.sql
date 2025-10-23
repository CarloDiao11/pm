-- Recreated from Supabase schema for MySQL (XAMPP)

CREATE DATABASE IF NOT EXISTS corepm;
USE corepm;

-- USERS TABLE
CREATE TABLE users (
  user_id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(150) UNIQUE,
  password_hash TEXT,
  role ENUM('admin', 'driver', 'user') DEFAULT 'user',
  status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users 
ADD COLUMN username VARCHAR(100) UNIQUE AFTER email;
-- Add profile_picture column to users table
ALTER TABLE users 
ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER status;

-- Add profile_picture to drivers table
ALTER TABLE drivers 
ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER rating;

-- Create uploads directory structure (to be created manually)
-- Create folders: /uploads/profile_pictures/

ALTER TABLE trips 
ADD COLUMN destination_lat DECIMAL(10, 8) AFTER destination,
ADD COLUMN destination_lon DECIMAL(10, 8) AFTER destination_lat;

-- VEHICLES TABLE
CREATE TABLE vehicles (
  vehicle_id CHAR(36) NOT NULL PRIMARY KEY,
  plate_number VARCHAR(50),
  type VARCHAR(50),
  status ENUM('available', 'in_use', 'maintenance', 'inactive') DEFAULT 'available',
  mileage DECIMAL(10,2),
  insurance_expiry DATE
);

-- DRIVERS TABLE
CREATE TABLE drivers (
  drivers_id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  license_number VARCHAR(50),
  license_expiry DATE,
  contact_number VARCHAR(20),
  address TEXT,
  rating DECIMAL(3,2),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- DRIVER DOCUMENTS
CREATE TABLE driver_documents (
  doc_id CHAR(36) NOT NULL PRIMARY KEY,
  driver_id CHAR(36),
  doc_type VARCHAR(100),
  file_url TEXT,
  expiry_date DATE,
  FOREIGN KEY (driver_id) REFERENCES drivers(drivers_id)
);

-- DRIVER WALLETS
CREATE TABLE driver_wallets (
  wallet_id CHAR(36) NOT NULL PRIMARY KEY,
  driver_id CHAR(36),
  current_balance DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (driver_id) REFERENCES drivers(drivers_id)
);

-- TRIPS
CREATE TABLE trips (
  trip_id CHAR(36) NOT NULL PRIMARY KEY,
  driver_id CHAR(36),
  vehicle_id CHAR(36),
  trip_type VARCHAR(50),
  origin TEXT,
  destination TEXT,
  status ENUM('pending','ongoing','completed','cancelled') DEFAULT 'pending',
  start_time DATETIME,
  end_time DATETIME,
  FOREIGN KEY (driver_id) REFERENCES drivers(drivers_id),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id)
);

-- TRANSACTIONS
CREATE TABLE transactions (
  transaction_id CHAR(36) NOT NULL PRIMARY KEY,
  wallet_id CHAR(36),
  trip_id CHAR(36),
  amount DECIMAL(10,2),
  type ENUM('credit','debit'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('completed','pending','failed') DEFAULT 'completed',
  FOREIGN KEY (wallet_id) REFERENCES driver_wallets(wallet_id),
  FOREIGN KEY (trip_id) REFERENCES trips(trip_id)
);

-- FUEL LOGS
CREATE TABLE fuel_logs (
  fuel_id CHAR(36) NOT NULL PRIMARY KEY,
  vehicle_id CHAR(36),
  driver_id CHAR(36),
  liters DECIMAL(10,2),
  cost DECIMAL(10,2),
  date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  station_name VARCHAR(100),
  odometer_reading DECIMAL(10,2),
  fuel_type ENUM('petrol','diesel','gas') DEFAULT 'diesel',
  receipt_number VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
  FOREIGN KEY (driver_id) REFERENCES drivers(drivers_id)
);

-- CONSUMABLES
CREATE TABLE consumables (
  consumable_id CHAR(36) NOT NULL PRIMARY KEY,
  item_name VARCHAR(100),
  stock_qty BIGINT DEFAULT 0,
  min_threshold BIGINT,
  category VARCHAR(100),
  unit_price DECIMAL(10,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  supplier_name VARCHAR(100),
  supplier_contact VARCHAR(100),
  location VARCHAR(100) DEFAULT 'Main Warehouse',
  status ENUM('active','discontinued','out_of_stock') DEFAULT 'active'
);

-- CONSUMABLE LOGS
CREATE TABLE consumable_logs (
  log_id CHAR(36) NOT NULL PRIMARY KEY,
  consumable_id CHAR(36),
  usage_type ENUM('maintenance','repair','replacement','emergency','routine'),
  quantity BIGINT,
  date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  requested_by CHAR(36),
  vehicle_id CHAR(36),
  purpose TEXT,
  unit_cost DECIMAL(10,2),
  total_cost DECIMAL(10,2),
  approved_by CHAR(36),
  status ENUM('pending','approved','rejected') DEFAULT 'approved',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (consumable_id) REFERENCES consumables(consumable_id),
  FOREIGN KEY (requested_by) REFERENCES users(user_id)
);

-- STOCK MOVEMENTS
CREATE TABLE stock_movements (
  id CHAR(36) NOT NULL PRIMARY KEY,
  consumable_id CHAR(36) NOT NULL,
  movement_type ENUM('in','out','adjustment') NOT NULL,
  quantity INT NOT NULL,
  reference_type VARCHAR(50),
  reference_id CHAR(36),
  notes TEXT,
  created_by CHAR(36),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (consumable_id) REFERENCES consumables(consumable_id)
);

-- MAINTENANCE LOGS
CREATE TABLE maintenance_logs (
  log_id CHAR(36) NOT NULL PRIMARY KEY,
  vehicle_id CHAR(36),
  description TEXT,
  cost DECIMAL(10,2),
  serviced_by VARCHAR(100),
  completed_date DATE,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id)
);

-- MAINTENANCE SCHEDULES
CREATE TABLE maintenance_schedules (
  schedule_id CHAR(36) NOT NULL PRIMARY KEY,
  vehicle_id CHAR(36),
  schedule_date DATE,
  type VARCHAR(100),
  status VARCHAR(50),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id)
);

-- INCIDENT REPORTS
CREATE TABLE incident_reports (
  report_id CHAR(36) NOT NULL PRIMARY KEY,
  vehicle_id CHAR(36),
  driver_id CHAR(36),
  description TEXT,
  severity VARCHAR(50),
  date_reported TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
  FOREIGN KEY (driver_id) REFERENCES drivers(drivers_id)
);

-- NOTIFICATIONS
CREATE TABLE notifications (
  notification_id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  message TEXT,
  type VARCHAR(50),
  status VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- REPORTS
CREATE TABLE reports (
  report_id CHAR(36) NOT NULL PRIMARY KEY,
  report_type VARCHAR(100) NOT NULL,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  file_url TEXT,
  generated_by CHAR(36),
  FOREIGN KEY (generated_by) REFERENCES users(user_id)
);

-- Add KPI Snapshots Table
CREATE TABLE kpi_snapshots (
    snapshot_id CHAR(36) NOT NULL PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    active_trips INT DEFAULT 0,
    pending_payouts DECIMAL(12,2) DEFAULT 0.00,
    low_stock_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date (snapshot_date)
);

-- Add column to track when low stock was first detected
ALTER TABLE consumables 
ADD COLUMN last_low_stock_alert DATE NULL DEFAULT NULL AFTER min_threshold; 
