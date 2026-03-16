import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../models/student.dart';
import '../models/lecturer.dart';
import '../models/outline_event.dart';

class LocalStorageService {
  static const String _studentKey = 'student_data';
  static const String _lecturerKey = 'lecturer_data';
  static const String _outlineEventsKey = 'outline_events';
  static const String _apiKeyKey = 'gemini_api_key';
  static const String _studyPreferenceKey = 'study_preference';
  static const String _studyDaysKey = 'study_days';
  static const String _pomodoroStatsKey = 'pomodoro_stats';
  SharedPreferences? _prefs;
  static const Set<String> _legacyDemoTitles = {
    'assignment 1 submission',
    'semester test 1',
    'project milestone report',
    'practical assessment',
    'final examination',
  };

  LocalStorageService();

  Future<void> initialize() async {
    _prefs = await SharedPreferences.getInstance();
    await _purgeLegacyDemoOutlineEvents();
  }

  Future<void> saveStudent(Student student) async {
    if (_prefs != null) {
      await _prefs!.setString(_studentKey, jsonEncode(student.toJson()));
    }
  }

  Future<void> saveLecturer(Lecturer lecturer) async {
    if (_prefs != null) {
      await _prefs!.setString(_lecturerKey, jsonEncode(lecturer.toJson()));
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

  Lecturer? getLecturer() {
    if (_prefs != null) {
      final lecturerData = _prefs!.getString(_lecturerKey);
      if (lecturerData != null) {
        try {
          return Lecturer.fromJson(jsonDecode(lecturerData));
        } catch (e) {
          debugPrint('Error parsing lecturer data: $e');
        }
      }
    }
    return null;
  }

  bool isStudentLoggedIn() {
    return getStudent() != null;
  }

  bool isLecturerLoggedIn() {
    return getLecturer() != null;
  }

  Future<int?> getStudentId() async {
    if (_prefs == null) await initialize();
    // studentId is already an int? in the Student model
    return getStudent()?.studentId;
  }

  Future<void> clearStudent() async {
    if (_prefs != null) {
      await _prefs!.remove(_studentKey);
    }
  }

  Future<void> clearLecturer() async {
    if (_prefs != null) {
      await _prefs!.remove(_lecturerKey);
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
      final cleanedIncoming =
          events.where((event) => !_isLegacyDemoEvent(event)).toList();
      // Merge with existing events to avoid overwriting
      final existing = getOutlineEvents();
      // Upsert by title and date so reminder toggles and edits persist correctly.
      for (var newEvent in cleanedIncoming) {
        final index = existing.indexWhere(
            (e) => e.title == newEvent.title && e.date == newEvent.date);
        if (index >= 0) {
          existing[index] = newEvent;
        } else {
          existing.add(newEvent);
        }
      }
      final List<String> encoded =
          existing.map((e) => jsonEncode(e.toJson())).toList().cast<String>();
      await _prefs!.setStringList(_outlineEventsKey, encoded);
    }
  }

  List<OutlineEvent> getOutlineEvents() {
    if (_prefs != null) {
      final List<String>? encoded = _prefs!.getStringList(_outlineEventsKey);
      if (encoded != null && encoded.isNotEmpty) {
        try {
          final decoded =
              encoded.map((e) => OutlineEvent.fromJson(jsonDecode(e))).toList();
          return decoded.where((event) => !_isLegacyDemoEvent(event)).toList();
        } catch (e) {
          debugPrint('Error parsing outline events: $e');
        }
      }
    }
    return [];
  }

  bool _isLegacyDemoEvent(OutlineEvent event) {
    final moduleCode = event.moduleCode.trim().toUpperCase();
    if (moduleCode == 'DEMO') return true;
    return _legacyDemoTitles.contains(event.title.trim().toLowerCase());
  }

  Future<void> _purgeLegacyDemoOutlineEvents() async {
    if (_prefs == null) return;
    final encoded = _prefs!.getStringList(_outlineEventsKey);
    if (encoded == null || encoded.isEmpty) return;

    try {
      final decoded =
          encoded.map((e) => OutlineEvent.fromJson(jsonDecode(e))).toList();
      final cleaned =
          decoded.where((event) => !_isLegacyDemoEvent(event)).toList();
      if (cleaned.length == decoded.length) return;

      final cleanedEncoded =
          cleaned.map((e) => jsonEncode(e.toJson())).toList();
      await _prefs!.setStringList(_outlineEventsKey, cleanedEncoded);
      debugPrint(
          'Removed ${decoded.length - cleaned.length} legacy demo outline events');
    } catch (e) {
      debugPrint('Error purging legacy demo events: $e');
    }
  }

  Future<void> saveApiKey(String key) async {
    if (_prefs != null) {
      await _prefs!.setString(_apiKeyKey, key);
    }
  }

  String? getApiKey() {
    return _prefs?.getString(_apiKeyKey);
  }

  // Study time preference ('morning', 'afternoon', 'evening', 'night', 'balanced')
  Future<void> saveStudyPreference(String preference) async {
    if (_prefs != null) {
      await _prefs!.setString(_studyPreferenceKey, preference);
    }
  }

  String getStudyPreference() {
    return _prefs?.getString(_studyPreferenceKey) ?? 'balanced';
  }

  // Preferred study days (e.g. ['Monday','Tuesday','Wednesday','Thursday','Friday'])
  Future<void> saveStudyDays(List<String> days) async {
    if (_prefs != null) {
      await _prefs!.setStringList(_studyDaysKey, days);
    }
  }

  List<String> getStudyDays() {
    return _prefs?.getStringList(_studyDaysKey) ??
        ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
  }

  // Pomodoro Statistics
  Future<void> savePomodoroStats(Map<String, int> stats) async {
    if (_prefs != null) {
      final jsonString = jsonEncode(stats);
      await _prefs!.setString(_pomodoroStatsKey, jsonString);
      debugPrint('Saved Pomodoro stats: $jsonString');
    }
  }

  Map<String, int> getPomodoroStats() {
    if (_prefs != null) {
      final statsString = _prefs!.getString(_pomodoroStatsKey);
      if (statsString != null) {
        try {
          final Map<String, dynamic> decoded = jsonDecode(statsString);
          return decoded.map((key, value) => MapEntry(key, value as int));
        } catch (e) {
          debugPrint('Error parsing Pomodoro stats: $e');
        }
      }
    }
    // Default empty stats
    return {
      'sessions': 0,
      'pomodoros': 0,
      'focusTime': 0,
      'breakTime': 0,
    };
  }
}
