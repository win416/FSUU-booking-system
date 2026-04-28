# FSUU Dental Clinic Booking System - Project Documentation

---

## 1. Project Overview

### What is the system for?

The **FSUU Dental Clinic Booking System** is a comprehensive web-based appointment management and patient record system for Fr. Saturnino Urios University (FSUU) Dental Clinic. The system streamlines dental service booking, patient management, and clinical operations.

#### Key Purpose:
- **For Students & Staff**: Book dental appointments online with available time slots and services
- **For Dentists**: Manage assigned appointments, patient records, and schedule availability
- **For Admins**: Oversee system operations, user management, reports, and clinic settings

#### Main Features:
- 🗓️ **Online Appointment Booking** - Students/staff can request dental service appointments
- 📋 **Real-time Slot Management** - Automatic conflict detection and availability tracking
- 💼 **Patient Records** - Dentists can store medical history and treatment notes
- 👥 **Multi-role Access** - Different dashboards for patients, dentists, and administrators
- 📧 **Email Notifications** - Appointment reminders and status updates
- 📊 **Reporting & Analytics** - Admin can generate reports on clinic usage
- 🔐 **Authentication** - Secure login with email verification and Google OAuth support
- 📱 **Responsive Design** - Works on desktop and mobile devices

#### Services Offered:
1. **Consultation** - Professional dental check-ups and preventive care (30 min)
2. **Tooth Extraction** - Removing damaged or decayed teeth (30 min)
3. **Oral Prophylaxis** - Professional teeth cleaning and plaque removal (30 min)
4. **Permanent Tooth Filling** - Restoring damaged teeth with filling materials (30 min)

---

## 2. Tech Stack

### Frontend
- **HTML5** - Semantic markup and structure
- **CSS3** - Styling with custom stylesheets (`index.css`, `admin-dashboard.css`, `admin-appointments.css`, `dentist-dashboard.css`)
- **Bootstrap 5.1.3** - Responsive UI framework (CDN)
- **Bootstrap Icons 1.8.1** - Icon library for UI elements
- **JavaScript (Vanilla)** - Client-side interactivity and DOM manipulation
- **No JavaScript Framework** - Lightweight vanilla JS implementation

### Backend
- **PHP 8.2.12** - Server-side scripting language
- **Apache Web Server** - HTTP server (via XAMPP)
- **Session Management** - PHP native sessions with security best practices
- **PHPMailer 6.9** - Email delivery (via Composer)

### Database
- **MariaDB 10.4.32** - Relational database
- **MySQL 5.7+ Compatible** - Standard SQL support

