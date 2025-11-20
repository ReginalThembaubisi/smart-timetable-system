import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/app_theme.dart';
import '../config/app_colors.dart';
import '../services/api_service.dart';
import '../widgets/skeleton_loader.dart';
import '../widgets/empty_state.dart';
import '../widgets/loading_indicators.dart';
import '../widgets/enhanced_card.dart';
import '../widgets/animations.dart';

class ExamTimetableScreen extends StatefulWidget {
  const ExamTimetableScreen({Key? key}) : super(key: key);

  @override
  State<ExamTimetableScreen> createState() => _ExamTimetableScreenState();
}

class _ExamTimetableScreenState extends State<ExamTimetableScreen>
    with TickerProviderStateMixin {
  late TabController _tabController;
  List<ExamTimetable> _timetables = [];
  List<ExamNotification> _notifications = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadExamData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadExamData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      // Get student ID from shared preferences
      final prefs = await SharedPreferences.getInstance();
      
      // Try different keys for student data
      String? studentJson = prefs.getString('student');
      studentJson ??= prefs.getString('student_data');
      studentJson ??= prefs.getString('logged_in_student');
      
      if (studentJson == null) {
        // Try to get student ID directly
        final directStudentId = prefs.getInt('student_id')?.toString();
        if (directStudentId != null) {
          // Load exam timetables and notifications in parallel
          final results = await Future.wait([
            _loadExamTimetables(directStudentId),
            _loadExamNotifications(directStudentId),
          ]);

          setState(() {
            _timetables = results[0] as List<ExamTimetable>;
            _notifications = results[1] as List<ExamNotification>;
            _isLoading = false;
          });
          return;
        }
        throw Exception('Student ID not found. Please log in again.');
      }
      
      final studentData = jsonDecode(studentJson);
      String? studentId = studentData['student_id']?.toString();
      studentId ??= studentData['id']?.toString();
      studentId ??= studentData['studentId']?.toString();
      
      if (studentId == null) {
        throw Exception('Student ID not found. Please log in again.');
      }

      // Load exam timetables and notifications in parallel
      final results = await Future.wait([
        _loadExamTimetables(studentId),
        _loadExamNotifications(studentId),
      ]);

      setState(() {
        _timetables = results[0] as List<ExamTimetable>;
        _notifications = results[1] as List<ExamNotification>;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        // Show more user-friendly error messages
        if (e.toString().contains('Student ID not found')) {
          _error = 'Please log in again to access exam timetables.';
        } else if (e.toString().contains('No modules found')) {
          _error = 'No exam timetables available for your registered modules yet.';
        } else if (e.toString().contains('No exam timetable available')) {
          _error = 'No exam timetables have been published yet.';
        } else {
          _error = 'Unable to load exam data. Please check your connection and try again.';
        }
        _isLoading = false;
      });
    }
  }

  Future<List<ExamTimetable>> _loadExamTimetables(String studentId) async {
    try {
      final data = await ApiService.getStudentExamTimetable(int.parse(studentId));
      
      if (data['success'] == true) {
        // Handle multiple backend shapes:
        // 1) Full timetables list [{... exams:[...] }, ...]
        // 2) Flat exams under data.exams [{ exam_date, exam_time, duration, module_name, module_code, venue_name }]
        final dynamic timetableData = data['timetable'] ?? data['data'];
        if (timetableData is List) {
          return timetableData.map((t) => ExamTimetable.fromJson(t as Map<String, dynamic>)).toList();
        }
        if (timetableData is Map && timetableData['exams'] is List) {
          final List<dynamic> examsList = List<dynamic>.from(timetableData['exams']);
          final mappedExams = examsList.map((e) {
            final Map<String, dynamic> row = Map<String, dynamic>.from(e as Map);
            // Map backend fields to UI Exam model
            final int duration = (row['duration'] is int) ? row['duration'] as int : int.tryParse('${row['duration']}') ?? 0;
            final String startTime = '${row['exam_time'] ?? ''}';
            // Calculate end time from start time and duration
            String? endTime;
            if (startTime.isNotEmpty && duration > 0) {
              try {
                final timeParts = startTime.split(':');
                if (timeParts.length >= 2) {
                  final hour = int.parse(timeParts[0]);
                  final minute = int.parse(timeParts[1]);
                  final startDateTime = DateTime(2000, 1, 1, hour, minute);
                  final endDateTime = startDateTime.add(Duration(minutes: duration));
                  endTime = '${endDateTime.hour.toString().padLeft(2, '0')}:${endDateTime.minute.toString().padLeft(2, '0')}:${endDateTime.second.toString().padLeft(2, '0')}';
                }
              } catch (e) {
                // If parsing fails, leave endTime as null
                endTime = null;
              }
            }
            return Exam(
              examId: '${row['exam_id'] ?? ''}',
              moduleName: '${row['module_name'] ?? ''}',
              moduleCode: '${row['module_code'] ?? ''}',
              examDate: '${row['exam_date'] ?? ''}',
              startTime: startTime,
              endTime: endTime,
              venue: '${row['venue_name'] ?? ''}',
              durationMinutes: duration,
              examType: '${row['exam_status'] ?? 'final'}',
              instructions: '',
            );
          }).toList();
          
          // If no exams, return empty list gracefully
          if (mappedExams.isEmpty) return [];
          
          final synthetic = ExamTimetable(
            timetableId: 'default',
            title: 'Exam Timetable',
            description: '',
            status: 'final',
            academicYear: '',
            semester: '',
            uploadedAt: '',
            exams: mappedExams,
          );
          return [synthetic];
        }
        // Fallback: no recognizable data shape
        return [];
      } else {
        // If no timetables found, return empty list instead of throwing error
        if (data['message']?.contains('No modules found') == true || 
            data['message']?.contains('No exam timetable available') == true) {
          return [];
        }
        throw Exception(data['message'] ?? data['error'] ?? 'Failed to load exam timetables');
      }
    } catch (e) {
      // If it's a network error or no data, return empty list
      if (e.toString().contains('No modules found') || 
          e.toString().contains('No exam timetable available')) {
        return [];
      }
      rethrow;
    }
  }

  Future<List<ExamNotification>> _loadExamNotifications(String studentId) async {
    try {
      final data = await ApiService.getStudentExamNotifications(int.parse(studentId));
      
      if (data['success'] == true) {
        // Handle both 'data' and 'notifications' response formats
        final notificationData = data['notifications'] ?? data['data'] ?? [];
        return (notificationData as List)
            .map((n) => ExamNotification.fromJson(n))
            .toList();
      } else {
        // If no notifications found, return empty list instead of throwing error
        return [];
      }
    } catch (e) {
      // If it's a network error or no data, return empty list
      return [];
    }
  }

  Future<void> _markNotificationAsRead(String notificationId) async {
    try {
      // Get student ID from shared preferences
      final prefs = await SharedPreferences.getInstance();
      final studentJson = prefs.getString('student');
      if (studentJson == null) return;
      
      final studentData = jsonDecode(studentJson);
      final studentId = studentData['student_id']?.toString();
      if (studentId == null) return;

      final data = await ApiService.markExamNotificationRead(
        int.parse(notificationId), 
        int.parse(studentId)
      );

      if (data['success']) {
        // Remove notification from local list
        setState(() {
          _notifications.removeWhere((n) => n.notificationId == notificationId);
        });
      }
    } catch (e) {
      // Handle error silently or show a snackbar
      print('Error marking notification as read: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
      appBar: AppBar(
        title: const Text('Exam Timetables'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        elevation: 0,
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.white,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          tabs: [
            Tab(
              icon: const Icon(Icons.calendar_today),
              text: 'Timetables',
            ),
            Tab(
              icon: const Icon(Icons.notifications),
              child: _notifications.isNotEmpty
                  ? Stack(
                      children: [
                        const Text('Notifications'),
                        Positioned(
                          right: 0,
                          top: 0,
                          child: Container(
                            padding: const EdgeInsets.all(2),
                            decoration: BoxDecoration(
                              color: Colors.red,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            constraints: const BoxConstraints(
                              minWidth: 16,
                              minHeight: 16,
                            ),
                            child: Text(
                              '${_notifications.length}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                              textAlign: TextAlign.center,
                            ),
                          ),
                        ),
                      ],
                    )
                  : const Text('Notifications'),
            ),
          ],
        ),
      ),
      body: _isLoading
          ? _buildLoadingState()
          : _error != null
              ? _buildErrorWidget()
              : TabBarView(
                  controller: _tabController,
                  children: [
                    _buildTimetablesTab(),
                    _buildNotificationsTab(),
                  ],
                ),
      floatingActionButton: FloatingActionButton(
        onPressed: _loadExamData,
        backgroundColor: AppColors.primary,
        child: const Icon(Icons.refresh, color: Colors.white),
      ),
    );
  }

  Widget _buildErrorWidget() {
    return Center(
      child: ErrorState(
        title: 'Error Loading Exam Data',
        message: _error ?? 'Something went wrong. Please try again.',
        onRetry: _loadExamData,
        retryText: 'Retry',
      ),
    );
  }

  Widget _buildLoadingState() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: List.generate(3, (index) => const SkeletonExamCard()),
      ),
    );
  }

  Widget _buildTimetablesTab() {
    if (_timetables.isEmpty) {
      return Center(
        child: EmptyExamState(
          onRefresh: _loadExamData,
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _timetables.length,
      itemBuilder: (context, index) {
        final timetable = _timetables[index];
        return _buildTimetableCard(timetable);
      },
    );
  }

  Widget _buildTimetableCard(ExamTimetable timetable) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      elevation: 4,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: ExpansionTile(
        initiallyExpanded: true,
        leading: CircleAvatar(
          backgroundColor: timetable.status == 'final'
              ? Colors.green
              : Colors.orange,
          child: Icon(
            timetable.status == 'final'
                ? Icons.check_circle
                : Icons.edit,
            color: Colors.white,
          ),
        ),
        title: Text(
          timetable.title,
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 16,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (timetable.description.isNotEmpty)
              Text(
                timetable.description,
                style: TextStyle(
                  color: Colors.grey[600],
                  fontSize: 12,
                ),
              ),
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(
                  Icons.school,
                  size: 14,
                  color: Colors.grey[600],
                ),
                const SizedBox(width: 4),
                Text(
                  timetable.academicYear,
                  style: TextStyle(
                    color: Colors.grey[600],
                    fontSize: 12,
                  ),
                ),
                const SizedBox(width: 16),
                Icon(
                  Icons.calendar_month,
                  size: 14,
                  color: Colors.grey[600],
                ),
                const SizedBox(width: 4),
                Text(
                  '${timetable.exams.length} exams',
                  style: TextStyle(
                    color: Colors.grey[600],
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ],
        ),
        children: [
          if (timetable.exams.isEmpty)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'No exams scheduled for your modules',
                style: TextStyle(
                  color: Colors.grey,
                  fontStyle: FontStyle.italic,
                ),
              ),
            )
          else
            ...timetable.exams.map((exam) => Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: _buildExamCard(exam),
            )),
        ],
      ),
    );
  }

  Widget _buildExamCard(Exam exam) {
    return FadeInAnimation(
      delay: const Duration(milliseconds: 200),
      child: ExamCard(
        moduleName: exam.moduleName,
        moduleCode: exam.moduleCode,
        date: _formatDate(exam.examDate),
        time: '${_formatTime(exam.startTime)} - ${_formatTime(exam.endTime)}',
        venue: exam.venue,
        duration: '${exam.durationMinutes} min',
        examType: exam.examType,
        onTap: () {
          // Add exam details dialog or navigation
          _showExamDetails(exam);
        },
      ),
    );
  }

  void _showExamDetails(Exam exam) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(exam.moduleName),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Module: ${exam.moduleCode}'),
            const SizedBox(height: 8),
            Text('Date: ${_formatDate(exam.examDate)}'),
            Text('Time: ${_formatTime(exam.startTime)} - ${_formatTime(exam.endTime)}'),
            Text('Duration: ${exam.durationMinutes} minutes'),
            Text('Venue: ${exam.venue}'),
            Text('Type: ${exam.examType.toUpperCase()}'),
            if (exam.instructions.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text('Instructions: ${exam.instructions}'),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  Widget _buildNotificationsTab() {
    if (_notifications.isEmpty) {
      return Center(
        child: EmptyNotificationsState(),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _notifications.length,
      itemBuilder: (context, index) {
        final notification = _notifications[index];
        return _buildNotificationCard(notification);
      },
    );
  }

  Widget _buildNotificationCard(ExamNotification notification) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: _getNotificationColor(notification.type),
          child: Icon(
            _getNotificationIcon(notification.type),
            color: Colors.white,
          ),
        ),
        title: Text(
          notification.title,
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 14,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(
              notification.message,
              style: TextStyle(
                color: Colors.grey[600],
                fontSize: 12,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              _formatDateTime(notification.createdAt),
              style: TextStyle(
                color: Colors.grey[500],
                fontSize: 10,
              ),
            ),
          ],
        ),
        trailing: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => _markNotificationAsRead(notification.notificationId),
        ),
        onTap: () => _markNotificationAsRead(notification.notificationId),
      ),
    );
  }

  Color _getExamTypeColor(String type) {
    switch (type.toLowerCase()) {
      case 'written':
        return Colors.blue;
      case 'practical':
        return Colors.green;
      case 'oral':
        return Colors.orange;
      case 'online':
        return Colors.purple;
      default:
        return Colors.grey;
    }
  }

  Color _getNotificationColor(String type) {
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

  IconData _getNotificationIcon(String type) {
    switch (type) {
      case 'draft_available':
        return Icons.edit;
      case 'final_available':
        return Icons.check_circle;
      case 'exam_reminder':
        return Icons.alarm;
      case 'venue_change':
        return Icons.location_on;
      default:
        return Icons.notifications;
    }
  }

  String _formatDate(String dateString) {
    final date = DateTime.parse(dateString);
    return '${date.day}/${date.month}/${date.year}';
  }

  String _formatTime(String? timeString) {
    if (timeString == null || timeString.isEmpty) {
      return '00:00';
    }
    try {
      final time = TimeOfDay.fromDateTime(DateTime.parse('2000-01-01 $timeString'));
      return '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}';
    } catch (e) {
      return '00:00';
    }
  }

  String _formatDateTime(String dateTimeString) {
    final dateTime = DateTime.parse(dateTimeString);
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inDays > 0) {
      return '${difference.inDays} day${difference.inDays == 1 ? '' : 's'} ago';
    } else if (difference.inHours > 0) {
      return '${difference.inHours} hour${difference.inHours == 1 ? '' : 's'} ago';
    } else if (difference.inMinutes > 0) {
      return '${difference.inMinutes} minute${difference.inMinutes == 1 ? '' : 's'} ago';
    } else {
      return 'Just now';
    }
  }
}

