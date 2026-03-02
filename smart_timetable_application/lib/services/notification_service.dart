import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/timezone.dart' as tz;
import 'package:timezone/data/latest.dart' as tz;
import 'dart:convert';
import 'dart:async';
import '../models/student.dart';
import '../models/study_session.dart';
import 'api_service.dart';

class NotificationService {
  static const String _notificationsKey = 'exam_notifications';
  static Timer? _notificationTimer;
  static FlutterLocalNotificationsPlugin? _flutterLocalNotificationsPlugin;
  
  // Initialize notification service
  static Future<void> initialize() async {
    // Initialize timezone data
    tz.initializeTimeZones();
    
    // Initialize the notification plugin
    _flutterLocalNotificationsPlugin = FlutterLocalNotificationsPlugin();
    
    // Android initialization settings
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    
    // iOS initialization settings
    const DarwinInitializationSettings initializationSettingsIOS =
        DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );
    
    // Combined initialization settings
    const InitializationSettings initializationSettings =
        InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsIOS,
    );
    
    // Initialize the plugin
    await _flutterLocalNotificationsPlugin?.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: _onNotificationTapped,
    );
    
    // Request permissions (especially for iOS)
    await _requestPermissions();
    
    _startNotificationTimer();
  }
  
  // Handle notification tap
  static void _onNotificationTapped(NotificationResponse notificationResponse) {
    final payload = notificationResponse.payload;
    if (payload != null) {
      // Handle different notification types
      print('Notification tapped with payload: $payload');
      // You could navigate to specific screens based on payload
    }
  }
  
  // Request notification permissions
  static Future<void> _requestPermissions() async {
    if (_flutterLocalNotificationsPlugin != null) {
      // Request permissions for iOS
      await _flutterLocalNotificationsPlugin!
          .resolvePlatformSpecificImplementation<
              IOSFlutterLocalNotificationsPlugin>()
          ?.requestPermissions(
            alert: true,
            badge: true,
            sound: true,
          );
      
      // Request permissions for Android 13+
      await _flutterLocalNotificationsPlugin!
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();
    }
  }
  
  // Start timer to check for notifications every 5 minutes
  static void _startNotificationTimer() {
    _notificationTimer?.cancel();
    _notificationTimer = Timer.periodic(
      const Duration(minutes: 5),
      (timer) => _checkForNotifications(),
    );
  }
  
  // Check for new notifications
  static Future<void> _checkForNotifications() async {
    try {
      final studentId = await ApiService.getStudentId();
      if (studentId == null) return;
      
      // This would typically check for push notifications
      // For now, we'll just check when the app is opened
    } catch (e) {
      print('Error checking notifications: $e');
    }
  }
  
  // Get stored notifications
  static Future<List<ExamNotification>> getStoredNotifications() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final notificationsJson = prefs.getString(_notificationsKey);
      
      if (notificationsJson != null) {
        final List<dynamic> notificationsList = jsonDecode(notificationsJson);
        return notificationsList
            .map((json) => ExamNotification.fromJson(json))
            .toList();
      }
    } catch (e) {
      print('Error getting stored notifications: $e');
    }
    
    return [];
  }
  
  // Store notifications locally
  static Future<void> storeNotifications(List<ExamNotification> notifications) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final notificationsJson = jsonEncode(
        notifications.map((n) => n.toJson()).toList(),
      );
      await prefs.setString(_notificationsKey, notificationsJson);
    } catch (e) {
      print('Error storing notifications: $e');
    }
  }
  
  // Mark notification as read
  static Future<void> markAsRead(String notificationId) async {
    try {
      final notifications = await getStoredNotifications();
      final updatedNotifications = notifications.map((notification) {
        if (notification.notificationId == notificationId) {
          return ExamNotification(
            notificationId: notification.notificationId,
            type: notification.type,
            title: notification.title,
            message: notification.message,
            timetableTitle: notification.timetableTitle,
            timetableStatus: notification.timetableStatus,
            createdAt: notification.createdAt,
            isRead: true,
            readAt: DateTime.now().toIso8601String(),
          );
        }
        return notification;
      }).toList();
      
      await storeNotifications(updatedNotifications);
    } catch (e) {
      print('Error marking notification as read: $e');
    }
  }
  
  // Show notification banner
  static void showNotificationBanner(
    BuildContext context,
    ExamNotification notification,
  ) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              notification.title,
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              notification.message,
              style: const TextStyle(color: Colors.white70),
            ),
          ],
        ),
        backgroundColor: _getNotificationColor(notification.type),
        duration: const Duration(seconds: 5),
        action: SnackBarAction(
          label: 'View',
          textColor: Colors.white,
          onPressed: () {
            // Navigate to exam timetables screen
            Navigator.pushNamed(context, '/exam_timetables');
          },
        ),
      ),
    );
  }
  
  // Get notification color based on type
  static Color _getNotificationColor(String type) {
    switch (type) {
      case 'draft_available':
        return Colors.orange;
      case 'final_available':
        return Colors.green;
      case 'exam_reminder':
        return Colors.blue;
      case 'venue_change':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }
  
  // Schedule deadline reminder (7 days before)
  static Future<int?> scheduleDeadlinesReminder(String title, DateTime deadlineDate, String type) async {
    if (_flutterLocalNotificationsPlugin == null) return null;
    
    try {
      final reminderId = DateTime.now().millisecondsSinceEpoch ~/ 1000;
      final reminderDate = deadlineDate.subtract(const Duration(days: 7));
      
      // Only schedule if the reminder date is in the future
      if (reminderDate.isAfter(DateTime.now())) {
        const AndroidNotificationDetails androidPlatformChannelSpecifics =
            AndroidNotificationDetails(
          'deadlines_reminders',
          'Deadline Reminders',
          channelDescription: 'Reminders for upcoming academic deadlines',
          importance: Importance.high,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
        );
        
        const DarwinNotificationDetails iOSPlatformChannelSpecifics =
            DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        );
        
        const NotificationDetails platformChannelSpecifics =
            NotificationDetails(
          android: androidPlatformChannelSpecifics,
          iOS: iOSPlatformChannelSpecifics,
        );
        
        if (kIsWeb) {
          // Limited web support for scheduled notifications
          print('Skipping scheduled notification on web for: $title');
        } else {
          await _flutterLocalNotificationsPlugin!.zonedSchedule(
            reminderId,
            '🔔 Upcoming $type',
            'Your $title is due in 7 days!',
            tz.TZDateTime.from(reminderDate, tz.local),
            platformChannelSpecifics,
            payload: 'deadline_$reminderId',
            androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
          );
        }
        
        print('Scheduled reminder for $title on $reminderDate');
        return reminderId;
      }
    } catch (e) {
      print('Error scheduling deadline reminder: $e');
    }
    return null;
  }

  // Cancel deadline reminder
  static Future<void> cancelDeadlineReminder(int reminderId) async {
    if (_flutterLocalNotificationsPlugin == null) return;
    try {
      await _flutterLocalNotificationsPlugin!.cancel(reminderId);
      print('Canceled deadline reminder: $reminderId');
    } catch (e) {
      print('Error canceling deadline reminder: $e');
    }
  }

  // Schedule study session notification
  static Future<void> scheduleStudySessionNotification(String sessionId, String title, DateTime scheduledTime) async {
    if (_flutterLocalNotificationsPlugin == null) return;
    
    try {
      // Read user's preferred reminder lead time (default 15 min)
      final prefs = await SharedPreferences.getInstance();
      final leadMinutes = prefs.getInt('reminder_lead_minutes') ?? 15;

      // Schedule notification [leadMinutes] before the session
      final notificationTime = scheduledTime.subtract(Duration(minutes: leadMinutes));
      
      // Only schedule if the time is in the future
      if (notificationTime.isAfter(DateTime.now())) {
        const AndroidNotificationDetails androidPlatformChannelSpecifics =
            AndroidNotificationDetails(
          'study_sessions',
          'Study Session Reminders',
          channelDescription: 'Notifications for upcoming study sessions',
          importance: Importance.high,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
          // removed non-existent sound resource
        );
        
        const DarwinNotificationDetails iOSPlatformChannelSpecifics =
            DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
          sound: 'default',
        );
        
        const NotificationDetails platformChannelSpecifics =
            NotificationDetails(
          android: androidPlatformChannelSpecifics,
          iOS: iOSPlatformChannelSpecifics,
        );

        final leadLabel = leadMinutes == 60 ? '1 hour' : '$leadMinutes minutes';
        
        // For web, we'll use a simpler approach since local notifications are limited
        if (kIsWeb) {
          // For web, we'll show an immediate notification instead
          await _flutterLocalNotificationsPlugin!.show(
            int.parse(sessionId),
            '📚 Study Session Reminder',
            '$title starts in $leadLabel! Time to prepare.',
            platformChannelSpecifics,
            payload: 'study_session_$sessionId',
          );
        } else {
          // For mobile platforms, use scheduled notifications
          await _flutterLocalNotificationsPlugin!.zonedSchedule(
            int.parse(sessionId),
            '📚 Study Session Reminder',
            '$title starts in $leadLabel! Time to prepare.',
            tz.TZDateTime.from(notificationTime, tz.local),
            platformChannelSpecifics,
            payload: 'study_session_$sessionId',
            androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
          );
        }
        
        print('Scheduling notification for session: $sessionId at $scheduledTime ($leadMinutes min early)');
      }
    } catch (e) {
      print('Error scheduling notification: $e');
    }
  }

  // Cancel notification
  static Future<void> cancelNotification(String sessionId) async {
    if (_flutterLocalNotificationsPlugin == null) return;
    
    try {
      await _flutterLocalNotificationsPlugin!.cancel(int.parse(sessionId));
      print('Canceling notification for session: $sessionId');
    } catch (e) {
      print('Error canceling notification: $e');
    }
  }

  // Schedule all study session notifications
  static Future<void> scheduleAllStudySessionNotifications(List<StudySession> sessions) async {
    print('Scheduling all study session notifications');
    
    for (final session in sessions) {
      if (session.sessionId != null) {
        // Calculate the next occurrence of this session
        final nextSessionTime = _getNextSessionDateTime(session);
        if (nextSessionTime != null) {
          await scheduleStudySessionNotification(
            session.sessionId.toString(),
            session.title,
            nextSessionTime,
          );
        }
      }
    }
  }

  // Cancel all study session notifications
  static Future<void> cancelAllStudySessionNotifications() async {
    if (_flutterLocalNotificationsPlugin == null) return;
    
    try {
      await _flutterLocalNotificationsPlugin!.cancelAll();
      print('Canceled all study session notifications');
    } catch (e) {
      print('Error canceling all notifications: $e');
    }
  }
  
  // Get next occurrence of a study session
  static DateTime? _getNextSessionDateTime(StudySession session) {
    try {
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      
      final targetDayIndex = _getDayIndex(session.dayOfWeek);
      final currentDayIndex = now.weekday;
      
      final timeParts = session.startTime.split(':');
      final hour = int.parse(timeParts[0]);
      final minute = int.parse(timeParts[1]);
      
      int daysToAdd = targetDayIndex - currentDayIndex;
      if (daysToAdd < 0) {
        daysToAdd += 7;
      }
      
      DateTime targetDate = today.add(Duration(days: daysToAdd));
      DateTime scheduledDateTime = DateTime(targetDate.year, targetDate.month, targetDate.day, hour, minute);
      
      if (daysToAdd == 0 && scheduledDateTime.isBefore(now)) {
        scheduledDateTime = scheduledDateTime.add(const Duration(days: 7));
      }
      
      return scheduledDateTime;
    } catch (e) {
      print('Error calculating next session time: $e');
      return null;
    }
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
  
  // Convert day name to weekday number
  static int _getDayOfWeekNumber(String dayName) {
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
  
  // Show immediate notification for starting a study session
  static Future<void> showStudySessionStartNotification(String title) async {
    if (_flutterLocalNotificationsPlugin == null) return;
    
    try {
      const AndroidNotificationDetails androidPlatformChannelSpecifics =
          AndroidNotificationDetails(
        'study_sessions_immediate',
        'Study Session Alerts',
        channelDescription: 'Immediate alerts for study sessions',
        importance: Importance.max,
        priority: Priority.high,
        icon: '@mipmap/ic_launcher',
        playSound: true,
        enableVibration: true,
      );
      
      const DarwinNotificationDetails iOSPlatformChannelSpecifics =
          DarwinNotificationDetails(
        presentAlert: true,
        presentBadge: true,
        presentSound: true,
      );
      
      const NotificationDetails platformChannelSpecifics =
          NotificationDetails(
        android: androidPlatformChannelSpecifics,
        iOS: iOSPlatformChannelSpecifics,
      );
      
      await _flutterLocalNotificationsPlugin!.show(
        DateTime.now().millisecondsSinceEpoch ~/ 1000,
        '🎯 Study Session Started!',
        '$title - Let\'s focus and make progress!',
        platformChannelSpecifics,
        payload: 'study_session_start',
      );
    } catch (e) {
      print('Error showing immediate notification: $e');
    }
  }
  
  // Show Pomodoro break notification
  static Future<void> showPomodoroBreakNotification() async {
    if (_flutterLocalNotificationsPlugin == null) return;
    
    try {
      const AndroidNotificationDetails androidPlatformChannelSpecifics =
          AndroidNotificationDetails(
        'pomodoro_breaks',
        'Pomodoro Break Alerts',
        channelDescription: 'Break time notifications for Pomodoro sessions',
        importance: Importance.high,
        priority: Priority.high,
        icon: '@mipmap/ic_launcher',
        playSound: true,
      );
      
      const DarwinNotificationDetails iOSPlatformChannelSpecifics =
          DarwinNotificationDetails(
        presentAlert: true,
        presentBadge: true,
        presentSound: true,
      );
      
      const NotificationDetails platformChannelSpecifics =
          NotificationDetails(
        android: androidPlatformChannelSpecifics,
        iOS: iOSPlatformChannelSpecifics,
      );
      
      await _flutterLocalNotificationsPlugin!.show(
        DateTime.now().millisecondsSinceEpoch ~/ 1000 + 1,
        '☕ Break Time!',
        'Great work! Take a 5-minute break to recharge.',
        platformChannelSpecifics,
        payload: 'pomodoro_break',
      );
    } catch (e) {
      print('Error showing break notification: $e');
    }
  }

  // Show test notification
  static void showTestNotification(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Test notification'),
        duration: Duration(seconds: 2),
      ),
    );
  }

  // Dispose timer
  static void dispose() {
    _notificationTimer?.cancel();
  }
}

// Enhanced ExamNotification model with read status
class ExamNotification {
  final String notificationId;
  final String type;
  final String title;
  final String message;
  final String timetableTitle;
  final String timetableStatus;
  final String createdAt;
  final bool isRead;
  final String? readAt;

  ExamNotification({
    required this.notificationId,
    required this.type,
    required this.title,
    required this.message,
    required this.timetableTitle,
    required this.timetableStatus,
    required this.createdAt,
    this.isRead = false,
    this.readAt,
  });

  factory ExamNotification.fromJson(Map<String, dynamic> json) {
    return ExamNotification(
      notificationId: json['notification_id'].toString(),
      type: json['type'] ?? '',
      title: json['title'] ?? '',
      message: json['message'] ?? '',
      timetableTitle: json['timetable_title'] ?? '',
      timetableStatus: json['timetable_status'] ?? '',
      createdAt: json['created_at'] ?? '',
      isRead: json['is_read'] ?? false,
      readAt: json['read_at'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'notification_id': notificationId,
      'type': type,
      'title': title,
      'message': message,
      'timetable_title': timetableTitle,
      'timetable_status': timetableStatus,
      'created_at': createdAt,
      'is_read': isRead,
      'read_at': readAt,
    };
  }
}