### Development & Deployment Environment
- **XAMPP** - Local development stack (PHP, Apache, MySQL/MariaDB)
- **Composer** - PHP dependency management
- **Git** - Version control
- **Localhost** - Development environment (http://localhost/FSUU-booking-system-1)

### Email Service
- **PHPMailer** - SMTP-based email delivery
- **Gmail SMTP** - Email provider (configured in config.secrets.php)

### Authentication Methods
- **Native PHP Sessions** - Session-based authentication
- **Email Verification** - OTP-based email verification
- **Google OAuth 2.0** - Alternative login option
- **bcrypt Password Hashing** - Secure password storage

---

## 3. System Architecture

### High-Level Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENT LAYER (Frontend)                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐  │
│  │   Patient UI    │  │   Dentist UI    │  │   Admin UI      │  │
│  │  (book-appt,    │  │ (appointments,  │  │ (user-mgmt,     │  │
│  │   dashboard,    │  │  my-schedule,   │  │  reports,       │  │
│  │   messages)     │  │  patient-rec)   │  │  settings)      │  │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘  │
│           │                    │                    │             │
│           └────────────────────┼────────────────────┘             │
│                                │                                  │
│                    [Bootstrap + Vanilla JS]                       │
│                                │                                  │
└────────────────────────────────┼──────────────────────────────────┘
                                 │
                    HTTP Requests (JSON/Form Data)
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER (Backend - PHP)             │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    API Layer (/api)                     │   │
│  │  ┌──────────────────────────────────────────────────┐   │   │
│  │  │  • book-appointment.php                          │   │   │
│  │  │  • get-slots.php                                 │   │   │
│  │  │  • cancel-appointment.php                        │   │   │
│  │  │  • update-appointment.php                        │   │   │
│  │  │  • dentist-appointments.php                      │   │   │
│  │  │  • dentist-patient-records.php                   │   │   │
│  │  │  • admin-reports.php                             │   │   │
│  │  │  • notifications.php                             │   │   │
│  │  │  • messages.php                                  │   │   │
│  │  └──────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Business Logic Layer (/includes)           │   │
│  │  ┌──────────────────────────────────────────────────┐   │   │
│  │  │  • session.php (SessionManager class)            │   │   │
│  │  │  • db_connection.php (Database connection)       │   │   │
│  │  │  • config.php (Configuration)                    │   │   │
│  │  │  • appointment_reminders.php (Email reminders)   │   │   │
│  │  └──────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Page Controllers (/patient, /dentist, /admin)   │
│  │  ┌──────────────────────────────────────────────────┐   │   │
│  │  │  • dashboard.php, my-appointments.php            │   │   │
│  │  │  • profile.php, notifications.php                │   │   │
│  │  │  • messages.php, history.php                     │   │   │
│  │  └──────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │            Authentication Layer (/auth)                 │   │
│  │  ┌──────────────────────────────────────────────────┐   │   │
│  │  │  • login.php, register.php, logout.php           │   │   │
│  │  │  • verify.php, google_auth.php                   │   │   │
│  │  └──────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
└────────────────────────────────┬──────────────────────────────────┘
                                 │
                    Database Queries (MySQLi/PDO)
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                   DATA LAYER (Database - MariaDB)                │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Database: fsuu_dental_booking                                   │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    Core Tables                          │   │
│  │  • users                          (User accounts)       │   │
│  │  • services                       (Dental services)     │   │
│  │  • appointments                   (Bookings)           │   │
│  │  • dentist_profiles               (Dentist info)       │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │               Supporting Tables                         │   │
│  │  • medical_info                   (Patient medical)     │   │
│  │  • messages                       (Internal messages)   │   │
│  │  • notifications                  (Notification queue)  │   │
│  │  • blocked_schedules              (Dentist holidays)   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │               Tracking & Audit Tables                   │   │
│  │  • dentist_appointment_assignments (Assignment history) │   │
│  │  • dentist_patient_records        (Treatment notes)     │   │
│  │  • appointment_reminders          (Email log)           │   │
│  │  • audit_log                      (System activity)     │   │
│  │  • system_settings                (Configuration)       │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘

                    ┌─────────────────────────────┐
                    │    External Services        │
                    ├─────────────────────────────┤
                    │  📧 Gmail SMTP (Email)      │
                    │  🔐 Google OAuth 2.0        │
                    └─────────────────────────────┘
```

### Data Flow Example: Booking an Appointment

```
Patient                          Backend                        Database
   │                                │                              │
   ├──── POST /api/book-appointment.php ────────────────────────────┤
   │     (service_id, date, time, consent)                          │
   │                                                                 │
   │                      ┌─ Validate request                       │
   │                      ├─ Check availability ──────── Query      │
   │                      │   appointments, services                │
   │                      ├─ Insert new appointment ──── INSERT     │
   │                      ├─ Assign dentist ────────── UPDATE       │
   │                      └─ Send notifications ────────── INSERT   │
   │                                                                 │
   │◄─────────── JSON Response (success/error) ─────────────────────┤
   │
   ├──── Auto: Trigger appointment_reminders.php ──────────────────┤
   │                      ├─ Query upcoming appointments
   │                      └─ Send email reminders via SMTP
   │
   └──── Patient receives appointment confirmation email
```

---

## 4. Database Schema

### Complete Entity-Relationship Diagram

```
┌─────────────────────┐
│      USERS          │
├─────────────────────┤
│ user_id (PK)        │◄─────────────────┐
│ fsuu_id             │                  │
│ first_name          │                  │
│ last_name           │                  │
│ email               │                  │
│ password            │                  │
│ contact_number      │                  │
│ program             │                  │
│ verification_code   │                  │
│ is_verified         │                  │
│ role                │                  │
│ is_active           │                  │
│ profile_picture     │                  │
│ created_at          │                  │
└─────────────────────┘                  │
        │                                 │
        ├──────────────────┐              │
        │                  │              │
        ▼                  ▼              │
   ┌──────────┐      ┌──────────────────────┐
   │MEDICAL   │      │DENTIST_PROFILES      │
   │_INFO     │      ├──────────────────────┤
   ├──────────┤      │dentist_id (FK/PK)    │
   │user_id   │◄─────┤specialization        │
   │(FK/PK)   │      │digital_signature_path│
   │allergies │      │created_at            │
   │medical   │      │updated_at            │
   │_cond     │      └──────────────────────┘
   │medicatio │
   │ns        │      ┌──────────────────┐
   │emergency │◄────►│DENTIST_PATIENT   │
   │_contact  │      │_RECORDS          │
   │_*        │      ├──────────────────┤
   │last_update       │record_id (PK)    │
   └──────────┘      │dentist_id (FK)   │
                     │patient_id (FK)   │
                     │appointment_id(FK)│
                     │treatment_notes   │
                     │prescription      │
                     │created_at        │
                     │updated_at        │
                     └──────────────────┘


        ┌──────────────────┐
        │    SERVICES      │
        ├──────────────────┤
        │service_id (PK)   │
        │service_name      │
        │description       │
        │duration_minutes  │
        │is_active         │
        └─────────┬────────┘
                  │
                  │ (1:M)
                  │
        ┌─────────▼────────────────────┐
        │    APPOINTMENTS              │
        ├──────────────────────────────┤
        │appointment_id (PK)           │
        │user_id (FK) ─────────────────┤────► USERS
        │service_id (FK) ──────────────┤────► SERVICES
        │appointment_date              │
        │appointment_time              │
        │notes                         │
        │consent_agreed                │
        │status (enum)                 │
        │cancellation_reason           │
        │cancelled_at                  │
        │created_at                    │
        └─────────┬──────────────────────┘
                  │
                  │ (1:M)
                  │
        ┌─────────▼──────────────────────┐
        │DENTIST_APPOINTMENT_ASSIGNMENTS │
        ├────────────────────────────────┤
        │assignment_id (PK)              │
        │appointment_id (FK) ────────────┤
        │dentist_id (FK) ────────────────┤────► USERS (Dentist)
        │checked_in_at                   │
        │completed_at                    │
        │created_at                      │
        └────────────────────────────────┘


┌─────────────────────────────┐
│  BLOCKED_SCHEDULES          │
├─────────────────────────────┤
│block_id (PK)                │
│block_date                   │
│start_time                   │
│end_time                     │
│reason                       │
│is_full_day                  │
│created_by (FK) ─────────────┤────► USERS (Dentist/Admin)
└─────────────────────────────┘


┌──────────────────────────┐
│    APPOINTMENT_REMINDERS │
├──────────────────────────┤
│reminder_id (PK)          │
│appointment_id (FK) ──────┤────► APPOINTMENTS
│reminder_hours            │
│delivery_status           │
│error_message             │
│sent_at                   │
└──────────────────────────┘


┌──────────────────────┐         ┌──────────────────────┐
│      MESSAGES        │         │  NOTIFICATIONS       │
├──────────────────────┤         ├──────────────────────┤
│message_id (PK)       │         │notification_id (PK) │
│sender_id (FK) ───────┼────────►│user_id (FK) ────────┤
│receiver_id (FK) ─────┤         │type                  │
│subject               │         │subject               │
│message_text          │         │message               │
│is_read               │         │status                │
│created_at            │         │is_read               │
└──────────────────────┘         │created_at            │
                                 │sent_at               │
                                 └──────────────────────┘


┌────────────────────┐
│  AUDIT_LOG         │
├────────────────────┤
│log_id (PK)         │
│user_id (FK) ───────┤────► USERS
│action              │
│description         │
│ip_address          │
│created_at          │
└────────────────────┘


┌────────────────────────┐
│  SYSTEM_SETTINGS       │
├────────────────────────┤
│setting_key (PK)        │
│setting_value           │
│                        │
│ • dentist_*_weekday    │
│   _start/_end          │
│ • dentist_*_saturday   │
│   _start/_end          │
│ • max_bookings_per_day │
│ • reminder_hours       │
│ • reminder_last_run_at │
│ • weekday/saturday     │
│   _start/_end          │
│ • wednesday_start/_end │
└────────────────────────┘
```

### Table Relationships Summary

| Table | Relationship | Connected Table | Multiplicity |
|-------|--------------|-----------------|--------------|
| `users` | 1 → M | `medical_info` | One user has one medical info |
| `users` | 1 → M | `appointments` | One user books many appointments |
| `users` | 1 → 1 | `dentist_profiles` | One dentist has one profile |
| `services` | 1 → M | `appointments` | One service has many appointments |
| `appointments` | 1 → M | `dentist_appointment_assignments` | One appointment assigned to one dentist |
| `appointments` | 1 → M | `dentist_patient_records` | One appointment may have treatment records |
| `appointments` | 1 → M | `appointment_reminders` | One appointment may have multiple reminders |
| `users` | 1 → M | `messages` (sender) | One user sends many messages |
| `users` | 1 → M | `messages` (receiver) | One user receives many messages |
| `users` | 1 → M | `notifications` | One user gets many notifications |
| `users` | 1 → M | `audit_log` | One user's actions logged |

### Key Data Characteristics

**User Roles:**
- `student` - FSUU students (can book appointments)
- `staff` - FSUU staff members (can book appointments)
- `dentist` - Dental professionals (manage appointments & records)
- `admin` - System administrators (manage system & reports)

**Appointment Status Values:**
- `pending` - Awaiting approval from dentist/admin
- `approved` - Confirmed appointment
- `completed` - Service provided
- `cancelled` - Patient or dentist cancelled
- `no_show` - Patient didn't attend

**Notification Types:**
- `email` - Email-based notifications
- Can be extended for SMS, push notifications

---

## 5. System Walkthrough

### 5.1 User Journey: New Patient Booking an Appointment

#### Step 1: Registration
```
Landing Page (index.php)
    ↓
User clicks "Register" button
    ↓
Registration Form (auth/register.php)
    • Enter: FSUU ID, Name, Email, Password, Phone, Program
    ↓
Backend validates and creates user account
    • Password hashed with bcrypt
    • Email verification code generated
    ↓
Verification Email sent (via PHPMailer/SMTP)
    ↓
Patient verifies email via link or code
    ↓
Account activated → Redirects to Login
```

#### Step 2: Login
```
Login Page (auth/login.php)
    ↓
User enters Email & Password
    ↓
Backend authenticates:
    • Check if email exists
    • Verify password hash matches
    • Check if account is verified and active
    ↓
Session created (SessionManager::login)
    ↓
User redirected to dashboard based on role:
    • Dentist → dentist/dashboard.php
    • Admin → admin/dashboard.php
    • Patient → patient/dashboard.php
```

#### Step 3: Booking an Appointment
```
Patient Dashboard (patient/dashboard.php)
    ↓
Patient navigates to "Book Appointment"
    • Clicks "My Appointments" → "New Appointment"
    ↓
Appointment Booking Form
    • Select Service (Consultation, Extraction, etc.)
    • Select Preferred Date
    • Select Time Slot
    • Add Medical Notes (optional)
    • Agree to Consent
    ↓
Frontend JavaScript:
    • Calls API: GET /api/get-slots.php
        - Parameters: service_id, appointment_date
        - Backend checks:
            * Blocked schedules (holidays)
            * Dentist working hours
            * Available appointments
            * User's max bookings per day limit
        - Returns: Available time slots
    ↓
Patient selects time slot
    ↓
Form Submission (POST to /api/book-appointment.php)
    • Parameters: user_id, service_id, date, time, medical_notes, consent
    ↓
Backend Processing:
    1. Validate all required fields
    2. Verify user is authenticated
    3. Check appointment doesn't conflict
    4. Assign available dentist
    5. Create appointment record (status: "pending")
    6. Create audit log entry
    7. Send notification email to admin
    8. Send notification email to patient
    ↓
Response: Appointment ID & confirmation details
    ↓
Patient sees confirmation popup
    • Appointment Number: APT-2024-001
    • Service: Consultation
    • Date & Time: Apr 17, 2026 at 9:00 AM
    • Assigned Dentist: Dr. Win Bonbon
    ↓
Notification email received by patient
```

### 5.2 Dentist Workflow: Managing Appointments

#### Step 1: Login & Dashboard
```
Dentist Portal (dentist/dashboard.php)
    ↓
Dashboard shows:
    • Today's Appointments (count, time slots)
    • Pending Appointments (awaiting approval)
    • Patient Statistics
    • Recent Messages
    • Notifications Badge
```

#### Step 2: Reviewing Appointments
```
Navigate to "Appointments" (dentist/appointments.php)
    ↓
View appointment list filtered by:
    • Date range
    • Status (pending, approved, completed)
    • Service type
    ↓
Dentist reviews appointment details:
    • Patient name, contact
    • Service requested
    • Appointment date & time
    • Patient medical info (allergies, conditions, medications)
    • Previous treatment notes
    ↓
Dentist can:
    • APPROVE appointment
        → Status changes to "approved"
        → Email sent to patient confirming appointment
    ↓
    • REJECT appointment
        → Requires cancellation reason
        → Email sent to patient with reason
    ↓
    • RESCHEDULE appointment
        → Suggest alternative date/time
        → Patient gets notification to confirm
```

#### Step 3: Managing Schedule Availability
```
Dentist Portal → "My Schedule" (dentist/my-schedule.php)
    ↓
View/Edit Working Hours:
    • Set weekday hours (e.g., Monday-Friday 8AM-12PM)
    • Set Saturday hours (e.g., 1PM-5PM)
    • Define closed days
    ↓
Block Time Off:
    • Select dates for leaves, seminars, holidays
    • System prevents new appointment bookings during blocked times
    ↓
Changes saved to system_settings table:
    • dentist_[id]_weekday_start/end
    • dentist_[id]_saturday_start/end
```

#### Step 4: Completing Appointments
```
On Appointment Day:
    ↓
Dentist views "Appointments" section
    ↓
Check-in process:
    • Click "Check In" button
    • System records checked_in_at timestamp
    • Appointment status: "in_progress"
    ↓
During Treatment:
    • Dentist can view patient medical history
    • Access previous treatment notes
    ↓
After Treatment:
    • Click "Complete Appointment"
    • Enter Treatment Notes:
        - What was done
        - Findings
        - Recommendations
        - Prescription (if needed)
    ↓
System updates:
    • appointment status → "completed"
    • completed_at timestamp
    • dentist_patient_records entry created
    ↓
Patient notification: "Appointment completed - Your record saved"
```

#### Step 5: Viewing Patient Records
```
Dentist Portal → "My Patients" (dentist/my-patients.php)
    ↓
View list of patients treated:
    • Patient name, ID
    • Appointment history
    • Medical information
    ↓
Click on patient → Patient details page
    • Medical info: allergies, conditions, medications
    • Emergency contacts
    • Complete appointment history
    • All treatment records and notes
    • Prescriptions given
```

### 5.3 Admin Workflow: System Management

#### Step 1: Admin Dashboard
```
Admin Portal (admin/dashboard.php)
    ↓
Overview displays:
    • Total users (students, staff, dentists)
    • Appointment statistics
    • System health status
    • Recent activities
```

#### Step 2: User Management
```
Admin → "Users" (admin/users.php)
    ↓
View all system users:
    • Filter by role (student, staff, dentist, admin)
    • Search by name/email/ID
    • View user details
    ↓
Admin can:
    • ACTIVATE/DEACTIVATE user accounts
    • VERIFY user emails
    • ASSIGN/CHANGE user roles
    • VIEW audit logs for user activities
    ↓
Patient Management (admin/patient.php):
    • View patient medical information
    • Manage patient profiles
    • Update emergency contacts
```

#### Step 3: Appointment Management
```
Admin → "Appointments" (admin/appointments.php)
    ↓
View system-wide appointments:
    • Filter by status, date, service, dentist
    • Export appointment data
    ↓
Can perform:
    • APPROVE pending appointments
    • CANCEL appointments (with reason)
    • REASSIGN dentists
    • UPDATE appointment times
    ↓
Bulk Actions:
    • Generate reports for date range
    • Export to CSV/PDF
```

#### Step 4: System Settings
```
Admin → "Settings" (admin/settings.php)
    ↓
Configure clinic operations:
    
    Appointment Rules:
        • Max bookings per patient per day
        • Minimum hours before cancellation allowed
        • Session timeout duration
        
    Clinic Hours:
        • Default weekday hours (e.g., 8 AM - 9 PM)
        • Saturday hours (e.g., 8 AM - 4 PM)
        • Wednesday special hours
        • Individual dentist schedules
        
    Email Settings:
        • SMTP server configuration
        • Email templates
        • Reminder timing (e.g., 24 hours before)
    
    Holiday/Blocked Dates:
        • Define system-wide blocked dates
        • Dentists can set individual blocked dates
    ↓
Changes applied to system_settings table
```

#### Step 5: Reporting & Analytics
```
Admin → "Reports" (admin/reports.php)
    ↓
Available Reports:
    
    1. Appointment Summary
        • Total appointments by period
        • Appointments by service type
        • Appointments by dentist
        • Status breakdown (pending/approved/completed/cancelled)
    
    2. Patient Analytics
        • New patients registered
        • Most active patients
        • Patient demographics
        • No-show rate analysis
    
    3. Dentist Performance
        • Appointments completed per dentist
        • Average appointment duration
        • Patient feedback/ratings (if implemented)
        • Utilization rates
    
    4. System Activity
        • Login activities
        • User account changes
        • Appointment modifications
        • System errors
    ↓
Export Options:
    • Download as CSV
    • Download as PDF
    • Email reports automatically
```

#### Step 6: Messaging & Notifications
```
Admin → "Messages" (admin/messages.php)
    ↓
Send messages to:
    • Individual dentists
    • Individual patients
    • All users
    ↓
Use cases:
    • Notify of schedule changes
    • Send appointment reminders
    • Broadcast clinic announcements

Admin → "Notifications" (admin/notifications.php)
    ↓
Monitor notification queue:
    • View pending notifications
    • Retry failed notifications
    • Check email delivery logs
```

### 5.4 Key Features in Action

#### Feature: Automated Email Reminders
```
Appointment Created (status: approved)
    ↓
Cron Job / Scheduled Task:
    Runs: appointment_reminders.php
    Checks: Appointments 24 hours from now
    ↓
For each upcoming appointment:
    1. Query appointment details
    2. Get patient email
    3. Compose reminder email
    4. Send via PHPMailer (Gmail SMTP)
    ↓
If successful:
    • appointment_reminders table entry created
    • delivery_status = "sent"
    • Patient receives: "Reminder: Dental appointment tomorrow at 9:00 AM"
    
If failed:
    • delivery_status = "failed"
    • error_message logged
    • Admin notified for manual follow-up
```

#### Feature: Real-time Slot Availability
```
Patient selects appointment date
    ↓
JavaScript calls: GET /api/get-slots.php?date=2026-04-17&service=1
    ↓
Backend logic:
    1. Check if date is blocked (holidays, full-day events)
        → Skip if blocked
    
    2. For each available dentist:
        a. Get dentist's working hours for that day
        b. Get dentist's existing appointments
        c. Get dentist's blocked schedules
        d. Calculate free slots (service_duration = 30 min)
    
    3. Check patient booking limit:
        → If max_bookings_per_day = 1, patient can only book 1 per day
    
    4. Return available slots
    ↓
Frontend displays available times:
    • 9:00 AM (with Dr. Win)
    • 9:30 AM (with Dr. Jeo)
    • 2:00 PM (with Dr. Win)
    • etc.
    
    ↓
Patient clicks desired slot
```

#### Feature: Multi-role Access Control
```
User logs in
    ↓
Backend creates session:
    • $_SESSION['user_id']
    • $_SESSION['role']
    • $_SESSION['email']
    ↓
On each page load:
    SessionManager checks user role:
    
    if (SessionManager::isPatient()) {
        // Show patient dashboard features
        // Hide admin/dentist features
    }
    elseif (SessionManager::isDentist()) {
        // Show dentist dashboard features
        // Hide patient/admin features
    }
    elseif (SessionManager::isAdmin()) {
        // Show admin dashboard features
        // All access
    }
    else {
        // Not logged in → Redirect to login
    }
    ↓
This ensures:
    • Patients cannot access admin/dentist pages
    • Dentists cannot modify system settings
    • Only admins see user management pages
```

### 5.5 Data Flow: From User to Database

```
Example: Patient books appointment

1. USER ACTION (Frontend - index.html)
   Patient fills form:
   - Service: "Consultation" (id=1)
   - Date: "2026-04-17"
   - Time: "09:00"
   
2. CLIENT-SIDE VALIDATION (JavaScript)
   - Check date not in past
   - Check time selected
   - Check consent checkbox marked
   
3. API REQUEST (HTTP POST)
   POST /api/book-appointment.php
   Body: {
     "service_id": 1,
     "appointment_date": "2026-04-17",
     "appointment_time": "09:00",
     "notes": "Regular checkup",
     "consent_agreed": 1
   }
   
4. SERVER-SIDE PROCESSING (PHP)
   
   a) Authenticate:
      - Check session exists
      - Verify user_id
      
   b) Validate Input:
      - service_id exists and is_active = 1
      - date is valid and not blocked
      - time is in clinic hours
      - patient has medical info
      
   c) Business Logic:
      - Query available dentists
      - Check their schedules
      - Select dentist (round-robin or by availability)
      - Check patient appointment limit
      
   d) Database Queries:
      
      SELECT * FROM services WHERE service_id = 1
      
      SELECT * FROM blocked_schedules 
      WHERE block_date = '2026-04-17'
      
      SELECT * FROM appointments 
      WHERE appointment_date = '2026-04-17' AND status != 'cancelled'
      
      SELECT * FROM dentist_profiles 
      WHERE dentist_id = 12
      
      INSERT INTO appointments (
        user_id, service_id, appointment_date, 
        appointment_time, notes, consent_agreed, 
        status, created_at
      ) VALUES (5, 1, '2026-04-17', '09:00', '...', 1, 'pending', NOW())
      
      INSERT INTO dentist_appointment_assignments (
        appointment_id, dentist_id, created_at
      ) VALUES (25, 12, NOW())
      
      INSERT INTO notifications (
        user_id, type, subject, message, status, created_at
      ) VALUES 
      (5, 'email', 'Appointment Confirmed', '...', 'pending', NOW()),
      (12, 'email', 'New Appointment Assigned', '...', 'pending', NOW())
      
      INSERT INTO audit_log (
        user_id, action, description, ip_address, created_at
      ) VALUES (5, 'book_appointment', 'Booked appointment #25...', '127.0.0.1', NOW())
   
