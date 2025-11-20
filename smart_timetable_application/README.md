# Smart Timetable Flutter App

A Flutter mobile application that connects to your PHP backend to display real student timetables and study plans.

## Features

✅ **Real Authentication** - Connects to your PHP backend for student login
✅ **Live Timetable Data** - Fetches real timetable from your database
✅ **Study Plan Management** - View assigned modules and available modules
✅ **Student Information** - Display real student details from backend
✅ **Error Handling** - Proper error messages and retry functionality
✅ **Loading States** - Shows loading indicators while fetching data

## Setup Instructions

### 1. PHP Backend Requirements

Make sure your PHP backend is running and accessible:
- XAMPP server running
- Database `smart_timetable` exists and has data
- PHP files accessible at `http://localhost/public/`

### 2. API Endpoints Required

Your PHP backend must have these endpoints working:
- `student_login_api.php` - Student authentication
- `get_student_timetable.php` - Fetch student timetable
- `get_student_modules.php` - Get student's assigned modules
- `fetch_all_modules.php` - Get all available modules

### 3. Flutter App Configuration

#### For Android Emulator Testing:
The app is configured to use `10.0.2.2` which allows the Android emulator to access your host machine.

#### For Physical Device Testing:
1. Find your computer's IP address (e.g., `192.168.1.100`)
2. Update `lib/config/app_config.dart`:
   ```dart
   static const String apiBaseUrl = 'http://192.168.1.100/public';
   ```

#### For Web Testing:
Update `lib/config/app_config.dart`:
```dart
static const String apiBaseUrl = 'http://localhost/public';
```

### 4. Testing the App

1. **Run your PHP backend** (XAMPP)
2. **Start your Flutter app**
3. **Login with real student credentials** from your database
4. **View real timetable data** from your backend
5. **Access study plan** to see assigned modules

## File Structure

```
lib/
├── config/
│   └── app_config.dart          # App configuration and API URLs
├── models/
│   ├── student.dart             # Student data model
│   └── module.dart              # Module data model
├── screens/
│   ├── timetable_screen.dart    # Main timetable display
│   └── study_plan_screen.dart   # Study plan and modules
├── services/
│   └── api_service.dart         # API communication service
└── main.dart                    # App entry point
```

## Troubleshooting

### Common Issues:

1. **"Connection refused" error:**
   - Check if XAMPP is running
   - Verify the API base URL in `app_config.dart`
   - For physical device, use your computer's actual IP address

2. **"Login failed" error:**
   - Check if `student_login_api.php` is working
   - Verify student credentials exist in database
   - Check PHP error logs

3. **"No data" displayed:**
   - Verify database has student data
   - Check if modules are assigned to students
   - Test API endpoints manually in browser

### Testing API Endpoints:

Test these URLs in your browser to verify they work:
- `http://localhost/public/student_login_api.php`
- `http://localhost/public/get_student_timetable.php?student_id=1`
- `http://localhost/public/get_student_modules.php?student_id=1`

## What Was Fixed

This app was completely rebuilt to:
- ❌ Remove hardcoded demo data
- ❌ Remove fake authentication
- ✅ Connect to real PHP backend APIs
- ✅ Display real student data
- ✅ Show real timetable information
- ✅ Add study plan functionality
- ✅ Handle loading and error states
- ✅ Provide proper user feedback

## Next Steps

Your Flutter app now:
1. **Authenticates real students** from your database
2. **Shows real timetable data** from your PHP backend
3. **Displays study plans** with assigned modules
4. **Handles errors gracefully** with retry options

The app is now fully functional and connected to your Smart Timetable system!
