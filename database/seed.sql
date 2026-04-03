-- ============================================================
-- FarmFlow MVP — Sample Seed Data
-- Run AFTER schema.sql
-- ============================================================

START TRANSACTION;

-- ============================================================
-- Farms
-- ============================================================
INSERT INTO `farms` (`name`, `state`, `city`, `type`, `size`) VALUES
('Green Valley Farm',   'Lagos',    'Ikorodu',   'mixed',      45.50),
('Sunrise Poultry',     'Oyo',      'Ibadan',    'poultry',    12.00),
('Riverside Crops',     'Kano',     'Kano City', 'crop',       80.00),
('Delta Livestock',     'Delta',    'Warri',     'livestock',  30.75),
('Plateau Orchards',    'Plateau',  'Jos',       'orchard',    22.00);

-- ============================================================
-- Users  (passwords are bcrypt of "password123")
-- ============================================================
INSERT INTO `users` (`name`, `email`, `password`, `role`, `farm_id`, `email_notifications`, `theme_preference`) VALUES
('Admin Owner',    'admin@farmflow.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner',   NULL, 1, 'dark'),
('Amaka Obi',      'amaka@farmflow.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1,    1, 'light'),
('Chidi Nwosu',    'chidi@farmflow.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 2,    1, 'light'),
('Fatima Musa',    'fatima@farmflow.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'viewer',  3,    0, 'light'),
('Emeka Dike',     'emeka@farmflow.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'viewer',  1,    0, 'light');

-- ============================================================
-- Animals
-- ============================================================
INSERT INTO `animals` (`farm_id`, `type`, `breed`, `quantity`, `birth_date`, `maturity_days`, `health_status`, `sold`, `sold_date`, `price_sold`) VALUES
(1, 'Cattle',  'Bunaji',         12, DATE_SUB(CURDATE(), INTERVAL 280 DAY), 365, 'healthy',     0, NULL,       NULL),
(1, 'Goat',    'West African',   30, DATE_SUB(CURDATE(), INTERVAL 120 DAY), 180, 'healthy',     0, NULL,       NULL),
(1, 'Pig',     'Large White',    20, DATE_SUB(CURDATE(), INTERVAL 90 DAY),  160, 'healthy',     0, NULL,       NULL),
(2, 'Chicken', 'Broiler',       500, DATE_SUB(CURDATE(), INTERVAL 35 DAY),  42,  'healthy',     0, NULL,       NULL),
(2, 'Chicken', 'Layer',         200, DATE_SUB(CURDATE(), INTERVAL 180 DAY), 168, 'healthy',     0, NULL,       NULL),
(2, 'Turkey',  'Broad Breasted', 80, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 140, 'recovering',  0, NULL,       NULL),
(4, 'Cattle',  'Sokoto Gudali',  18, DATE_SUB(CURDATE(), INTERVAL 400 DAY), 365, 'healthy',     1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 850000.00),
(4, 'Sheep',   'Uda',            45, DATE_SUB(CURDATE(), INTERVAL 200 DAY), 240, 'healthy',     0, NULL,       NULL),
(1, 'Rabbit',  'New Zealand',    60, DATE_SUB(CURDATE(), INTERVAL 55 DAY),  70,  'sick',        0, NULL,       NULL),
(2, 'Duck',    'Pekin',          40, DATE_SUB(CURDATE(), INTERVAL 60 DAY),  56,  'healthy',     0, NULL,       NULL);

-- ============================================================
-- Crops
-- ============================================================
INSERT INTO `crops` (`farm_id`, `type`, `variety`, `quantity`, `quantity_unit`, `planting_date`, `maturity_days`, `harvested`, `harvested_date`, `price_sold`) VALUES
(3, 'Maize',    'SUWAN-1',       5000, 'kg',    DATE_SUB(CURDATE(), INTERVAL 75 DAY),  90,  0, NULL,       NULL),
(3, 'Sorghum',  'SK5912',        3000, 'kg',    DATE_SUB(CURDATE(), INTERVAL 100 DAY), 120, 0, NULL,       NULL),
(1, 'Tomato',   'Roma VF',        800, 'kg',    DATE_SUB(CURDATE(), INTERVAL 55 DAY),  75,  0, NULL,       NULL),
(1, 'Cassava',  'TMS 30572',     2000, 'kg',    DATE_SUB(CURDATE(), INTERVAL 240 DAY), 365, 0, NULL,       NULL),
(5, 'Mango',    'Alphonso',      1200, 'units', DATE_SUB(CURDATE(), INTERVAL 90 DAY),  120, 0, NULL,       NULL),
(5, 'Banana',   'Cavendish',      600, 'bunches',DATE_SUB(CURDATE(), INTERVAL 270 DAY),300, 0, NULL,       NULL),
(3, 'Pepper',   'Tatashe',        400, 'kg',    DATE_SUB(CURDATE(), INTERVAL 80 DAY),  90,  1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 320000.00),
(1, 'Yam',      'Pona',          1500, 'tubers',DATE_SUB(CURDATE(), INTERVAL 200 DAY), 270, 0, NULL,       NULL),
(3, 'Cowpea',   'IT90K-277-2',   2500, 'kg',    DATE_SUB(CURDATE(), INTERVAL 50 DAY),  75,  0, NULL,       NULL),
(1, 'Spinach',  'Green Valley',   300, 'kg',    DATE_SUB(CURDATE(), INTERVAL 25 DAY),  40,  1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 45000.00);

-- ============================================================
-- Egg Production (last 14 days for farm 2)
-- ============================================================
INSERT INTO `egg_production` (`farm_id`, `animal_id`, `date_produced`, `quantity`, `daily_target`, `sold`, `price_sold`, `recorded_by`) VALUES
(2, 5, DATE_SUB(CURDATE(), INTERVAL 13 DAY), 180, 200, 1, 27000.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 195, 200, 1, 29250.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 11 DAY), 210, 200, 1, 31500.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 188, 200, 1, 28200.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 9 DAY),  175, 200, 0, NULL,     3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 8 DAY),  202, 200, 1, 30300.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  198, 200, 1, 29700.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 6 DAY),  215, 200, 1, 32250.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  190, 200, 0, NULL,     3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 4 DAY),  205, 200, 1, 30750.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  220, 200, 1, 33000.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  185, 200, 1, 27750.00, 3),
(2, 5, DATE_SUB(CURDATE(), INTERVAL 1 DAY),  200, 200, 1, 30000.00, 3),
(2, 5, CURDATE(),                            210, 200, 0, NULL,     3);

