class AppConfig {
  // Network configurations: prefer API_BASE_URL from --dart-define / environment variable.
  // Default to Railway production URL (no /admin prefix - Railway serves from root)
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://web-production-f8792.up.railway.app',
  );

  // Fallback URLs (used automatically by ApiService)
  static const String apiBaseUrlWithPort = 'http://127.0.0.1:8090';
  static const String apiBaseUrlEmulator = 'http://10.0.2.2:8090';
  static const String apiBaseUrlLocalhost = 'http://localhost:8090';
  // Railway production URL as fallback
  static const String apiBaseUrlRailway = 'https://web-production-f8792.up.railway.app';
  
  // Other app configuration constants can be added here
  static const String appName = 'Smart Timetable';
  static const String appVersion = '1.0.0';
  
  // API Endpoints - All migrated to /api/ directory
  static const String loginEndpoint = '/api/student_login_api.php';
  static const String timetableEndpoint = '/api/get_student_timetable.php';
  static const String modulesEndpoint = '/api/student_modules_api.php';
  static const String allModulesEndpoint = '/api/fetch_all_modules.php';
  
  // Timeout Configuration - increased for web development
  static const int connectionTimeout = 30000; // 30 seconds for web dev
  static const int receiveTimeout = 30000; // 30 seconds for web dev
}

