
import 'package:flutter/material.dart';
import '../models/student.dart';
import '../models/study_session.dart';
import '../models/module.dart';
import '../screens/create_session_screen.dart';
import '../screens/study_timer_screen.dart';
import '../screens/edit_session_screen.dart';
import '../services/notification_service.dart';
import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import '../widgets/skeleton_loader.dart';
import '../widgets/empty_state.dart';
import '../widgets/loading_indicators.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../services/study_session_service.dart';
import '../config/app_colors.dart';


class StudySessionsScreen extends StatefulWidget {
  final Student student;
  final List<StudySession> studySessions;
  final List<Module> studentModules;

  const StudySessionsScreen({
    Key? key,
    required this.student,
    required this.studySessions,
    required this.studentModules,
  }) : super(key: key);

  @override
  State<StudySessionsScreen> createState() => _StudySessionsScreenState();
}

class _StudySessionsScreenState extends State<StudySessionsScreen> {
  String selectedFilter = 'All';
  final List<String> filters = ['All', 'Study', 'Revision', 'Assignment', 'Exam Prep'];
  
  // Local list to manage sessions independently
  List<StudySession> _localStudySessions = [];
  
  // View mode: 'list' or 'grid'


  List<StudySession> get filteredSessions {
    if (selectedFilter == 'All') {
      return _localStudySessions;
    }
    return _localStudySessions
        .where((session) => session.sessionType.toLowerCase() == selectedFilter.toLowerCase())
        .toList();
  }

  int _weeklyMinutes() {
    final now = DateTime.now();
    final startOfWeek = now.subtract(Duration(days: now.weekday % 7));
    int minutes = 0;
    for (final s in _localStudySessions) {
      // naive sum using duration field if present, else derive from time
      final dur = s.duration ?? _diffMinutes(s.startTime, s.endTime);
      // Ideally check s.date in current week; if no date, include all
      minutes += dur;
    }
    return minutes;
  }

  int _diffMinutes(String start, String end) {
    try {
      final sp = start.split(':');
      final ep = end.split(':');
      final startMins = int.parse(sp[0]) * 60 + int.parse(sp[1]);
      final endMins = int.parse(ep[0]) * 60 + int.parse(ep[1]);
      return (endMins - startMins).clamp(0, 24 * 60);
    } catch (_) {
      return 0;
    }
  }

