import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../models/study_session.dart';
import 'notification_service.dart';

class StudySessionService {
  static const String _sessionsKey = 'study_sessions';

  // Get all study sessions for a student
  static Future<List<StudySession>> getStudySessions(int studentId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final sessionsKey = '${_sessionsKey}_$studentId';
      
      debugPrint('Getting study sessions for student ID: $studentId');
      debugPrint('Using SharedPreferences key: $sessionsKey');
      
      final sessionsJson = prefs.getStringList(sessionsKey) ?? [];
      debugPrint('Raw JSON data from SharedPreferences: $sessionsJson');
      
      final sessions = sessionsJson
          .map((json) => StudySession.fromJson(jsonDecode(json)))
          .toList();
      
      debugPrint('Parsed ${sessions.length} study sessions');
      
      // Sort by day and time
      sessions.sort((a, b) {
        final dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        final dayA = dayOrder.indexOf(a.dayOfWeek);
        final dayB = dayOrder.indexOf(b.dayOfWeek);
        
        if (dayA != dayB) return dayA.compareTo(dayB);
        return a.startTime.compareTo(b.startTime);
      });
      
      return sessions;
    } catch (e) {
      debugPrint('Error getting study sessions: $e');
      return [];
    }
  }

  // Add a new study session
  static Future<bool> addStudySession(int studentId, StudySession session) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final sessionsKey = '${_sessionsKey}_$studentId';
      
      debugPrint('Adding study session for student ID: $studentId');
      debugPrint('Using SharedPreferences key: $sessionsKey');
      
      final existingSessions = await getStudySessions(studentId);
      
      // Generate a unique session ID
      final nextId = existingSessions.isEmpty ? 1 : 
          existingSessions.map((s) => s.sessionId ?? 0).reduce((a, b) => a > b ? a : b) + 1;
      
      // Create a new session with the unique ID
      final sessionWithId = session.copyWith(sessionId: nextId);
      existingSessions.add(sessionWithId);
      
      final sessionsJson = existingSessions
          .map((s) => jsonEncode(s.toJson()))
          .toList();
      
      await prefs.setStringList(sessionsKey, sessionsJson);
      
      // Schedule notification for the new session
      // Convert day and time to proper DateTime
      final notificationDateTime = _createDateTimeFromDayAndTime(
        sessionWithId.dayOfWeek, 
        sessionWithId.startTime
      );
      
      await NotificationService.scheduleStudySessionNotification(
        sessionWithId.sessionId.toString(), 
        sessionWithId.title, 
        notificationDateTime
      );
      
      debugPrint('Study session added with ID $nextId: ${session.title}');
      debugPrint('Total sessions for student: ${existingSessions.length}');
      return true;
    } catch (e) {
      debugPrint('Error adding study session: $e');
      return false;
    }
  }

  // Update an existing study session
  static Future<bool> updateStudySession(int studentId, StudySession oldSession, StudySession newSession) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final sessionsKey = '${_sessionsKey}_$studentId';
      
      final existingSessions = await getStudySessions(studentId);
      
      // Use sessionId for reliable matching
      final sessionIndex = existingSessions.indexWhere((session) => 
        session.sessionId == oldSession.sessionId
      );
      
      if (sessionIndex != -1) {
        // Preserve the original sessionId
        final updatedSession = newSession.copyWith(sessionId: oldSession.sessionId);
        existingSessions[sessionIndex] = updatedSession;
        
        final sessionsJson = existingSessions
            .map((s) => jsonEncode(s.toJson()))
            .toList();
        
        await prefs.setStringList(sessionsKey, sessionsJson);
        
        // Cancel old notification and schedule new one
        await NotificationService.cancelNotification(oldSession.sessionId!.toString());
        
        // Convert day and time to proper DateTime for notification
        final notificationDateTime = _createDateTimeFromDayAndTime(
          updatedSession.dayOfWeek, 
          updatedSession.startTime
        );
        
        await NotificationService.scheduleStudySessionNotification(
          updatedSession.sessionId.toString(), 
          updatedSession.title, 
          notificationDateTime
        );
        
        debugPrint('Study session updated with ID ${oldSession.sessionId}: ${newSession.title}');
        return true;
      }
      
      debugPrint('Session not found for update: ID ${oldSession.sessionId}');
      return false;
    } catch (e) {
      debugPrint('Error updating study session: $e');
      return false;
    }
  }

  // Delete a study session
  static Future<bool> deleteStudySession(int studentId, StudySession session) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final sessionsKey = '${_sessionsKey}_$studentId';
      
      final existingSessions = await getStudySessions(studentId);
      
      // Use sessionId for reliable deletion
      final initialCount = existingSessions.length;
      existingSessions.removeWhere((s) => s.sessionId == session.sessionId);
      final finalCount = existingSessions.length;
      
      if (initialCount == finalCount) {
        debugPrint('Session not found for deletion: ID ${session.sessionId}');
        return false;
      }
      
      final sessionsJson = existingSessions
          .map((s) => jsonEncode(s.toJson()))
          .toList();
      
      await prefs.setStringList(sessionsKey, sessionsJson);
      
      // Cancel notification for the deleted session
      await NotificationService.cancelNotification(session.sessionId!.toString());
      
      debugPrint('Study session deleted with ID ${session.sessionId}: ${session.title}');
      return true;
    } catch (e) {
      debugPrint('Error deleting study session: $e');
      return false;
    }
  }

  // Get session statistics
  static Map<String, dynamic> getSessionStats(List<StudySession> sessions) {
    if (sessions.isEmpty) {
      return {
        'totalSessions': 0,
        'totalTime': 0,
        'averageTime': 0,
        'mostStudiedModule': 'None',
        'favoriteTime': 'None',
        'sessionsByType': {},
      };
    }

    final totalSessions = sessions.length;
    
    // Calculate total duration
    int totalTime = 0;
    for (final session in sessions) {
      if (session.duration != null) {
        totalTime += session.duration!;
      } else {
        // Calculate duration from start and end time if not set
        totalTime += _calculateDurationFromTime(session.startTime, session.endTime);
      }
    }
    
    final averageTime = totalTime ~/ totalSessions;

    // Find most studied module
    final moduleCounts = <String, int>{};
    for (final session in sessions) {
      final module = session.moduleCode;
      moduleCounts[module] = (moduleCounts[module] ?? 0) + 1;
    }
    final mostStudiedModule = moduleCounts.entries
        .reduce((a, b) => a.value > b.value ? a : b)
        .key;

    // Find favorite study time
    final timeCounts = <String, int>{};
    for (final session in sessions) {
      final time = session.startTime;
      timeCounts[time] = (timeCounts[time] ?? 0) + 1;
    }
    final favoriteTime = timeCounts.entries
        .reduce((a, b) => a.value > b.value ? a : b)
        .key;

    // Count sessions by type
    final sessionsByType = <String, int>{};
    for (final session in sessions) {
      final type = session.sessionType;
      sessionsByType[type] = (sessionsByType[type] ?? 0) + 1;
    }

    return {
      'totalSessions': totalSessions,
      'totalTime': totalTime,
      'averageTime': averageTime,
      'mostStudiedModule': mostStudiedModule,
      'favoriteTime': favoriteTime,
      'sessionsByType': sessionsByType,
    };
  }

  // Calculate duration from start and end time
  static int _calculateDurationFromTime(String startTime, String endTime) {
    try {
      final start = startTime.split(':');
      final end = endTime.split(':');
      
      if (start.length >= 2 && end.length >= 2) {
        final startHour = int.parse(start[0]);
        final startMin = int.parse(start[1]);
        final endHour = int.parse(end[0]);
        final endMin = int.parse(end[1]);
        
        final startTotal = startHour * 60 + startMin;
        final endTotal = endHour * 60 + endMin;
        final duration = endTotal - startTotal;
        
        return duration > 0 ? duration : 60; // Default to 1 hour if invalid
      }
    } catch (e) {
      debugPrint('Error calculating duration from time: $e');
    }
    
    return 60; // Default fallback
  }

  // Check for time conflicts
  static Future<List<StudySession>> checkTimeConflicts(int studentId, String day, String startTime, String endTime, {StudySession? excludeSession}) async {
    try {
      final sessions = await getStudySessions(studentId);
      final daySessions = sessions.where((s) => s.dayOfWeek == day).toList();
      
      final conflicts = <StudySession>[];
      final newStart = _timeToMinutes(startTime);
      final newEnd = _timeToMinutes(endTime);
      
      for (final session in daySessions) {
        if (excludeSession != null && 
            session.sessionId == excludeSession.sessionId) {
          continue; // Skip the session being edited
        }
        
        final sessionStart = _timeToMinutes(session.startTime);
        final sessionEnd = _timeToMinutes(session.endTime);
        
        // Check for overlap
        if ((newStart < sessionEnd) && (sessionStart < newEnd)) {
          conflicts.add(session);
        }
      }
      
      return conflicts;
    } catch (e) {
      debugPrint('Error checking time conflicts: $e');
      return [];
    }
  }

  // Convert time string to minutes
  static int _timeToMinutes(String time) {
    try {
      final parts = time.split(':');
      final hours = int.parse(parts[0]);
      final minutes = int.parse(parts[1]);
      return hours * 60 + minutes;
    } catch (e) {
      debugPrint('Error converting time to minutes: $e');
      return 0;
    }
  }

  // Get sessions for a specific day
  static Future<List<StudySession>> getSessionsForDay(int studentId, String day) async {
    try {
      final sessions = await getStudySessions(studentId);
      return sessions.where((s) => s.dayOfWeek == day).toList();
    } catch (e) {
      debugPrint('Error getting sessions for day: $e');
      return [];
    }
  }

  // Get sessions for a specific module
  static Future<List<StudySession>> getSessionsForModule(int studentId, String moduleCode) async {
    try {
      final sessions = await getStudySessions(studentId);
      return sessions.where((s) => s.moduleCode == moduleCode).toList();
    } catch (e) {
      debugPrint('Error getting sessions for module: $e');
      return [];
    }
  }

  // Reschedule all notifications for a student
  static Future<void> rescheduleAllNotifications(int studentId) async {
    try {
      final sessions = await getStudySessions(studentId);
      await NotificationService.scheduleAllStudySessionNotifications(sessions);
      debugPrint('Rescheduled notifications for ${sessions.length} sessions');
    } catch (e) {
      debugPrint('Error rescheduling notifications: $e');
    }
  }

  // Cancel all notifications for a student
  static Future<void> cancelAllNotifications(int studentId) async {
    try {
      await NotificationService.cancelAllStudySessionNotifications();
      debugPrint('Cancelled all notifications for student $studentId');
    } catch (e) {
      debugPrint('Error cancelling notifications: $e');
    }
  }

  // Helper method to create DateTime from day and time
  static DateTime _createDateTimeFromDayAndTime(String dayOfWeek, String timeString) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    
    // Get the target day of week
    final dayIndex = _getDayIndex(dayOfWeek);
    final currentDayIndex = now.weekday;
    
    // Calculate days to add to get to the target day
    int daysToAdd = dayIndex - currentDayIndex;
    if (daysToAdd <= 0) {
      daysToAdd += 7; // Next week if day has passed
    }
    
    final targetDate = today.add(Duration(days: daysToAdd));
    
    // Parse the time
    final timeParts = timeString.split(':');
    final hour = int.parse(timeParts[0]);
    final minute = timeParts.length > 1 ? int.parse(timeParts[1]) : 0;
    
    return DateTime(targetDate.year, targetDate.month, targetDate.day, hour, minute);
  }
  
  static int _getDayIndex(String dayName) {
    switch (dayName.toLowerCase()) {
      case 'monday': return 1;
      case 'tuesday': return 2;
      case 'wednesday': return 3;
      case 'thursday': return 4;
      case 'friday': return 5;
      case 'saturday': return 6;
      case 'sunday': return 7;
      default: return 1;
    }
  }
}
