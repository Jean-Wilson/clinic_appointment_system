# Clinic Appointment System

A starter clinic appointment scheduling application built with PHP, MySQL, HTML, CSS, JavaScript, Nginx and Docker Compose.

## Features
- Dashboard with patient, doctor, appointment and today's appointment counts
- Patient registration and patient directory
- Doctor registration and doctor directory
- Appointment booking with patient, doctor, date, time and reason
- Appointment status updates: Scheduled, Completed, Cancelled and No-show
- Delete appointments
- Prevents booking the same doctor in the same date/time slot twice
- Sample doctors, patients and appointments included

## Run with Docker
1. Install Docker Desktop and start it.
2. In your WSL terminal, go to the project folder:

   ```bash
   cd /mnt/c/Users/students/Downloads/CarePoint_Clinic_Appointment_System_Updated/clinic_appointment_system
   ```

3. Build the images and create the containers:

   ```bash
   docker compose build
   docker compose create
   ```

4. Start the application:

   ```bash
   docker compose up -d
   docker compose down
   docker compose ps
   ```

5. Open http://localhost:8080

### Tag and share images with Docker Hub
Set `DOCKERHUB_USERNAME` to your Docker Hub username in the same WSL terminal. Sign in, build the images, add a version tag, and push both tags:

```bash

docker login
docker compose build
docker tag clinic_appointment_system-backend:latest igiranezawilson48/clinic_appointment_system-backend:v1.0.0
docker tag clinic_appointment_system-frontend:latest igiranezawilson48/clinic_appointment_system-frontend:v1.0.0

export DOCKERHUB_USERNAME=igiranezawilson48

docker push "$DOCKERHUB_USERNAME/clinic_appointment_system-backend:latest"
docker push "$DOCKERHUB_USERNAME/clinic_appointment_system-backend:v1.0.0"
docker push "$DOCKERHUB_USERNAME/clinic_appointment_system-frontend:latest"
docker push "$DOCKERHUB_USERNAME/clinic_appointment_system-frontend:v1.0.0" 

docker push igiranezawilson48/clinic_appointment_system-backend:v1.0.0
docker push igiranezawilson48/clinic_appointment_system-frontend:v1.0.0
```

On another machine, set the same username, pull the images, and run the application:

```bash

docker compose pull backend frontend
docker compose up -d
```

Other useful commands:

```bash
docker compose logs -f          # View application logs
docker compose stop             # Stop containers without removing them
docker compose start            # Start previously created containers
docker compose down             # Stop and remove containers and network
```

To remove the local database volume as well (this permanently deletes local database data):

```bash
docker compose down -v
```

## Project structure

```text
clinic_appointment_system/
├── backend/
│   ├── database/init.sql
│   ├── public/index.php
│   ├── public/.htaccess
│   ├── src/db.php
│   └── Dockerfile
├── frontend/
│   ├── index.html
│   ├── style.css
│   ├── app.js
│   ├── nginx.conf
│   └── Dockerfile
├── docker-compose.yml
└── README.md
```

## API overview
- `GET /api/health`
- `GET /api/stats`
- `GET, POST /api/patients`
- `GET, POST /api/doctors`
- `GET, POST /api/appointments`
- `PUT /api/appointments/{id}` to replace appointment booking details (patient, doctor, date, time, reason; optional status)
- `PATCH /api/appointments/{id}` to update supplied fields, including status only, e.g. `{"status":"Completed"}`
- `DELETE /api/appointments/{id}` to permanently delete a booking

The appointment list and dashboard statistics reload after booking, editing, status changes and deletion. The edit form includes patient, doctor, date, time, reason and status.

## Database
Database: `clinic_appointments`

Tables:
- `patients`
- `doctors`
- `appointments`

Default local development database credentials are defined in `docker-compose.yml`. Change them before any real deployment.

## Important notes
This is an educational starter project, not a production clinical system. It has no user authentication, role-based access, audit logging, encrypted medical records, backups, or production security hardening. Do not enter real patient medical or identifying information. Add authentication, authorization, privacy safeguards and applicable local compliance controls before real use.
