CREATE DATABASE IF NOT EXISTS clinic_appointments;
USE clinic_appointments;

CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30),
  email VARCHAR(150),
  date_of_birth DATE NULL,
  gender VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  specialty VARCHAR(100),
  phone VARCHAR(30),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  reason TEXT,
  status ENUM('Scheduled','Completed','Cancelled','No-show') NOT NULL DEFAULT 'Scheduled',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointment_patient FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointment_doctor FOREIGN KEY(doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT,
  UNIQUE KEY unique_doctor_slot(doctor_id, appointment_date, appointment_time)
);

INSERT INTO doctors(full_name,specialty,phone) VALUES
('Dr. Alice Uwimana','General Practitioner','0780000001'),
('Dr. Jean de Dieu Habimana','Pediatrics','0780000002'),
('Dr. Marie Claire Mukamana','Dentistry','0780000003');

INSERT INTO patients(full_name,phone,email,date_of_birth,gender) VALUES
('Eric Niyonzima','0781111111','eric@example.com','1998-04-12','Male'),
('Aline Mukamana','0782222222','aline@example.com','2001-09-03','Female');

INSERT INTO appointments(patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES
(1,1,CURDATE(),'09:00:00','General check-up','Scheduled'),
(2,2,DATE_ADD(CURDATE(), INTERVAL 1 DAY),'10:30:00','Child wellness consultation','Scheduled');
