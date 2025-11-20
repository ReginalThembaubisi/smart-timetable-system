import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import '../models/student.dart';
import '../config/app_config.dart';

class ApiService {
  static const String _baseUrl = AppConfig.apiBaseUrl;
  
  // Get base URL for external use
  static String get baseUrl => _baseUrl;
  
  // Alternative URLs to try if main one fails
  static const List<String> _fallbackUrls = [
    AppConfig.apiBaseUrl,
    AppConfig.apiBaseUrlEmulator,
    AppConfig.apiBaseUrlWithPort,
    AppConfig.apiBaseUrlLocalhost,
  ];

  // Create HTTP client with timeout and better error handling
  static http.Client _createClient() {
    final client = http.Client();
    return client;
  }

  // Some servers may prepend bytes/lines before JSON. This extracts the JSON object.
  static Map<String, dynamic>? _safeDecodeJsonObject(String body) {
    try {
      final start = body.indexOf('{');
      final end = body.lastIndexOf('}');
      if (start >= 0 && end >= start) {
        final jsonSlice = body.substring(start, end + 1);
        final decoded = jsonDecode(jsonSlice);
        if (decoded is Map<String, dynamic>) return decoded;
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  // Update student profile
  static Future<Map<String, dynamic>> updateStudentProfile(Student student) async {
    final client = _createClient();
    try {
      debugPrint('Attempting to update profile for student ID: ${student.studentId}');
      
      final response = await client.post(
        Uri.parse('$_baseUrl/update_student_profile.php'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'student_id': student.studentId,
          'full_name': student.fullName,
          'email': student.email,
        }),
      ).timeout(
        Duration(milliseconds: AppConfig.connectionTimeout),
        onTimeout: () {
          throw Exception('Profile update request timed out');
        },
      );

      debugPrint('Profile update response status: ${response.statusCode}');
      debugPrint('Profile update response body: ${response.body}');

      if (response.statusCode == 200) {
        final decoded = _safeDecodeJsonObject(response.body);
        // Normalize backend response shape:
        // PHP returns: { success, message, data: { student: {...} } }
        // UI expects: { success, message, student: {...} }
        if (decoded != null) {
          final success = decoded['success'] == true;
          final message = decoded['message'];
          final Map<String, dynamic>? student =
              (decoded['data'] is Map && decoded['data']['student'] is Map)
                  ? Map<String, dynamic>.from(decoded['data']['student'])
                  : (decoded['student'] is Map
                      ? Map<String, dynamic>.from(decoded['student'])
                      : null);
          return {
            'success': success,
            'message': message,
            'student': student,
          };
        }
        return {
          'success': false,
          'message': 'Unexpected response format',
        };
      } else {
        return {
          'success': false,
          'message': 'Server error: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint('Profile update error: $e');
      return {
        'success': false,
        'message': 'Network error: Unable to connect to server. Please check your connection.',
      };
    } finally {
      client.close();
    }
  }

  // Login student - simplified approach
  static Future<Map<String, dynamic>> loginStudent(String studentNumber, String password) async {
    final client = _createClient();
    try {
      debugPrint('Attempting login with URL: $_baseUrl${AppConfig.loginEndpoint}');
      
      final response = await client.post(
        Uri.parse('$_baseUrl${AppConfig.loginEndpoint}'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'student_number': studentNumber,
          'password': password,
        }),
      ).timeout(
        Duration(milliseconds: AppConfig.connectionTimeout),
        onTimeout: () {
          debugPrint('Login request timed out after ${AppConfig.connectionTimeout}ms');
          throw Exception('Login request timed out');
        },
      );

      debugPrint('Login response status: ${response.statusCode}');
      debugPrint('Login response body: ${response.body}');

      if (response.statusCode == 200) {
        final decoded = _safeDecodeJsonObject(response.body);
        if (decoded != null) {
          debugPrint('Login response decoded successfully: $decoded');
          return decoded;
        } else {
          debugPrint('Failed to decode login response');
          return {
            'success': false,
            'message': 'Invalid response format from server',
          };
        }
      } else {
        return {
          'success': false,
          'message': 'Server error: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint('Login error: $e');
      return {
        'success': false,
        'message': 'Network error: Unable to connect to server. Please check your connection.',
      };
    } finally {
      client.close();
    }
  }

  // Get student timetable
  static Future<Map<String, dynamic>> getStudentTimetable(int studentId) async {
    final client = _createClient();
    try {
      // For demo purposes, use student ID 1 if the passed ID is invalid
      final actualStudentId = studentId > 0 ? studentId : 1;
      debugPrint('Fetching timetable for student ID: $actualStudentId');
      final response = await client.get(
        Uri.parse('$_baseUrl${AppConfig.timetableEndpoint}?student_id=$actualStudentId'),
      ).timeout(
        Duration(milliseconds: AppConfig.connectionTimeout),
        onTimeout: () {
          throw Exception('Timetable request timed out');
        },
      );

      debugPrint('Timetable response status: ${response.statusCode}');
      if (response.statusCode == 200) {
        final decoded = _safeDecodeJsonObject(response.body) ?? {};
        final bool success = decoded['success'] == true;
        final String? message = decoded['message'];
        // Expect sessions as a flat list from backend: data.sessions
        final List<dynamic> sessions =
            (decoded['data'] is Map && decoded['data']['sessions'] is List)
                ? List<dynamic>.from(decoded['data']['sessions'])
                : (decoded['sessions'] is List ? List<dynamic>.from(decoded['sessions']) : <dynamic>[]);
        // Group into timetable map: { day: [sessions...] }
        final Map<String, List<dynamic>> grouped = <String, List<dynamic>>{};
        for (final s in sessions) {
          if (s is Map) {
            final String day = (s['day_of_week'] ?? '').toString();
            if (day.isEmpty) continue;
            grouped.putIfAbsent(day, () => <dynamic>[]);
            grouped[day]!.add(s);
          }
        }
        return {
          'success': success,
          'message': message,
          'timetable': grouped,
          'data': { 'timetable': grouped },
        };
      } else {
        debugPrint('Timetable API error: ${response.statusCode}');
        return {
          'success': false,
          'message': 'Failed to fetch timetable: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint('Timetable fetch error: $e');
      return {
        'success': false,
        'message': 'Network error: $e',
      };
    } finally {
      client.close();
    }
  }

  // Get student modules
  static Future<Map<String, dynamic>> getStudentModules(int studentId) async {
    final client = _createClient();
    try {
      // For demo purposes, use student ID 1 if the passed ID is invalid
      final actualStudentId = studentId > 0 ? studentId : 1;
      debugPrint('Fetching modules for student ID: $actualStudentId');
      final response = await client.get(
        Uri.parse('$_baseUrl${AppConfig.modulesEndpoint}?student_id=$actualStudentId'),
      ).timeout(
        Duration(milliseconds: AppConfig.connectionTimeout),
        onTimeout: () {
          throw Exception('Modules request timed out');
        },
      );

      debugPrint('Modules response status: ${response.statusCode}');
      if (response.statusCode == 200) {
        final decoded = _safeDecodeJsonObject(response.body) ?? {};
        final bool success = decoded['success'] == true;
        final String? message = decoded['message'];
        final List<dynamic> modules =
            (decoded['data'] is Map && decoded['data']['modules'] is List)
                ? List<dynamic>.from(decoded['data']['modules'])
                : (decoded['modules'] is List ? List<dynamic>.from(decoded['modules']) : <dynamic>[]);
        debugPrint('Modules data received successfully: ${modules.length}');
        return {
          'success': success,
          'message': message,
          'modules': modules,
          'data': { 'modules': modules },
        };
      } else {
        debugPrint('Modules API error: ${response.statusCode}');
        return {
          'success': false,
          'message': 'Failed to fetch modules: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint('Modules fetch error: $e');
      return {
        'success': false,
        'message': 'Network error: $e',
      };
    } finally {
      client.close();
    }
  }

  // Change student password
  static Future<Map<String, dynamic>> changeStudentPassword(
    int studentId, 
    String currentPassword, 
    String newPassword
  ) async {
    final client = _createClient();
    try {
      final response = await client.post(
        Uri.parse('$_baseUrl/change_password_api.php'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'student_id': studentId,
          'current_password': currentPassword,
          'new_password': newPassword,
        }),
      ).timeout(
        Duration(milliseconds: AppConfig.connectionTimeout),
        onTimeout: () {
          throw Exception('Password change request timed out');
        },
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data;
      } else {
        return {
          'success': false,
          'message': 'Password change failed: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint('Password change error: $e');
      return {
        'success': false,
        'message': 'Network error: $e',
      };
    } finally {
      client.close();
    }
  }

  // Get all available modules (for study planning)
  static Future<Map<String, dynamic>> getAllModules() async {
    final client = _createClient();
    try {
      final response = await client.get(
        Uri.parse('$_baseUrl${AppConfig.allModulesEndpoint}'),
      ).timeout(
        Duration(milliseconds: AppConfig.connectionTimeout),
        onTimeout: () {
          throw Exception('All modules request timed out');
        },
      );

      if (response.statusCode == 200) {
        final decoded = _safeDecodeJsonObject(response.body) ?? {};
        final bool success = decoded['success'] == true;
        final String? message = decoded['message'];
        final List<dynamic> modules =
            (decoded['data'] is Map && decoded['data']['modules'] is List)
                ? List<dynamic>.from(decoded['data']['modules'])
                : (decoded['modules'] is List ? List<dynamic>.from(decoded['modules']) : <dynamic>[]);
        return {
          'success': success,
          'message': message,
          'modules': modules,
          'data': { 'modules': modules },
        };
      } else {
        return {
          'success': false,
          'message': 'Failed to fetch all modules: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint('All modules fetch error: $e');
      return {
        'success': false,
        'message': 'Network error: $e',
      };
    } finally {
      client.close();
    }
  }

  // Get student exam timetable
  static Future<Map<String, dynamic>> getStudentExamTimetable(int studentId) async {
    final client = _createClient();
    try {
      debugPrint('Fetching exam timetable for student ID: $studentId');
      final response = await client.get(
        Uri.parse('$_baseUrl/get_student_exam_timetable.php?student_id=$studentId'),
      ).timeout(
        Duration(milliseconds: AppConfig.connectionTimeout),
        onTimeout: () {
          throw Exception('Exam timetable request timed out');
        },
      );

      debugPrint('Exam timetable response status: ${response.statusCode}');
      debugPrint('Exam timetable response body: ${response.body}');
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        debugPrint('Exam timetable data received successfully');
        return data;
      } else {
        debugPrint('Exam timetable API error: ${response.statusCode}');
        return {
          'success': false,
          'message': 'Failed to fetch exam timetable: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint('Exam timetable fetch error: $e');
      return {
        'success': false,
        'message': 'Network error: $e',
      };
    } finally {
      client.close();
    }
  }

  // Get student exam notifications
  static Future<Map<String, dynamic>> getStudentExamNotifications(int studentId) async {
    final client = _createClient();
    try {
      debugPrint('Fetching exam notifications for student ID: $studentId');
      final response = await client.get(
        Uri.parse('$_baseUrl/get_student_exam_notifications.php?student_id=$studentId'),
      ).timeout(
        Duration(milliseconds: AppConfig.connectionTimeout),
        onTimeout: () {
          throw Exception('Exam notifications request timed out');
        },
      );

      debugPrint('Exam notifications response status: ${response.statusCode}');
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        debugPrint('Exam notifications data received successfully');
        return data;
      } else {
        debugPrint('Exam notifications API error: ${response.statusCode}');
        return {
          'success': false,
          'message': 'Failed to fetch exam notifications: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint('Exam notifications fetch error: $e');
      return {
        'success': false,
        'message': 'Network error: $e',
      };
    } finally {
      client.close();
    }
  }

  // Mark exam notification as read
  static Future<Map<String, dynamic>> markExamNotificationRead(int notificationId, int studentId) async {
    final client = _createClient();
    try {
      final response = await client.post(
        Uri.parse('$_baseUrl/mark_notification_read.php'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'notification_id': notificationId,
          'student_id': studentId,
        }),
      ).timeout(
        Duration(milliseconds: AppConfig.connectionTimeout),
        onTimeout: () {
          throw Exception('Mark notification request timed out');
        },
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data;
      } else {
        return {
          'success': false,
          'message': 'Failed to mark notification as read: ${response.statusCode}',
        };
      }
    } catch (e) {
      debugPrint('Mark notification error: $e');
      return {
        'success': false,
        'message': 'Network error: $e',
      };
    } finally {
      client.close();
    }
  }

  // Get student ID from local storage
  static Future<String?> getStudentId() async {
    try {
      // This would typically get from SharedPreferences or similar
      // For now, return null - this should be implemented based on your storage strategy
      return null;
    } catch (e) {
      debugPrint('Error getting student ID: $e');
      return null;
    }
  }
}
