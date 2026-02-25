import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../models/student.dart';
import '../models/outline_event.dart';

class LocalStorageService {
  static const String _studentKey = 'student_data';
  static const String _outlineEventsKey = 'outline_events';
  static const String _apiKeyKey = 'gemini_api_key';
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

  Future<void> saveOutlineEvents(List<OutlineEvent> events) async {
    if (_prefs != null) {
      // Merge with existing events to avoid overwriting
      final existing = getOutlineEvents();
      // Simple de-duplication based on title and date
      for (var newEvent in events) {
        if (!existing.any((e) => e.title == newEvent.title && e.date == newEvent.date)) {
          existing.add(newEvent);
        }
      }
      final List<String> encoded = existing.map((e) => jsonEncode(e.toJson())).toList().cast<String>();
      await _prefs!.setStringList(_outlineEventsKey, encoded);
    }
  }

  List<OutlineEvent> getOutlineEvents() {
    if (_prefs != null) {
      final List<String>? encoded = _prefs!.getStringList(_outlineEventsKey);
      if (encoded != null) {
        try {
          return encoded.map((e) => OutlineEvent.fromJson(jsonDecode(e))).toList();
        } catch (e) {
          debugPrint('Error parsing outline events: $e');
        }
      }
    }
    return [];
  }

  Future<void> saveApiKey(String key) async {
    if (_prefs != null) {
      await _prefs!.setString(_apiKeyKey, key);
    }
  }

  String? getApiKey() {
    return _prefs?.getString(_apiKeyKey);
  }
}
