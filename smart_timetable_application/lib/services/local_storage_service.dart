import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../models/student.dart';

class LocalStorageService {
  static const String _studentKey = 'student_data';
  SharedPreferences? _prefs;

  LocalStorageService();

  Future<void> initialize() async {
    _prefs = await SharedPreferences.getInstance();
  }

  Future<void> saveStudent(Student student) async {
    if (_prefs != null) {
      await _prefs!.setString(_studentKey, jsonEncode(student.toJson()));
    }
  }

  Student? getStudent() {
    if (_prefs != null) {
      final studentData = _prefs!.getString(_studentKey);
      if (studentData != null) {
        try {
          return Student.fromJson(jsonDecode(studentData));
        } catch (e) {
          debugPrint('Error parsing student data: $e');
        }
      }
    }
    return null;
  }

  bool isStudentLoggedIn() {
    return getStudent() != null;
  }

  Future<void> clearStudent() async {
    if (_prefs != null) {
      await _prefs!.remove(_studentKey);
    }
  }

  Future<void> clearStudentData() async {
    if (_prefs != null) {
      await _prefs!.clear();
      debugPrint('All student data cleared');
    }
  }

  // Clear only login/session auth, keep user-created data like study sessions
  Future<void> clearLoginOnly() async {
    if (_prefs != null) {
      await _prefs!.remove(_studentKey);
      debugPrint('Login data cleared (kept study sessions)');
    }
  }
}