5. RESPONSE (HTTP JSON)
   {
     "success": true,
     "appointment_id": 25,
     "message": "Appointment booked successfully",
     "details": {
       "date": "2026-04-17",
       "time": "09:00",
       "service": "Consultation",
       "dentist": "Dr. Win Bonbon"
     }
   }
   
6. DATABASE STATE UPDATED
   
   appointments table:
   ├── appointment_id: 25
   ├── user_id: 5
   ├── service_id: 1
   ├── appointment_date: 2026-04-17
   ├── appointment_time: 09:00
   ├── status: pending
   └── created_at: 2026-04-17 10:15:23
   
   dentist_appointment_assignments table:
   ├── assignment_id: 12
   ├── appointment_id: 25
   ├── dentist_id: 12
   └── created_at: 2026-04-17 10:15:23
   
   notifications table:
   ├── 2 new rows (patient + dentist)
   └── status: pending (waiting for email daemon to send)
   
   audit_log table:
   ├── log_id: 140
   ├── user_id: 5
   ├── action: book_appointment
   └── created_at: 2026-04-17 10:15:23

7. EMAIL NOTIFICATIONS
   Daemon/Cron triggers:
   - Patient email: "Your appointment confirmed for April 17, 2026"
   - Dentist email: "New appointment assigned for April 17, 2026 at 9:00 AM"
   
   notifications.status → "sent"
   appointment_reminders table updated
```

---

## 6. Environment Setup & Configuration

### Required Configuration Files
- `includes/config.php` - Main configuration
- `includes/config.secrets.php` - Sensitive data (gitignored)
- `.htaccess` - Apache URL rewriting

### Database Initialization
1. Create database: `fsuu_dental_booking`
2. Import schema: `schema.sql`
3. Tables auto-created with necessary indexes and constraints

### Deployment Checklist
- [ ] Set up XAMPP with PHP 8.2+, MariaDB 10.4+
- [ ] Configure `config.secrets.php` with SMTP credentials
- [ ] Configure Google OAuth credentials
- [ ] Run `composer install` for PHPMailer
- [ ] Set appropriate file permissions
- [ ] Test email functionality
- [ ] Verify all API endpoints
- [ ] Test appointment booking flow
- [ ] Perform security audit

---

**Last Updated:** April 2026  
**Version:** 1.0  
**Status:** Production
