class AppConfig {
  // Network configurations - CHOOSE ONE BASED ON YOUR SETUP
  
  // Network configurations - Updated for XAMPP setup
  // API files are in the admin folder (C:\xampp\htdocs\admin\)
  // XAMPP default port is 80, so no port number needed
  // Since XAMPP serves from htdocs as document root, we need /admin path
  static const String apiBaseUrl = 'http://localhost/admin';
  
  // Fallback URLs (used automatically by ApiService)
  static const String apiBaseUrlWithPort = 'http://127.0.0.1/admin';
  static const String apiBaseUrlEmulator = 'http://10.0.2.2/admin';
  static const String apiBaseUrlLocalhost = 'http://localhost/admin';
  
  // Other app configuration constants can be added here
  static const String appName = 'Smart Timetable';
  static const String appVersion = '1.0.0';
  
  // API Endpoints
  static const String loginEndpoint = '/student_login_api.php';
  static const String timetableEndpoint = '/get_student_timetable.php';
  static const String modulesEndpoint = '/student_modules_api.php';
  static const String allModulesEndpoint = '/fetch_all_modules.php';
  
  // Timeout Configuration - increased for web development
  static const int connectionTimeout = 30000; // 30 seconds for web dev
  static const int receiveTimeout = 30000; // 30 seconds for web dev
}
