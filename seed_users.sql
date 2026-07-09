-- ================================================================
-- Seed data: 3 Admins + 30 Employees
-- Passwords: Admins = Admin@123 | Employees = Employee@123
-- role_id: 2 = Admin, 3 = Employee (from roles table)
-- job_role_id: 1=Labor 2=Foreman 3=Mason 4=Electrician
--              5=Plumber 6=Painter 7=Tile Setter 8=Roof Installer
-- ================================================================
USE `gh_software_db`;

-- ---------------- 3 ADMINS ----------------
INSERT INTO `users`
(`employee_id`, `role_id`, `job_role_id`, `first_name`, `last_name`, `username`, `email`, `password`, `barcode`, `date_hired`, `timesheet_type`, `status`) VALUES
('KMR00002', 2, NULL, 'Ricardo', 'Villanueva', 'ricardo.villanueva', 'ricardo.villanueva@ghsoftware.com', '$2y$10$xFfyXLfev5HBlZwd0Sp21OLTKR6Y.AfXGmBxrIGs97Vo8rlJZJSnq', 'KMR00002', '2023-01-09', 'semi_monthly', 'active'),
('KMR00003', 2, NULL, 'Maricel',  'Santos',     'maricel.santos',     'maricel.santos@ghsoftware.com',     '$2y$10$xFfyXLfev5HBlZwd0Sp21OLTKR6Y.AfXGmBxrIGs97Vo8rlJZJSnq', 'KMR00003', '2023-03-20', 'semi_monthly', 'active'),
('KMR00004', 2, NULL, 'Antonio',  'Reyes',      'antonio.reyes',      'antonio.reyes@ghsoftware.com',      '$2y$10$xFfyXLfev5HBlZwd0Sp21OLTKR6Y.AfXGmBxrIGs97Vo8rlJZJSnq', 'KMR00004', '2023-06-05', 'semi_monthly', 'active');