  Widget _templateChip(String label, IconData icon, Color color, String type, int minutes) {
    return GestureDetector(
      onTap: () async {
        final created = await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => CreateSessionScreen(
              student: widget.student,
              studentModules: widget.studentModules,
              // Prefill via optional named args if supported; otherwise just open
            ),
          ),
        );
        if (created != null) {
          await _loadStudySessions();
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('$label created'), duration: const Duration(seconds: 1)),
          );
        }
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.18),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: color.withValues(alpha: 0.35), width: 1),
        ),
        child: Row(
          children: [
            Icon(icon, color: Colors.white, size: 16),
            const SizedBox(width: 8),
            Text(
              label,
              style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void initState() {
    super.initState();
    // Initialize with passed sessions and then load fresh data
    _localStudySessions = List.from(widget.studySessions);
    _loadStudySessions();
    _scheduleNotifications();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Refresh data when screen becomes visible
    _loadStudySessions();
  }

  // Load study sessions from the service
  Future<void> _loadStudySessions() async {
    try {
      debugPrint('Loading study sessions for student ID: ${widget.student.studentId}');
      final loadedSessions = await StudySessionService.getStudySessions(widget.student.studentId);
      
      debugPrint('Raw loaded sessions: $loadedSessions');
      
      setState(() {
        _localStudySessions = loadedSessions;
      });
      
      debugPrint('Loaded ${loadedSessions.length} study sessions from service');
      debugPrint('Local sessions after setState: ${_localStudySessions.length}');
    } catch (e) {
      debugPrint('Error loading study sessions: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: AppColors.backgroundGradient,
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              _buildHeader(),
              _buildFilterTabs(),
              Expanded(
                child: RefreshIndicator(
                  color: Colors.white,
                  backgroundColor: Colors.blue,
                  onRefresh: _loadStudySessions,
                  child: _buildSessionsList(scrollable: true),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          // Stats row
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.surface.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.12), width: 1),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('This week', style: TextStyle(color: Colors.white70, fontSize: 11)),
                      const SizedBox(height: 4),
                      Text(
                        '${_weeklyMinutes() ~/ 60}h ${_weeklyMinutes() % 60}m',
                        style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.surface.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.12), width: 1),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Sessions', style: TextStyle(color: Colors.white70, fontSize: 11)),
                      const SizedBox(height: 4),
                      Text(
                        '${_localStudySessions.length} total',
                        style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 12),
          
          // Top row with back button and title
          Row(
            children: [
              GlassButton(
                onPressed: () => Navigator.pop(context),
                padding: const EdgeInsets.all(8),
                borderRadius: 20,
                child: const Icon(
                  Icons.arrow_back,
                  color: Colors.white,
                  size: 20,
                ),
              ),
              
              const SizedBox(width: 12),
              
              const Expanded(
                child: Text(
                  'Study Sessions',
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 8),
          
          // Subtitle
          const Text(
            'Personal study planning',
            style: TextStyle(
              fontSize: 12,
              color: Colors.white70,
            ),
          ),
          
          const SizedBox(height: 12),
          
          // Quick templates
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.only(bottom: 8),
            child: Row(
              children: [
                _templateChip('Revision 45m', Icons.refresh, Colors.green, 'Revision', 45),
                const SizedBox(width: 8),
                _templateChip('Assignment 90m', Icons.assignment, Colors.orange, 'Assignment', 90),
                const SizedBox(width: 8),
                _templateChip('Exam Prep 120m', Icons.quiz, Colors.red, 'Exam Prep', 120),
                const SizedBox(width: 8),
                _templateChip('Study 60m', Icons.school, Colors.blue, 'Study', 60),
              ],
            ),
          ),
          
          // Add button (keep UI minimal for mobile)
          Align(
            alignment: Alignment.centerRight,
            child: GlassButton(
              onPressed: () async {
                final result = await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => CreateSessionScreen(
                      student: widget.student,
                      studentModules: widget.studentModules,
                    ),
                  ),
                );
                
                if (result != null) {
                  await _loadStudySessions();
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Session created!'),
                      backgroundColor: Colors.green,
                      duration: Duration(seconds: 1),
                    ),
                  );
                }
              },
              padding: const EdgeInsets.all(8),
              borderRadius: 20,
              child: const Icon(
                Icons.add,
                color: Colors.white,
                size: 20,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterTabs() {
    return Container(
      height: 40,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        itemCount: filters.length,
        itemBuilder: (context, index) {
          final filter = filters[index];
          final isSelected = filter == selectedFilter;
          
          return GestureDetector(
            onTap: () {
              setState(() {
                selectedFilter = filter;
              });
            },
            child: Container(
              margin: const EdgeInsets.only(right: 12),
              child: GlassButton(
                isSelected: isSelected,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                borderRadius: 20,
                child: Text(
                  filter,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: isSelected ? Colors.white : Colors.white70,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildInfoNote() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: Colors.blue.withValues(alpha: 0.2),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: Colors.blue.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Icon(
            Icons.info_outline,
            color: Colors.blue[300],
            size: 16,
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              'Personal study sessions (separate from university timetable)',
              style: TextStyle(
                fontSize: 11,
                color: Colors.blue[300],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSessionsList({bool scrollable = false}) {
    debugPrint('Building sessions list. Filtered sessions count: ${filteredSessions.length}');
    debugPrint('Local sessions count: ${_localStudySessions.length}');
    debugPrint('Selected filter: $selectedFilter');
    
    if (filteredSessions.isEmpty) {
      final child = Center(
        child: EmptyStudySessionsState(
          onCreateSession: () async {
            final result = await Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => CreateSessionScreen(
                  student: widget.student,
                  studentModules: widget.studentModules,
                ),
              ),
            );
            
            if (result != null) {
              await _loadStudySessions();
            }
          },
        ),
      );
      // Ensure scrollable to make RefreshIndicator work in empty state
      return scrollable ? ListView(children: [const SizedBox(height: 60), child]) : child;
    }

    // Just use list view - simpler and better for mobile
    return _buildListView();
  }

  // Build list view
  Widget _buildListView() {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: filteredSessions.length,
      itemBuilder: (context, index) {
        final session = filteredSessions[index];
        return _buildSessionCard(session);
      },
    );
  }



  Widget _buildSessionCard(StudySession session) {
    final sessionTypeColors = {
      'study': Colors.blue,
      'revision': Colors.green,
      'assignment': Colors.orange,
      'exam_prep': Colors.red,
    };

    final sessionTypeIcons = {
      'study': Icons.school,
      'revision': Icons.refresh,
      'assignment': Icons.assignment,
      'exam_prep': Icons.quiz,
    };

    final color = sessionTypeColors[session.sessionType] ?? Colors.blue;
    final icon = sessionTypeIcons[session.sessionType] ?? Icons.school;

    return GlassCard(
      padding: const EdgeInsets.all(16),
      margin: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header with title and actions
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  icon,
                  color: color,
                  size: 18,
                ),
              ),
              
              const SizedBox(width: 12),
              
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            session.title,
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                              color: Colors.white,
                            ),
                          ),
                        ),
                        if (session.isAutoGenerated) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Colors.purple.withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(
                                color: Colors.purple.withValues(alpha: 0.5),
                                width: 1,
                              ),
                            ),
                            child: const Text(
                              'AI',
                              style: TextStyle(
                                color: Colors.purple,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${session.moduleCode} • ${session.sessionType.toUpperCase()}',
                      style: TextStyle(
                        fontSize: 12,
                        color: color,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
              
              // Action buttons
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    onPressed: () => _showSessionDetails(session),
                    icon: const Icon(
                      Icons.visibility,
                      color: Colors.white70,
                      size: 18,
                    ),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
                  IconButton(
                    onPressed: () => _editSession(session),
                    icon: const Icon(
                      Icons.edit,
                      color: Colors.white70,
                      size: 18,
                    ),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
                  IconButton(
                    onPressed: () => _deleteSession(session),
                    icon: const Icon(
                      Icons.delete,
                      color: Colors.red,
                      size: 18,
                    ),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
                ],
              ),
            ],
          ),
          
          const SizedBox(height: 12),
          
          // Time and day info
          Row(
            children: [
              Icon(
                Icons.access_time,
                color: Colors.white70,
                size: 16,
              ),
              const SizedBox(width: 6),
              Text(
                '${session.startTime} - ${session.endTime}',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
              
              const SizedBox(width: 16),
              
              Icon(
                Icons.calendar_today,
                color: Colors.white70,
                size: 16,
              ),
              const SizedBox(width: 6),
              Text(
                session.dayOfWeek,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
          
          if (session.venue != null) ...[
            SizedBox(height: 12),
            Row(
              children: [
                Icon(
                  Icons.location_on,
                  color: Colors.white70,
                  size: 18,
                ),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    session.venue!,
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ),
          ],
          
          if (session.notes != null && session.notes!.isNotEmpty) ...[
            SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
                             decoration: BoxDecoration(
                 color: Colors.white.withValues(alpha: 0.1),
                 borderRadius: BorderRadius.circular(8),
               ),
              child: Row(
                children: [
                  Icon(
                    Icons.note,
                    color: Colors.white70,
                    size: 18,
                  ),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      session.notes!,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          
          SizedBox(height: 16),
          
          // Action buttons
          Row(
            children: [
              // Edit button
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () async {
                    final result = await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => EditSessionScreen(
                          student: widget.student,
                          session: session,
                          studentModules: widget.studentModules,
                        ),
                      ),
                    );
                    
                    // Handle the result from edit screen
                    if (result != null) {
                      if (result == 'deleted') {
                        // Session was deleted, refresh the parent screen
                        Navigator.pop(context, true);
                      } else {
                        // Session was updated, refresh the parent screen
                        Navigator.pop(context, true);
                      }
                    }
                  },
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.white,
                    side: const BorderSide(color: Colors.white30),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  icon: const Icon(Icons.edit, size: 18),
                  label: const Text('Edit'),
                ),
              ),
              
              SizedBox(width: 12),
              
              // Delete button
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _showDeleteConfirmation(session),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.red,
                    side: const BorderSide(color: Colors.red),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  icon: const Icon(Icons.delete, size: 18),
                  label: const Text('Delete'),
                ),
              ),
              
              SizedBox(width: 12),
              
              // Start button
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => StudyTimerScreen(
                          studySession: session,
                        ),
                      ),
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: color,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  icon: const Icon(Icons.play_arrow, size: 18),
                  label: const Text('Start'),
                ),
              ),
            ],
          ),
          
          if (session.isAutoGenerated) ...[
            SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                             decoration: BoxDecoration(
                 color: Colors.orange.withValues(alpha: 0.2),
                 borderRadius: BorderRadius.circular(8),
               ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.auto_awesome,
                    color: Colors.orange,
                    size: 14,
                  ),
                  SizedBox(width: 4),
                  Text(
                    'Auto-generated',
                    style: TextStyle(
                      color: Colors.orange,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }



  // Build grid card for sessions (keeping for reference)
  Widget _buildSessionGridCard(StudySession session) {
    final sessionTypeColors = {
      'study': Colors.blue,
      'revision': Colors.green,
      'assignment': Colors.orange,
      'exam_prep': Colors.red,
    };

    final sessionTypeIcons = {
      'study': Icons.school,
      'revision': Icons.refresh,
      'assignment': Icons.assignment,
      'exam_prep': Icons.quiz,
    };

    final color = sessionTypeColors[session.sessionType] ?? Colors.blue;
    final icon = sessionTypeIcons[session.sessionType] ?? Icons.school;

    return GestureDetector(
      onTap: () => _showSessionDetails(session),
      child: GlassCard(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header with icon and type
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Icon(
                    icon,
                    color: color,
                    size: 16,
                  ),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    session.sessionType.toUpperCase(),
                    style: TextStyle(
                      color: color,
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
            
            const SizedBox(height: 8),
            
            // Title
            Text(
              session.title,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.bold,
                color: Colors.white,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            
            const SizedBox(height: 6),
            
            // Module
            Text(
              session.moduleCode,
              style: const TextStyle(
                color: Colors.white70,
                fontSize: 12,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            
            const SizedBox(height: 6),
            
            // Time and day
            Row(
              children: [
                Icon(
                  Icons.access_time,
                  color: Colors.white70,
                  size: 12,
                ),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    '${session.startTime} - ${session.endTime}',
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 10,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            
            const SizedBox(height: 2),
            
            Row(
              children: [
                Icon(
                  Icons.calendar_today,
                  color: Colors.white70,
                  size: 12,
                ),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    session.dayOfWeek,
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 10,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            
            const Spacer(),
            
            // Action buttons
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                IconButton(
                  onPressed: () => _showSessionDetails(session),
                  icon: const Icon(
                    Icons.info_outline,
                    color: Colors.white70,
                    size: 16,
                  ),
                  tooltip: 'View Details',
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(),
                ),
                IconButton(
                  onPressed: () => _showDeleteConfirmation(session),
                  icon: const Icon(
                    Icons.delete,
                    color: Colors.red,
                    size: 16,
                  ),
                  tooltip: 'Delete',
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // Show session details in a dialog
  void _showSessionDetails(StudySession session) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.blue[800],
        title: Text(
          session.title,
          style: const TextStyle(color: Colors.white),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildDetailRow(Icons.school, 'Module', session.moduleCode),
            const SizedBox(height: 8),
            _buildDetailRow(Icons.access_time, 'Time', '${session.startTime} - ${session.endTime}'),
            const SizedBox(height: 8),
            _buildDetailRow(Icons.calendar_today, 'Day', session.dayOfWeek),
            if (session.venue != null) ...[
              const SizedBox(height: 8),
              _buildDetailRow(Icons.location_on, 'Venue', session.venue!),
            ],
            if (session.notes != null && session.notes!.isNotEmpty) ...[
              const SizedBox(height: 8),
              _buildDetailRow(Icons.note, 'Notes', session.notes!),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text(
              'Close',
              style: TextStyle(color: Colors.white70),
            ),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              _editSession(session);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: Colors.blue,
            ),
            child: const Text('Edit'),
          ),
        ],
      ),
    );
  }

  // Edit session
  void _editSession(StudySession session) async {
    try {
      final result = await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => EditSessionScreen(
            student: widget.student,
            session: session,
            studentModules: widget.studentModules,
          ),
        ),
      );
      
      if (result != null) {
        await _loadStudySessions();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Study session updated successfully!'),
            backgroundColor: Colors.blue,
            duration: Duration(seconds: 2),
          ),
        );
      }
    } catch (e) {
      debugPrint('Error editing session: $e');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error opening edit screen: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  // Build detail row for dialog
  Widget _buildDetailRow(IconData icon, String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          icon,
          color: Colors.white70,
          size: 18,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  color: Colors.white70,
                  fontSize: 12,
                ),
              ),
              Text(
                value,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }



  // Show notification settings
  void _showNotificationSettings() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.grey[900],
        title: const Text(
          'Notification Settings',
          style: TextStyle(color: Colors.white),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Manage study session reminders',
              style: TextStyle(color: Colors.white70),
            ),
            const SizedBox(height: 20),
            Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () async {
                          Navigator.pop(context);
                          await _rescheduleAllNotifications();
                        },
                        icon: const Icon(Icons.schedule),
                        label: const Text('Reschedule All'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.blue,
                          foregroundColor: Colors.white,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () async {
                          Navigator.pop(context);
                          await _cancelAllNotifications();
                        },
                        icon: const Icon(Icons.notifications_off),
                        label: const Text('Cancel All'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.red,
                          foregroundColor: Colors.white,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 10),
            ElevatedButton.icon(
              onPressed: () async {
                Navigator.pop(context);
                await _testNotification();
              },
              icon: const Icon(Icons.bug_report),
              label: const Text('Test Notification'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close', style: TextStyle(color: Colors.white70)),
          ),
        ],
      ),
    );
  }

  // Reschedule all notifications
  Future<void> _rescheduleAllNotifications() async {
    try {
      await StudySessionService.rescheduleAllNotifications(widget.student.studentId);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('All notifications rescheduled!'),
          backgroundColor: Colors.green,
          duration: Duration(seconds: 2),
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  // Cancel all notifications
  Future<void> _cancelAllNotifications() async {
    try {
      await StudySessionService.cancelAllNotifications(widget.student.studentId);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('All notifications cancelled!'),
          backgroundColor: Colors.orange,
          duration: Duration(seconds: 2),
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  // Test notification
  Future<void> _testNotification() async {
    try {
      NotificationService.showTestNotification(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Test notification sent!'),
          backgroundColor: Colors.green,
          duration: Duration(seconds: 2),
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  // Schedule notifications for all sessions
  Future<void> _scheduleNotifications() async {
    try {
      await StudySessionService.rescheduleAllNotifications(widget.student.studentId);
      debugPrint('Scheduled notifications for study sessions');
    } catch (e) {
      debugPrint('Error scheduling notifications: $e');
    }
  }



  void _showDeleteConfirmation(StudySession session) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.red[800],
        title: const Text(
          'Delete Study Session',
          style: TextStyle(color: Colors.white),
        ),
        content: Text(
          'Are you sure you want to delete "${session.title}"? This action cannot be undone.',
          style: const TextStyle(color: Colors.white70),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text(
              'Cancel',
              style: TextStyle(color: Colors.white70),
            ),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              await _deleteSession(session);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: Colors.red,
            ),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
  }

  Future<void> _deleteSession(StudySession session) async {
    try {
      // Show loading indicator
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(
          child: CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
          ),
        ),
      );

      // Delete the session locally
      await _deleteStudySessionLocally(session);

      // Close loading dialog
      Navigator.pop(context);

      // Show success message
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Study session "${session.title}" deleted successfully!'),
          backgroundColor: Colors.green,
          duration: const Duration(seconds: 3),
        ),
      );

      // Don't navigate away - stay on the study sessions screen
    } catch (e) {
      // Close loading dialog
      Navigator.pop(context);

      // Show error message
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error deleting session: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  // Delete study session locally using SharedPreferences
  Future<void> _deleteStudySessionLocally(StudySession session) async {
    try {
      // Use the StudySessionService for reliable deletion
      final success = await StudySessionService.deleteStudySession(widget.student.studentId, session);
      
      if (!success) {
        throw Exception('Failed to delete study session');
      }
      
      // Reload sessions to get updated data
      await _loadStudySessions();
      
      debugPrint('Study session deleted successfully. Total sessions: ${_localStudySessions.length}');
    } catch (e) {
      debugPrint('Error deleting study session: $e');
      throw Exception('Failed to delete study session: $e');
    }
  }
}

