# Smart Timetable Management System

A comprehensive timetable management system designed for universities. This project consists of two main applications:

1. **Admin Web Panel** - PHP-based web interface for administrators to manage schedules, students, modules, and exams
2. **Student Mobile App** - Flutter mobile application for students to view timetables, track study sessions, and manage their academic schedule

## 🚀 Live Demo

Try the applications online:

- **Admin Panel (Web):** [https://web-production-f8792.up.railway.app/](https://web-production-f8792.up.railway.app/)
  - Login required for administrators
  - Manage students, modules, lecturers, venues, and schedules
  
- **Flutter App (Web):** [https://web-production-ffbb.up.railway.app/](https://web-production-ffbb.up.railway.app/)
  - Student login with student number and password
  - View timetables and manage study plans

## 📦 Repository

- **GitHub:** [https://github.com/ReginalThembaubisi/smart-timetable-system](https://github.com/ReginalThembaubisi/smart-timetable-system)
  - View source code
  - Report issues
  - Contribute to the project

## 📋 Overview

This system helps universities efficiently manage class schedules and academic information. Students can access their personalized timetables on mobile devices, while administrators use a web-based interface to create schedules, manage resources, and handle all administrative tasks.

## ✨ Features

### 📱 For Students (Mobile App)
- **Secure Login** - Authenticate with student number and password
- **Timetable View** - Access daily and weekly class schedules
- **Module Management** - View all enrolled modules and course details
- **Study Planning** - Create and track personalized study sessions
- **Study Timer** - Built-in timer to track study time
- **Exam Schedules** - View upcoming exams and receive notifications
- **Lecturer Test Alerts** - Receive immediate, 7-day, and 1-day reminders for lecturer-published tests
- **Offline Support** - Access data offline after initial sync

### 🖥️ For Administrators (Web Interface)
- **Student Management** - Add, edit, and view all student information
- **Module Management** - Create courses and assign them to programs
- **Lecturer Management** - Add and manage lecturer details
- **Lecturer Planner** - View lecturer-specific timetables and get suggested low-conflict test windows
- **Lecturer Login Management** - Create lecturer login identifiers and credentials
- **Venue Management** - Add classrooms and location information
- **Schedule Creation** - Create timetables, assign lecturers and venues
- **Exam Management** - Schedule exams and send notifications to students
- **Assessment Coordination** - Publish lecturer tests with shared-course conflict awareness
- **Bulk Operations** - Import/export large amounts of data efficiently
- **Search & Filter** - Quickly find any information in the system

## 🛠️ Tech Stack

### Frontend
- **Flutter** - Cross-platform mobile app framework
- **Dart** - Programming language for Flutter

### Backend
- **PHP** - Server-side scripting language
- **MySQL** - Relational database management system
- **REST APIs** - Communication between mobile app and server

### Development Tools
- **XAMPP** - Local development environment (Apache, MySQL, PHP)
- **Composer** - PHP dependency manager
- **PDO** - PHP Data Objects for database access

## 📁 Project Structure

```
smart-timetable-system/
├── admin/                    # Backend PHP files and web interface
│   ├── config.php           # Database configuration
│   ├── api/                 # API endpoints for mobile app
│   └── ...                  # Admin web interface files
└── smart_timetable_application/  # Flutter mobile app
    ├── lib/
    │   ├── config/          # App configuration
    │   ├── models/          # Data models
    │   ├── screens/         # App screens
    │   └── services/        # API services
    └── ...
```

## 🚀 Quick Start Guide

### Prerequisites

Before you begin, ensure you have the following installed:
- **XAMPP** (includes Apache, MySQL, and PHP)
- **Flutter SDK** (latest stable version)
- **Android Studio** or **VS Code** (with Flutter extensions)
- **Composer** (PHP package manager)
- **Git** (for cloning the repository)

### Installation Steps

#### 1. Database Setup
1. Start XAMPP and ensure MySQL is running
2. Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
3. Create a new database or use existing one
4. Import the `database_setup.sql` file to create all required tables
   - File location: [`database_setup.sql`](https://github.com/ReginalThembaubisi/smart-timetable-system/blob/main/admin/database_setup.sql)
   - Or use the file in the repository root: `admin/database_setup.sql`

#### 2. Backend Setup (Admin Panel)
1. Copy the `admin` folder to your XAMPP `htdocs` directory
   - Default path: `C:\xampp\htdocs\admin`
2. Open `admin/config.php` and verify database settings:
   ```php
   // Default XAMPP settings usually work:
   // Host: localhost
   // Username: root
   // Password: (empty)
   // Database: smart_timetable
   ```
3. Install PHP dependencies (if any):
   ```bash
   cd admin
   composer install
   ```
4. Access the admin panel at: `http://localhost/admin`

#### 3. Mobile App Setup (Flutter)
1. Open the Flutter project in Android Studio or VS Code
2. Install dependencies:
   ```bash
   flutter pub get
   ```
3. Configure API endpoint in `lib/config/app_config.dart`:
   ```dart
   // For Android Emulator:
   static const String apiBaseUrl = 'http://10.0.2.2/admin';
   
   // For Physical Device (replace with your computer's IP):
   static const String apiBaseUrl = 'http://192.168.1.XXX/admin';
   
   // For Web:
   static const String apiBaseUrl = 'http://localhost/admin';
   ```
4. Run the app:
   ```bash
   flutter run
   ```
5. Run the lecturer app entrypoint (separate third app flow):
   ```bash
   flutter run -t lib/main_lecturer.dart
   ```
6. Run Android app flavors as separate installable apps:
   ```bash
   # Student app (default package)
   flutter run --flavor student -t lib/main.dart

   # Lecturer app (separate package/app on device)
   flutter run --flavor lecturer -t lib/main_lecturer.dart
   ```
7. Build release APKs for both apps:
   ```bash
   flutter build apk --release --flavor student -t lib/main.dart
   flutter build apk --release --flavor lecturer -t lib/main_lecturer.dart
   ```
8. Configure release signing:
   - Copy `smart_timetable_application/android/key.properties.example` to `smart_timetable_application/android/key.properties`
   - Put your keystore file in `smart_timetable_application/android/keystore/`
   - Update `key.properties` with real passwords/alias

## 🔌 API Endpoints

The Flutter mobile app communicates with the backend through these REST API endpoints:

### Authentication
- `POST student_login_api.php` - Student login authentication
- `POST lecturer_login_api.php` - Lecturer login authentication

### Timetable
- `GET get_student_timetable.php?student_id={id}` - Retrieve student's class schedule
- `GET get_student_exam_timetable.php?student_id={id}` - Retrieve exam schedule
- `GET get_lecturer_timetable.php?lecturer_id={id}` - Retrieve lecturer's own timetable

### Modules
- `GET student_modules_api.php?student_id={id}` - Get modules enrolled by student
- `GET fetch_all_modules.php` - Get all available modules in the system

### Lecturer Assessment Planning
- `POST create_lecturer_assessment.php` - Publish lecturer test and queue student notifications
- `GET get_shared_assessment_calendar.php?module_id={id}` - View shared-cohort exams/tests
- `GET process_assessment_notifications.php` - Mark due D-7 / D-1 notifications as sent (for scheduled runner)

### Profile Management
- `POST update_student_profile.php` - Update student information
- `POST change_password_api.php` - Change student password

> **Note:** Replace `{id}` with the actual student ID in the URL parameters.

## 🗄️ Database Schema

The system uses MySQL database with the following main tables:

| Table Name | Description |
|------------|-------------|
| `students` | Stores student information (name, student number, contact details) |
| `modules` | Course modules and their details |
| `sessions` | Class timetable entries (time, day, module, lecturer, venue) |
| `lecturers` | Lecturer information and contact details |
| `venues` | Classroom and location information |
| `exams` | Exam schedules and details |
| `student_modules` | Junction table linking students to their enrolled modules |

> Import `database_setup.sql` to create all tables with proper relationships and constraints.

## 📖 About This Project

This project was developed as part of the ICT Application Development course at the University of Mpumalanga. It demonstrates full-stack development skills including:

- Database design and management
- RESTful API development
- Web application development (PHP)
- Mobile application development (Flutter)
- System integration and architecture

The project showcases a complete end-to-end solution for managing university timetables, combining web and mobile technologies to serve both administrators and students.

## 📸 Screenshots

### Online Gallery
View screenshots of both applications: [View Screenshots Gallery on Imgur](https://imgur.com/a/CWDSwa3)

### Local Screenshots
Screenshots are also available in the repository:
- **Admin Panel Screenshots:** [`/screenshots/admin-*.png`](https://github.com/ReginalThembaubisi/smart-timetable-system/tree/main/admin/screenshots)
- **Mobile App Screenshots:** [`/screenshots/app-*.png`](https://github.com/ReginalThembaubisi/smart-timetable-system/tree/main/admin/screenshots)

The gallery includes:
- Admin panel interface screenshots
- Mobile app screenshots (timetable views, study plans, etc.)
- Database management views

## 👤 Author

**Themba Ubisi**
- **GitHub:** [@ReginalThembaubisi](https://github.com/ReginalThembaubisi)
- **Repository:** [smart-timetable-system](https://github.com/ReginalThembaubisi/smart-timetable-system)

## 📄 License

This project is for educational use only.

---

> **Note:** This system was built for educational purposes as part of a university course project. It demonstrates full-stack development capabilities and could be adapted for real-world university use with additional security and feature enhancements.