-- ---------------- 30 EMPLOYEES ----------------
INSERT INTO `users`
(`employee_id`, `role_id`, `job_role_id`, `first_name`, `last_name`, `username`, `email`, `password`, `barcode`, `date_hired`, `timesheet_type`, `status`) VALUES
('KMR00005', 3, 2, 'Ernesto',   'Dela Cruz', 'ernesto.delacruz', 'ernesto.delacruz@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00005', '2023-02-13', 'weekly', 'active'),
('KMR00006', 3, 2, 'Rodel',     'Bautista',  'rodel.bautista',   'rodel.bautista@ghsoftware.com',   '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00006', '2023-04-17', 'weekly', 'active'),
('KMR00007', 3, 1, 'Joel',      'Mendoza',   'joel.mendoza',     'joel.mendoza@ghsoftware.com',     '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00007', '2023-05-08', 'weekly', 'active'),
('KMR00008', 3, 1, 'Marlon',    'Garcia',    'marlon.garcia',    'marlon.garcia@ghsoftware.com',    '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00008', '2023-07-24', 'weekly', 'active'),
('KMR00009', 3, 1, 'Dante',     'Ramos',     'dante.ramos',      'dante.ramos@ghsoftware.com',      '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00009', '2023-08-14', 'weekly', 'active'),
('KMR00010', 3, 1, 'Rogelio',   'Aquino',    'rogelio.aquino',   'rogelio.aquino@ghsoftware.com',   '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00010', '2023-09-04', 'weekly', 'active'),
('KMR00011', 3, 1, 'Federico',  'Navarro',   'federico.navarro', 'federico.navarro@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00011', '2023-10-16', 'weekly', 'active'),
('KMR00012', 3, 1, 'Wilfredo',  'Domingo',   'wilfredo.domingo', 'wilfredo.domingo@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00012', '2023-11-06', 'weekly', 'active'),
('KMR00013', 3, 3, 'Salvador',  'Torres',    'salvador.torres',  'salvador.torres@ghsoftware.com',  '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00013', '2024-01-08', 'weekly', 'active'),
('KMR00014', 3, 3, 'Renato',    'Flores',    'renato.flores',    'renato.flores@ghsoftware.com',    '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00014', '2024-02-12', 'weekly', 'active'),
('KMR00015', 3, 3, 'Eduardo',   'Castillo',  'eduardo.castillo', 'eduardo.castillo@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00015', '2024-03-04', 'weekly', 'active'),
('KMR00016', 3, 3, 'Alfredo',   'Morales',   'alfredo.morales',  'alfredo.morales@ghsoftware.com',  '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00016', '2024-04-15', 'weekly', 'active'),
('KMR00017', 3, 4, 'Nestor',    'Gutierrez', 'nestor.gutierrez', 'nestor.gutierrez@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00017', '2024-05-06', 'weekly', 'active'),
('KMR00018', 3, 4, 'Armando',   'Salazar',   'armando.salazar',  'armando.salazar@ghsoftware.com',  '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00018', '2024-06-10', 'weekly', 'active'),
('KMR00019', 3, 4, 'Gerardo',   'Villar',    'gerardo.villar',   'gerardo.villar@ghsoftware.com',   '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00019', '2024-07-01', 'weekly', 'active'),
('KMR00020', 3, 5, 'Domingo',   'Pascual',   'domingo.pascual',  'domingo.pascual@ghsoftware.com',  '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00020', '2024-08-05', 'weekly', 'active'),
('KMR00021', 3, 5, 'Teodoro',   'Ocampo',    'teodoro.ocampo',   'teodoro.ocampo@ghsoftware.com',   '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00021', '2024-09-09', 'weekly', 'active'),
('KMR00022', 3, 5, 'Isagani',   'Del Rosario', 'isagani.delrosario', 'isagani.delrosario@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00022', '2024-10-14', 'weekly', 'active'),
('KMR00023', 3, 6, 'Crisanto',  'Padilla',   'crisanto.padilla', 'crisanto.padilla@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00023', '2024-11-04', 'weekly', 'active'),
('KMR00024', 3, 6, 'Virgilio',  'Soriano',   'virgilio.soriano', 'virgilio.soriano@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00024', '2024-12-02', 'weekly', 'active'),
('KMR00025', 3, 6, 'Leandro',   'Cabrera',   'leandro.cabrera',  'leandro.cabrera@ghsoftware.com',  '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00025', '2025-01-13', 'weekly', 'active'),
('KMR00026', 3, 7, 'Efren',     'Velasco',   'efren.velasco',    'efren.velasco@ghsoftware.com',    '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00026', '2025-02-03', 'weekly', 'active'),
('KMR00027', 3, 7, 'Rolando',   'Enriquez',  'rolando.enriquez', 'rolando.enriquez@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00027', '2025-03-10', 'weekly', 'active'),
('KMR00028', 3, 7, 'Benigno',   'Trinidad',  'benigno.trinidad', 'benigno.trinidad@ghsoftware.com', '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00028', '2025-04-07', 'weekly', 'active'),
('KMR00029', 3, 8, 'Mariano',   'Lorenzo',   'mariano.lorenzo',  'mariano.lorenzo@ghsoftware.com',  '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00029', '2025-05-05', 'weekly', 'active'),
('KMR00030', 3, 8, 'Celso',     'Manalo',    'celso.manalo',     'celso.manalo@ghsoftware.com',     '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00030', '2025-06-09', 'weekly', 'active'),
('KMR00031', 3, 8, 'Arnulfo',   'Rivera',    'arnulfo.rivera',   'arnulfo.rivera@ghsoftware.com',   '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00031', '2025-07-14', 'weekly', 'active'),
('KMR00032', 3, 1, 'Feliciano', 'Aguilar',   'feliciano.aguilar','feliciano.aguilar@ghsoftware.com','$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00032', '2025-08-11', 'weekly', 'active'),
('KMR00033', 3, 1, 'Honorio',   'Sarmiento', 'honorio.sarmiento','honorio.sarmiento@ghsoftware.com','$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00033', '2025-09-01', 'weekly', 'active'),
('KMR00034', 3, 3, 'Patricio',  'Yumul',     'patricio.yumul',   'patricio.yumul@ghsoftware.com',   '$2y$10$b4VHxJmClysBTelptz/OU.cw71Ks9gOum2WwEYSBlrGlqGuZGKonm', 'KMR00034', '2025-10-06', 'weekly', 'inactive');

-- ---------------- Payroll info for the 30 employees (optional) ----------------
INSERT INTO `employee_payroll_info`
(`user_id`, `daily_rate`, `sss_enabled`, `sss_amount`, `philhealth_enabled`, `philhealth_amount`, `pagibig_enabled`, `pagibig_amount`)
SELECT u.id,
       CASE u.job_role_id
            WHEN 2 THEN 800.00   -- Foreman
            WHEN 4 THEN 700.00   -- Electrician
            WHEN 5 THEN 680.00   -- Plumber
            WHEN 3 THEN 650.00   -- Mason
            WHEN 7 THEN 650.00   -- Tile Setter
            WHEN 8 THEN 620.00   -- Roof Installer
            WHEN 6 THEN 600.00   -- Painter
            ELSE 550.00          -- Labor
       END,
       1, 250.00, 1, 150.00, 1, 100.00
FROM `users` u
WHERE u.role_id = 3
  AND u.employee_id BETWEEN 'KMR00005' AND 'KMR00034'
  AND NOT EXISTS (SELECT 1 FROM `employee_payroll_info` e WHERE e.user_id = u.id);
