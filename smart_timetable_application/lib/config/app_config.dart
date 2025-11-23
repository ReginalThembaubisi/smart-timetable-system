class AppConfig {
  // Network configurations: prefer API_BASE_URL from --dart-define / environment variable.
  // Default to Railway production URL (no /admin prefix - Railway serves from root)
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://web-production-f8792.up.railway.app',
  );

  // Fallback URLs (used automatically by ApiService)
  static const String apiBaseUrlWithPort = 'http://127.0.0.1/admin';
  static const String apiBaseUrlEmulator = 'http://10.0.2.2/admin';
  static const String apiBaseUrlLocalhost = 'http://localhost/admin';
  // Railway production URL as fallback (no /admin prefix)
  static const String apiBaseUrlRailway = 'https://web-production-f8792.up.railway.app';
  
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
