USE bus_owner_control;

INSERT INTO buses (bus_number, plate_number, capacity, model, status, latra_license_expiry, insurance_expiry, next_service_date) VALUES
('BUS 01', 'T123 ABC', 45, 'Yutong', 'active', '2027-12-31', '2027-06-30', '2026-08-15'),
('BUS 02', 'T456 DEF', 49, 'Scania', 'active', '2027-12-31', '2027-06-30', '2026-08-20');

INSERT INTO routes (origin, destination, distance_km, standard_fare, status) VALUES
('Dar es Salaam', 'Morogoro', 195, 15000, 'active'),
('Dar es Salaam', 'Dodoma', 450, 35000, 'active'),
('Dar es Salaam', 'Arusha', 650, 55000, 'active');

INSERT INTO drivers (name, phone, license_number, license_expiry, status) VALUES
('Juma Ally', '0712000001', 'DRV-001', '2028-01-01', 'active'),
('Musa John', '0712000002', 'DRV-002', '2028-01-01', 'active');

INSERT INTO trips (route_id, bus_id, driver_id, conductor_name, departure_date, departure_time, fare, status) VALUES
(1, 1, 1, 'Hassan', CURDATE(), '06:00:00', 15000, 'scheduled'),
(2, 2, 2, 'Baraka', CURDATE(), '08:30:00', 35000, 'scheduled');

INSERT INTO bookings (trip_id, agent_id, passenger_name, phone, seat_no, boarding_point, destination_point, amount_paid, payment_method, status) VALUES
(1, 1, 'Asha Mohamed', '0714000001', '1', 'Urafiki', 'Morogoro', 15000, 'cash', 'paid'),
(1, 2, 'Peter Joseph', '0714000002', '2', 'Posta', 'Morogoro', 15000, 'mobile_money', 'paid'),
(2, 1, 'Neema Charles', '0714000003', '5', 'Urafiki', 'Dodoma', 35000, 'cash', 'paid');

INSERT INTO parcels (tracking_code, trip_id, agent_id, sender_name, sender_phone, receiver_name, receiver_phone, description, from_branch, to_branch, amount_paid, payment_method, status) VALUES
('PCL-DEMO-001', 1, 1, 'Ali K', '0715000001', 'Said M', '0715000002', 'Small box of clothes', 'Urafiki', 'Morogoro', 8000, 'cash', 'received'),
('PCL-DEMO-002', 2, 2, 'Grace P', '0715000003', 'Mary J', '0715000004', 'Documents envelope', 'Posta', 'Dodoma', 5000, 'cash', 'received');

INSERT INTO expenses (trip_id, bus_id, category, amount, expense_date, paid_by, notes) VALUES
(1, 1, 'fuel', 180000, CURDATE(), 'Manager', 'Morning fuel'),
(2, 2, 'fuel', 300000, CURDATE(), 'Manager', 'Dodoma trip fuel'),
(NULL, 1, 'repair', 50000, CURDATE(), 'Mechanic', 'Minor check');

INSERT INTO maintenance_records (bus_id, maintenance_type, service_date, odometer_km, vendor, description, cost, status, next_due_date, created_by) VALUES
(1, 'service', CURDATE(), 156000, 'Urafiki Garage', 'Routine oil service and safety check', 120000, 'completed', DATE_ADD(CURDATE(), INTERVAL 45 DAY), 2),
(2, 'inspection', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 210000, 'Dar Inspection Centre', 'Pre-trip inspection and brake check', 0, 'planned', DATE_ADD(CURDATE(), INTERVAL 60 DAY), 2);

INSERT INTO fuel_logs (bus_id, trip_id, fuel_date, litres, price_per_litre, total_amount, odometer_km, station, notes, created_by) VALUES
(1, 1, CURDATE(), 65.50, 3200, 209600, 156100, 'Urafiki Fuel Station', 'Morning trip fuel', 2),
(2, 2, CURDATE(), 110.00, 3200, 352000, 210050, 'Posta Fuel Station', 'Dodoma trip fuel', 2);

INSERT INTO cash_closings (closing_date, agent_id, ticket_cash_expected, parcel_cash_expected, total_cash_expected, cash_submitted, mobile_money_submitted, bank_submitted, expenses_paid, variance, status, prepared_by, notes) VALUES
(CURDATE(), 1, 50000, 8000, 58000, 58000, 0, 0, 0, 0, 'submitted', 4, 'Demo Urafiki daily closing'),
(CURDATE(), 2, 0, 5000, 5000, 5000, 15000, 0, 0, 15000, 'submitted', 5, 'Demo Posta closing with mobile money ticket collection');
