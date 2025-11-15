# Smart Timetable System

A comprehensive timetable management system with Flutter mobile app and PHP web admin panel.

## Features

### Admin Panel (Web Interface)
- **Dashboard** - Overview with statistics
- **Student Management** - Add, edit, delete students
- **Module Management** - Manage course modules
- **Lecturer Management** - Manage lecturer information
- **Venue Management** - Manage classrooms and venues
- **Timetable Management** - Create and manage class sessions
- **Exam Management** - Schedule exams
- **Student Enrollment** - Enroll students in modules
- **Study Sessions** - View student-created study sessions

### Mobile App (Flutter)
- Student login
- View personal timetable
- View enrolled modules
- View exam timetable
- Create and manage study sessions
- Profile management
- Change password

## Setup Instructions

### 1. Database Setup

#### Option A: Using the Web Installer (Recommended)
1. Make sure XAMPP is running (Apache and MySQL)
2. Open browser: `http://localhost/setup_database.php`
3. Click "Setup Database" button
4. Optionally check "Insert sample data" for test data

#### Option B: Using phpMyAdmin
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "Import" tab
3. Choose file: `database_setup.sql`
4. Click "Go"

### 2. Admin Panel Access

1. Open: `http://localhost/admin/login.php`
2. Default credentials:
   - Username: `admin`
   - Password: `admin123`

**⚠️ Change these credentials in production!**

### 3. Test Student Credentials

If you inserted sample data:
- **Student Number**: `202057420`
- **Password**: `password123`
- **Student ID**: `3`

### 4. API Configuration

The Flutter app connects to:
- Default: `http://localhost`
- If your public folder is in a subdirectory, update `lib/config/app_config.dart`

## Database Structure

### Tables
- `students` - Student accounts
- `modules` - Course modules
- `lecturers` - Lecturer information
- `venues` - Classroom/venue information
- `sessions` - Timetable class sessions
- `student_modules` - Student enrollments (many-to-many)
- `exams` - Exam schedules
- `study_sessions` - Student-created study sessions
- `exam_notifications` - Exam notifications for students

## API Endpoints

All API endpoints are in the `public` folder:

- `student_login_api.php` - Student authentication
- `get_student_timetable.php` - Get student's timetable
- `student_modules_api.php` - Get student's enrolled modules
- `get_student_exam_timetable.php` - Get student's exam schedule
- `get_student_exam_notifications.php` - Get exam notifications
- `study_sessions_api.php` - CRUD operations for study sessions
- `update_student_profile.php` - Update student profile
- `change_password_api.php` - Change student password
- `fetch_all_modules.php` - Get all available modules
- `mark_notification_read.php` - Mark notification as read

## File Structure

```
public/
├── admin/                 # Admin web panel
│   ├── index.php         # Dashboard
│   ├── login.php         # Admin login
│   ├── students.php      # Student management
│   ├── modules.php       # Module management
│   ├── lecturers.php     # Lecturer management
│   ├── venues.php        # Venue management
│   ├── timetable.php     # Timetable management
│   ├── exams.php         # Exam management
│   ├── student_modules.php  # Enrollment management
│   ├── study_sessions.php   # Study session management
│   └── style.css         # Admin panel styles
├── *.php                 # API endpoints
├── database_setup.sql    # Database setup SQL
├── setup_database.php    # Web database installer
└── README.md            # This file
```

## Usage

### Admin Panel Workflow

1. **Setup Base Data**
   - Add modules
   - Add lecturers
   - Add venues
   - Add students

2. **Create Timetable**
   - Go to "Manage Timetable"
   - Add sessions (class times) for each module

3. **Enroll Students**
   - Go to "Student Enrollment"
   - Enroll students in their modules

4. **Schedule Exams**
   - Go to "Manage Exams"
   - Add exam schedules for modules

### Mobile App Workflow

1. Students login with student number and password
2. View their personalized timetable
3. View enrolled modules
4. View exam schedule
5. Create personal study sessions

## Development

### Database Connection

Default connection settings (in `admin/config.php`):
- Host: `localhost`
- Database: `smart_timetable`
- User: `root`
- Password: `` (empty)

Update these in production!

### API Base URL

Update in Flutter app: `lib/config/app_config.dart`

```dart
static const String apiBaseUrl = 'http://localhost';
```

For mobile devices/emulators, use your computer's IP address:
```dart
static const String apiBaseUrl = 'http://192.168.1.100';
```

## Security Notes

⚠️ **This is a development setup. For production:**

1. Change admin login credentials
2. Hash passwords properly
3. Use prepared statements (already implemented)
4. Add input validation
5. Enable HTTPS
6. Set secure database credentials
7. Add CSRF protection
8. Implement rate limiting

## Support

If you encounter issues:

1. Check XAMPP is running (Apache + MySQL)
2. Verify database is created: `smart_timetable`
3. Check file permissions
4. Review error logs in XAMPP
5. Verify API URLs match your setup

## License

Educational use only.