// Data models
class ExamTimetable {
  final String timetableId;
  final String title;
  final String description;
  final String status;
  final String academicYear;
  final String semester;
  final String uploadedAt;
  final List<Exam> exams;

  ExamTimetable({
    required this.timetableId,
    required this.title,
    required this.description,
    required this.status,
    required this.academicYear,
    required this.semester,
    required this.uploadedAt,
    required this.exams,
  });

  factory ExamTimetable.fromJson(Map<String, dynamic> json) {
    return ExamTimetable(
      timetableId: json['timetable_id'].toString(),
      title: json['title'] ?? '',
      description: json['description'] ?? '',
      status: json['status'] ?? '',
      academicYear: json['academic_year'] ?? '',
      semester: json['semester'] ?? '',
      uploadedAt: json['uploaded_at'] ?? '',
      exams: (json['exams'] as List?)
              ?.map((e) => Exam.fromJson(e))
              .toList() ??
          [],
    );
  }
}

class Exam {
  final String examId;
  final String moduleName;
  final String moduleCode;
  final String examDate;
  final String startTime;
  final String? endTime;
  final String venue;
  final int durationMinutes;
  final String examType;
  final String instructions;

  Exam({
    required this.examId,
    required this.moduleName,
    required this.moduleCode,
    required this.examDate,
    required this.startTime,
    this.endTime,
    required this.venue,
    required this.durationMinutes,
    required this.examType,
    required this.instructions,
  });

  factory Exam.fromJson(Map<String, dynamic> json) {
    return Exam(
      examId: json['exam_id'].toString(),
      moduleName: json['module_name'] ?? '',
      moduleCode: json['module_code'] ?? '',
      examDate: json['exam_date'] ?? '',
      startTime: json['start_time'] ?? '',
      endTime: json['end_time'],
      venue: json['venue'] ?? '',
      durationMinutes: json['duration_minutes'] ?? 0,
      examType: json['exam_type'] ?? '',
      instructions: json['instructions'] ?? '',
    );
  }
}

class ExamNotification {
  final String notificationId;
  final String type;
  final String title;
  final String message;
  final String timetableTitle;
  final String timetableStatus;
  final String createdAt;

  ExamNotification({
    required this.notificationId,
    required this.type,
    required this.title,
    required this.message,
    required this.timetableTitle,
    required this.timetableStatus,
    required this.createdAt,
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
    );
  }
}