-- ============================================================
-- Sales Ledger
-- ============================================================
INSERT INTO `sales` (`farm_id`, `entity_type`, `entity_id`, `sale_date`, `quantity`, `unit_price`, `buyer_name`, `recorded_by`) VALUES
(4, 'animal', 7, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  18,   47222.22, 'Alhaji Sule Traders',    1),
(3, 'crop',   7, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  400,  800.00,   'Lagos Pepper Market',    2),
(1, 'crop',   10, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 300,  150.00,   'Green Fresh Supermarket',2),
(2, 'egg',    8,  DATE_SUB(CURDATE(), INTERVAL 6 DAY), 180,  150.00,   'Egg Wholesale Ibadan',   3),
(2, 'egg',    9,  DATE_SUB(CURDATE(), INTERVAL 5 DAY), 195,  150.00,   'Egg Wholesale Ibadan',   3);

-- ============================================================
-- Logs (initial sample audit entries)
-- ============================================================
INSERT INTO `logs` (`user_id`, `action_type`, `entity_type`, `entity_id`, `description`, `ip_address`) VALUES
(1, 'login',   NULL,            NULL, 'Owner logged in',                                 '127.0.0.1'),
(1, 'create',  'farms',         1,    'Created farm: Green Valley Farm',                 '127.0.0.1'),
(1, 'create',  'farms',         2,    'Created farm: Sunrise Poultry',                   '127.0.0.1'),
(1, 'create',  'users',         2,    'Created user: Amaka Obi (manager)',               '127.0.0.1'),
(1, 'create',  'users',         3,    'Created user: Chidi Nwosu (manager)',             '127.0.0.1'),
(2, 'login',   NULL,            NULL, 'Manager Amaka Obi logged in',                     '192.168.1.5'),
(2, 'create',  'animals',       1,    'Added 12 Bunaji Cattle to Green Valley Farm',     '192.168.1.5'),
(3, 'login',   NULL,            NULL, 'Manager Chidi Nwosu logged in',                   '192.168.1.8'),
(3, 'create',  'egg_production',1,    'Recorded 180 eggs for farm: Sunrise Poultry',     '192.168.1.8'),
(1, 'sell',    'animals',       7,    'Sold 18 Sokoto Gudali Cattle for ₦850,000',       '127.0.0.1'),
(2, 'harvest', 'crops',         7,    'Harvested Tatashe Pepper — 400kg for ₦320,000',   '192.168.1.5'),
(3, 'record_eggs','egg_production',14,'Recorded 210 eggs today for Sunrise Poultry',     '192.168.1.8');

COMMIT;
