import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../models/student.dart';
import '../models/module.dart';
import '../models/study_session.dart';
import '../services/api_service.dart';
import '../services/study_session_service.dart';
import '../services/export_service.dart';
import '../services/ai_suggestion_service.dart';
import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import '../widgets/skeleton_loader.dart';
import '../widgets/empty_state.dart';
import '../widgets/loading_indicators.dart';
import '../config/app_colors.dart';
import 'session.dart';
import 'study_sessions_screen.dart';
import 'create_session_screen.dart';
import 'settings_screen.dart';
import 'exam_timetable_screen.dart';
import 'study_timer_screen.dart';
import 'study_plan_screen.dart';
import 'timetable_screen.dart';
import 'outline_upload_screen.dart';
import '../models/outline_event.dart';
import '../services/local_storage_service.dart';
import 'deadlines_screen.dart';

class DashboardScreen extends StatefulWidget {
  final Student student;

  const DashboardScreen({
    Key? key,
    required this.student,
  }) : super(key: key);

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> with TickerProviderStateMixin {
  // Data
  Map<String, Map<String, List<Session>>> timetableData = {};
  List<Module> studentModules = [];
  List<StudySession> studySessions = [];
  List<OutlineEvent> outlineEvents = [];
  final _storageService = LocalStorageService();
  
  // AI Suggestion
  Map<String, dynamic>? aiSuggestion;
  
  bool isLoading = true;
  String? errorMessage;
  
  // UI State
  String selectedDay = 'Monday';
  List<String> days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
  String selectedView = 'Today'; // 'Today' or 'Week'
  bool isOfflineMode = false;
  bool hasLocalData = false;
  int _selectedIndex = 0; // Bottom nav index
  
  // Search state
  String searchQuery = '';
  List<Session> filteredSessions = [];
  bool isSearching = false;
  
  // Animation controllers
  late AnimationController _progressController;
  late AnimationController _fadeController;
  late Animation<double> _fadeAnimation;
  
  // Weekly progress
  int completedStudySessions = 0;
  int totalStudySessions = 0;
  
  // Exam notifications
  int examNotificationCount = 0;

  @override
  void initState() {
    super.initState();
    _initializeAnimations();
    _loadData();
    _setTodayAsDefault();
    _loadStudySessions();
    _loadExamNotifications();
  }

  void _initializeAnimations() {
    _progressController = AnimationController(
      duration: const Duration(milliseconds: 1500),
      vsync: this,
    );
    
    _fadeController = AnimationController(
      duration: const Duration(milliseconds: 800),
      vsync: this,
    );
    
    _fadeAnimation = Tween<double>(
      begin: 0.0,
      end: 1.0,
    ).animate(CurvedAnimation(
      parent: _fadeController,
      curve: Curves.easeInOut,
    ));
  }

  @override
  void dispose() {
    _progressController.dispose();
    _fadeController.dispose();
    super.dispose();
  }

  void _setTodayAsDefault() {
    final today = DateTime.now();
    final weekdayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    final currentWeekday = weekdayNames[today.weekday - 1];
    
    setState(() {
      selectedDay = currentWeekday;
    });
  }

  Future<void> _loadData() async {
    try {
      setState(() {
        isLoading = true;
        errorMessage = null;
      });

      // Load timetable data
      await _loadTimetableData();
      
      // Load student modules
      await _loadStudentModules();
      
      // Load study sessions
      await _loadStudySessions();
      
      // Generate AI suggestion
      await _generateAISuggestion();
      
      await _loadStudySessions();
      await _loadExamNotifications();
      await _loadOutlineEvents();
      
      // Calculate weekly progress
      _calculateWeeklyProgress();
      
      setState(() {
        isLoading = false;
      });
      
      // Start animations
      _fadeController.forward();
      _progressController.forward();
      
    } catch (e) {
      setState(() {
        isLoading = false;
        errorMessage = 'Failed to load data: $e';
      });
    }
  }

  Future<void> _loadStudentModules() async {
    try {
      final studentId = widget.student.studentId;
      debugPrint('Loading student modules for ID: $studentId');
      
      if (studentId < 0) {
        debugPrint('ERROR: Invalid student ID: $studentId');
        setState(() {
          errorMessage = 'Invalid student ID. Please log out and log in again.';
        });
        return;
      }
      
      final response = await ApiService.getStudentModules(studentId);
      debugPrint('Modules API response: success=${response['success']}, message=${response['message']}');
      
      if (response['success'] == true) {
        final modulesList = response['modules'] as List?;
        if (modulesList != null) {
          setState(() {
            studentModules = modulesList
                .map((json) => Module.fromJson(json))
                .toList();
          });
          debugPrint('Loaded ${studentModules.length} student modules');
          for (final module in studentModules) {
            debugPrint('Module: ${module.moduleCode} - ${module.moduleName}');
          }
        } else {
          debugPrint('WARNING: Modules list is null in response');
          setState(() {
            studentModules = [];
          });
        }
      } else {
        debugPrint('Failed to load student modules: ${response['message']}');
        setState(() {
          errorMessage = 'Failed to load modules: ${response['message']}';
        });
      }
    } catch (e) {
      debugPrint('Error loading student modules: $e');
      setState(() {
        errorMessage = 'Error loading modules: $e';
      });
    }
  }

  Future<void> _loadTimetableData() async {
    try {
      final studentId = widget.student.studentId;
      debugPrint('Loading timetable for student ID: $studentId');
      
      if (studentId < 0) {
        debugPrint('ERROR: Invalid student ID: $studentId');
        setState(() {
          errorMessage = 'Invalid student ID. Please log out and log in again.';
        });
        // Fallback to local data
        await _loadLocalData();
        return;
      }
      
      // Try to load from API first
      final response = await ApiService.getStudentTimetable(studentId);
      debugPrint('Timetable API response: success=${response['success']}, message=${response['message']}');
      
      if (response['success'] == true) {
        final timetableMap = response['timetable'] as Map<String, dynamic>?;
        if (timetableMap != null) {
          _processTimetableData(timetableMap);
          await _saveTimetableLocally(timetableMap);
          setState(() {
            isOfflineMode = false;
            hasLocalData = true;
          });
          debugPrint('Timetable loaded successfully');
        } else {
          debugPrint('WARNING: Timetable map is null in response');
          // Fallback to local data
          await _loadLocalData();
        }
      } else {
        debugPrint('Failed to load timetable: ${response['message']}');
        // Fallback to local data
        await _loadLocalData();
      }
    } catch (e) {
      debugPrint('Timetable fetch error: $e');
      // Fallback to local data
      await _loadLocalData();
    }
  }

  Future<void> _loadOutlineEvents() async {
    try {
      await _storageService.initialize();
      final events = _storageService.getOutlineEvents();
      setState(() {
        outlineEvents = events;
      });
      debugPrint('Loaded ${outlineEvents.length} outline events');
    } catch (e) {
      debugPrint('Error loading outline events: $e');
    }
  }

  Future<void> _loadStudySessions() async {
    try {
      final sessions = await StudySessionService.getStudySessions(widget.student.studentId);
      setState(() {
        studySessions = sessions;
      });
    } catch (e) {
      debugPrint('Error loading study sessions: $e');
    }
  }

  Future<void> _loadExamNotifications() async {
    try {
      final data = await ApiService.getStudentExamNotifications(widget.student.studentId);
      if (data['success'] == true) {
        final notificationData = data['notifications'] ?? data['data'] ?? [];
        final notifications = (notificationData as List)
            .where((n) => n['is_read'] == 0 || n['is_read'] == false)
            .toList();
        setState(() {
          examNotificationCount = notifications.length;
        });
      }
    } catch (e) {
      debugPrint('Error loading exam notifications: $e');
    }
  }

  void _processTimetableData(Map<String, dynamic> timetableMap) {
    final Map<String, Map<String, List<Session>>> processedData = {};
    
    // The API returns a map where keys are days and values are lists of sessions
    timetableMap.forEach((day, sessionsList) {
      if (sessionsList is List) {
        for (final sessionData in sessionsList) {
          final session = Session.fromJson(sessionData);
          final String normalizedDay = _normalizeDayName(day);
          final startTime = sessionData['start_time']?.toString() ?? '00:00';
          
          if (!processedData.containsKey(normalizedDay)) {
            processedData[normalizedDay] = {};
          }
          if (!processedData[normalizedDay]!.containsKey(startTime)) {
            processedData[normalizedDay]![startTime] = [];
          }
          processedData[normalizedDay]![startTime]!.add(session);
        }
      }
    });
    
    // Get current day
    final today = DateTime.now();
    final weekdayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    final currentWeekday = weekdayNames[today.weekday - 1];
    
    setState(() {
      timetableData = processedData;
      
      // Sort days in chronological order
      final dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
      days = processedData.keys.toList();
      days.sort((a, b) {
        final indexA = dayOrder.indexOf(a);
        final indexB = dayOrder.indexOf(b);
        if (indexA == -1) return 1;
        if (indexB == -1) return -1;
        return indexA.compareTo(indexB);
      });
      
      // Set to current day if it exists and has sessions, otherwise first available day
      if (days.contains(currentWeekday) &&
          processedData[currentWeekday] != null &&
          processedData[currentWeekday]!.isNotEmpty) {
        selectedDay = currentWeekday;
      } else if (days.isNotEmpty) {
        selectedDay = days.first;
      }
    });
  }

  // Normalize day names from various formats (same as original timetable)
  String _normalizeDayName(String day) {
    if (day.isEmpty || day.toLowerCase() == 'null' || day.toLowerCase() == 'unknown') {
      return 'Unknown';
    }
    
    // Convert to lowercase for comparison
    final lowerDay = day.toLowerCase().trim();
    
    // Handle various day name formats
    if (lowerDay.contains('mon') || lowerDay == 'monday' || lowerDay == 'mon') {
      return 'Monday';
    } else if (lowerDay.contains('tue') || lowerDay == 'tuesday' || lowerDay == 'tue') {
      return 'Tuesday';
    } else if (lowerDay.contains('wed') || lowerDay == 'wednesday' || lowerDay == 'wed') {
      return 'Wednesday';
    } else if (lowerDay.contains('thu') || lowerDay == 'thursday' || lowerDay == 'thu') {
      return 'Thursday';
    } else if (lowerDay.contains('fri') || lowerDay == 'friday' || lowerDay == 'fri') {
      return 'Friday';
    } else if (lowerDay.contains('sat') || lowerDay == 'saturday' || lowerDay == 'sat') {
      return 'Saturday';
    } else if (lowerDay.contains('sun') || lowerDay == 'sunday' || lowerDay == 'sun') {
      return 'Sunday';
    }
    
    // If we can't determine the day, return the original with first letter capitalized
    return day.substring(0, 1).toUpperCase() + day.substring(1).toLowerCase();
  }

  Future<void> _loadLocalData() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      
      final timetableKey = 'timetable_${widget.student.studentId}';
      final timetableJson = prefs.getString(timetableKey);
      if (timetableJson != null) {
        final timetableMap = jsonDecode(timetableJson) as Map<String, dynamic>;
        _processTimetableData(timetableMap);
      }
      
      setState(() {
        isOfflineMode = true;
        hasLocalData = timetableData.isNotEmpty;
      });
    } catch (e) {
      debugPrint('Error loading local data: $e');
    }
  }

  Future<void> _saveTimetableLocally(Map<String, dynamic> timetableMap) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final timetableKey = 'timetable_${widget.student.studentId}';
      await prefs.setString(timetableKey, jsonEncode(timetableMap));
    } catch (e) {
      debugPrint('Error saving timetable locally: $e');
    }
  }

  void _calculateWeeklyProgress() {
    final now = DateTime.now();
    final startOfWeek = now.subtract(Duration(days: now.weekday - 1));
    final endOfWeek = startOfWeek.add(const Duration(days: 6));
    
    // Count completed study sessions this week
    completedStudySessions = studySessions.where((session) {
      final sessionDate = DateTime.parse(session.createdAt.toIso8601String());
      return sessionDate.isAfter(startOfWeek.subtract(const Duration(days: 1))) &&
             sessionDate.isBefore(endOfWeek.add(const Duration(days: 1)));
    }).length;
    
    // Total study sessions for the week (including planned ones)
    totalStudySessions = completedStudySessions + 5; // Add some planned sessions
  }

  List<Session> getTodaySessions() {
    if (!timetableData.containsKey(selectedDay)) return [];
    
    // Get all sessions for the selected day
    final dayData = timetableData[selectedDay]!;
    final allSessions = <Session>[];
    
    // Flatten all time slots into a single list
    for (final timeSlot in dayData.values) {
      allSessions.addAll(timeSlot);
    }
    
    // Sort by start time
    allSessions.sort((a, b) {
      final timeA = a.startTime ?? '00:00';
      final timeB = b.startTime ?? '00:00';
      return timeA.compareTo(timeB);
    });
    
    return allSessions;
  }

  List<StudySession> getTodayStudySessions() {
    return studySessions.where((session) => session.dayOfWeek == selectedDay).toList();
  }

  // Search functionality
  void _performSearch(String query) {
    setState(() {
      searchQuery = query;
      isSearching = query.isNotEmpty;
    });
    
    if (query.isEmpty) {
      filteredSessions = [];
      return;
    }
    
    // Get all sessions from all days
    final allSessions = <Session>[];
    for (final day in timetableData.keys) {
      for (final timeSlot in timetableData[day]!.values) {
        allSessions.addAll(timeSlot);
      }
    }
    
    // Filter sessions based on search query
    filteredSessions = allSessions.where((session) {
      final queryLower = query.toLowerCase();
      return session.moduleName.toLowerCase().contains(queryLower) ||
             session.moduleCode.toLowerCase().contains(queryLower) ||
             session.lecturerName.toLowerCase().contains(queryLower) ||
             session.venueName.toLowerCase().contains(queryLower) ||
             (session.sessionType?.toLowerCase().contains(queryLower) ?? false);
    }).toList();
    
    // Sort by start time
    filteredSessions.sort((a, b) {
      final timeA = a.startTime ?? '00:00';
      final timeB = b.startTime ?? '00:00';
      return timeA.compareTo(timeB);
    });
  }

  String getGreeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good Morning';
    if (hour < 17) return 'Good Afternoon';
    return 'Good Evening';
  }

  String getTodayFocus() {
    final todaySessions = getTodaySessions();
    if (todaySessions.isEmpty) return 'No classes today - perfect for self-study!';
    
    final firstSession = todaySessions.first;
    return 'Focus: ${firstSession.moduleName} at ${firstSession.startTime}';
  }

  String getCurrentDayName() {
    final today = DateTime.now();
    final weekdayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    return weekdayNames[today.weekday - 1];
  }


  // Helper: get the next upcoming class for today
  Session? _getNextClass() {
    final todaySessions = getTodaySessions();
    if (todaySessions.isEmpty) return null;
    final now = DateTime.now();
    final nowMinutes = now.hour * 60 + now.minute;
    for (final s in todaySessions) {
      final parts = (s.startTime ?? '00:00').split(':');
      if (parts.length < 2) continue;
      final classMinutes =
          (int.tryParse(parts[0]) ?? 0) * 60 + (int.tryParse(parts[1]) ?? 0);
      if (classMinutes > nowMinutes) return s;
    }
    return null;
  }

  Widget _buildNextClassBanner() {
    // Only show on 'Today' view for the actual current day
    final today = DateTime.now();
    final weekday = [
      'Monday', 'Tuesday', 'Wednesday',
      'Thursday', 'Friday', 'Saturday', 'Sunday'
    ][today.weekday - 1];
    if (selectedView != 'Today' || selectedDay != weekday) return const SizedBox.shrink();

    final next = _getNextClass();
    final Color bannerColor;
    final IconData bannerIcon;
    final String bannerText;

    if (next == null) {
      bannerColor = Colors.green;
      bannerIcon = Icons.celebration;
      bannerText = 'No more classes today 🎉';
    } else {
      bannerColor = AppColors.primary;
      bannerIcon = Icons.schedule;
      bannerText =
          'Next: ${next.moduleName} at ${next.startTime} • ${next.venueName}';
    }

    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
      decoration: BoxDecoration(
        color: bannerColor.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
            color: bannerColor.withValues(alpha: 0.4), width: 1),
      ),
      child: Row(
        children: [
          Icon(bannerIcon, color: bannerColor, size: 16),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              bannerText,
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.9),
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBottomNavBar() {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border(
          top: BorderSide(
              color: Colors.white.withValues(alpha: 0.1), width: 1),
        ),
      ),
      child: BottomNavigationBar(
        currentIndex: _selectedIndex,
        onTap: (index) {
          if (index == _selectedIndex) return;
          if (index == 1) {
            // Navigate to dedicated timetable screen
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => TimetableScreen(student: widget.student),
              ),
            );
            return;
          }
          if (index == 2) {
            // Navigate to study sessions
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => StudySessionsScreen(
                  student: widget.student,
                  studySessions: studySessions,
                  studentModules: studentModules,
                ),
              ),
            );
            return;
          }
          if (index == 3) {
            // Navigate to settings
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => SettingsScreen(student: widget.student),
              ),
            );
            return;
          }
          setState(() => _selectedIndex = index);
        },
        type: BottomNavigationBarType.fixed,
        backgroundColor: Colors.transparent,
        selectedItemColor: AppColors.primary,
        unselectedItemColor: Colors.white38,
        selectedLabelStyle: const TextStyle(
            fontSize: 11, fontWeight: FontWeight.w600),
        unselectedLabelStyle: const TextStyle(fontSize: 11),
        elevation: 0,
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.home_outlined),
            activeIcon: Icon(Icons.home),
            label: 'Dashboard',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.calendar_month_outlined),
            activeIcon: Icon(Icons.calendar_month),
            label: 'Timetable',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.menu_book_outlined),
            activeIcon: Icon(Icons.menu_book),
            label: 'Study',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.settings_outlined),
            activeIcon: Icon(Icons.settings),
            label: 'Settings',
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      bottomNavigationBar: _buildBottomNavBar(),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: AppColors.backgroundGradient,
          ),
        ),
        child: SafeArea(
          child: isLoading
              ? _buildLoadingState()
              : FadeTransition(
                  opacity: _fadeAnimation,
                  child: Column(
                    children: [
                      _buildHeader(),
                      Expanded(
                        child: RefreshIndicator(
                          color: Colors.white,
                          backgroundColor: AppColors.primary,
                          onRefresh: () async {
                            await _loadData();
                            if (mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                    content: Text('Data refreshed')),
                              );
                            }
                          },
                          child: SingleChildScrollView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _buildTodaysScheduleCard(),
                                const SizedBox(height: 16),
                                _buildQuickActionsCard(),
                                const SizedBox(height: 16),
                                _buildUpcomingDeadlines(),
                                const SizedBox(height: 16),
                                _buildWeeklyProgressCard(),
                                const SizedBox(height: 16),
                                _buildWeeklyCalendarCard(),
                                const SizedBox(height: 16),
                                _buildAISuggestionCard(),
                                const SizedBox(height: 24),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
        ),
      ),
      floatingActionButton: _buildFloatingActionButton(),
      floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
    );
  }

  Widget _buildUpcomingDeadlines() {
    if (outlineEvents.isEmpty) return const SizedBox.shrink();
    
    // Sort events by date
    final sortedEvents = List<OutlineEvent>.from(outlineEvents);
    sortedEvents.sort((a, b) => a.date.compareTo(b.date));
    
    // Filter out past events
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final futureEvents = sortedEvents.where((e) => e.date.isAfter(today.subtract(const Duration(days: 1)))).toList();
    
    if (futureEvents.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Upcoming Deadlines',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              ),
              TextButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => DeadlinesScreen(modules: studentModules),
                    ),
                  ).then((_) => _loadOutlineEvents());
                },
                child: const Text('View All', style: TextStyle(color: AppColors.primary, fontSize: 13)),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        ...futureEvents.take(3).map((event) => _buildDeadlineCard(event)).toList(),
      ],
    );
  }

  Widget _buildDeadlineCard(OutlineEvent event) {
    final dateStr = DateFormat('MMM d').format(event.date);
    final isVerySoon = event.date.difference(DateTime.now()).inDays < 3;
    
    return GlassCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: _getEventColor(event.type).withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: _getEventColor(event.type).withValues(alpha: 0.3)),
            ),
            child: Icon(
              _getEventIcon(event.type),
              color: _getEventColor(event.type),
              size: 20,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  event.title,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                    fontSize: 14,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  '${event.moduleCode} • ${event.type}',
                  style: const TextStyle(color: Colors.white54, fontSize: 11),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                dateStr,
                style: TextStyle(
                  color: isVerySoon ? Colors.redAccent : Colors.white70,
                  fontWeight: FontWeight.bold,
                  fontSize: 13,
                ),
              ),
              if (isVerySoon)
                const Text(
                  'Urgent',
                  style: TextStyle(color: Colors.redAccent, fontSize: 9, fontWeight: FontWeight.bold),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Color _getEventColor(String type) {
    switch (type.toLowerCase()) {
      case 'test': return Colors.orangeAccent;
      case 'assignment': return Colors.blueAccent;
      case 'exam': return Colors.redAccent;
      case 'practical': return Colors.tealAccent;
      default: return Colors.white70;
    }
  }

  IconData _getEventIcon(String type) {
    switch (type.toLowerCase()) {
      case 'test': return Icons.quiz;
      case 'assignment': return Icons.assignment;
      case 'exam': return Icons.workspace_premium;
      case 'practical': return Icons.science;
      default: return Icons.event;
    }
  }

  Widget _buildLoadingState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          PulsingLoadingIndicator(
            message: 'Loading your schedule...',
            size: 50,
            color: Colors.white,
          ),
          const SizedBox(height: 32),
          // Skeleton cards for preview
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 32),
            child: Column(
              children: [
                SkeletonCard(),
                const SizedBox(height: 16),
                SkeletonCard(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          // Top row with profile and settings
          Row(
            children: [
              // Profile avatar
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(25),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.3),
                    width: 2,
                  ),
                ),
                child: const Icon(
                  Icons.person,
                  color: Colors.white,
                  size: 24,
                ),
              ),
              
              const SizedBox(width: 12),
              
              // User info
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${getGreeting()}, ${widget.student.fullName.split(' ').first} 👋',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    GestureDetector(
                      onTap: () {
                        final todaySessions = getTodaySessions();
                        if (todaySessions.isNotEmpty) {
                          _showModuleDetailsPopup(todaySessions.first);
                        }
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.2),
                            width: 1,
                          ),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                getTodayFocus(),
                                style: const TextStyle(
                                  color: Colors.white70,
                                  fontSize: 14,
                                ),
                                overflow: TextOverflow.ellipsis,
                                maxLines: 1,
                              ),
                            ),
                            const SizedBox(width: 8),
                            const Icon(
                              Icons.info_outline,
                              color: Colors.white70,
                              size: 16,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              
              // View toggle buttons
              Row(
                children: [
                  _buildViewToggleButton('Today', selectedView == 'Today'),
                  const SizedBox(width: 8),
                  _buildViewToggleButton('Week', selectedView == 'Week'),
                  const SizedBox(width: 12),
                  // Compact modules count chip
                  if (studentModules.isNotEmpty) ...[
                    GestureDetector(
                      onTap: _navigateToModules,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: Colors.deepPurple.withValues(alpha: 0.25),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: Colors.deepPurple.withValues(alpha: 0.35), width: 1),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.menu_book, color: Colors.white, size: 16),
                            const SizedBox(width: 6),
                            Text(
                              '${studentModules.length}',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                  ],
                  // Settings button
                  GlassButton(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => SettingsScreen(student: widget.student),
                        ),
                      );
                    },
                    padding: const EdgeInsets.all(8),
                    borderRadius: 20,
                    child: const Icon(
                      Icons.settings,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 8),
                ],
              ),
            ],
          ),
          
          const SizedBox(height: 16),
          
          // Search bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(25),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.3),
                width: 1,
              ),
            ),
            child: TextField(
              onChanged: _performSearch,
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(
                hintText: 'Search classes, lecturers, venues...',
                hintStyle: TextStyle(color: Colors.white.withValues(alpha: 0.7)),
                prefixIcon: Icon(
                  Icons.search,
                  color: Colors.white.withValues(alpha: 0.7),
                ),
                suffixIcon: isSearching
                    ? IconButton(
                        icon: Icon(
                          Icons.clear,
                          color: Colors.white.withValues(alpha: 0.7),
                        ),
                        onPressed: () {
                          _performSearch('');
                        },
                      )
                    : null,
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(vertical: 8),
              ),
            ),
          ),
          _buildNextClassBanner(),
        ],
      ),
    );
  }

  Widget _buildViewToggleButton(String label, bool isSelected) {
    return GestureDetector(
      onTap: () {
        setState(() {
          selectedView = label;
        });
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected 
              ? Colors.white.withValues(alpha: 0.3)
              : Colors.white.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(15),
          border: Border.all(
            color: isSelected 
                ? Colors.white.withValues(alpha: 0.5)
                : Colors.white.withValues(alpha: 0.2),
            width: 1,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: Colors.white,
            fontSize: 12,
            fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
          ),
        ),
      ),
    );
  }

  Widget _buildQuickActionsCard() {
    return GlassCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.orange.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.flash_on,
                  color: Colors.white,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'Quick Actions',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          
          // New Scan Outline feature
          _buildQuickActionButton(
            'Scan Outline',
            Icons.document_scanner,
            Colors.blue,
            () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => OutlineUploadScreen(modules: studentModules),
                ),
              ).then((_) => _loadOutlineEvents());
            },
          ),
          
          const SizedBox(height: 12),
          
          _buildQuickActionButton(
            'Study Sessions',
            Icons.book,
            Colors.purple,
            () => _navigateToStudySessions(),
          ),
          
          const SizedBox(height: 12),
          
          _buildQuickActionButton(
            'Add Study Session',
            Icons.add_circle,
            Colors.green,
            () => _navigateToCreateSession(),
          ),
          
          const SizedBox(height: 12),
          
          _buildQuickActionButtonWithBadge(
            'Exam Timetables',
            Icons.calendar_today,
            Colors.red,
            () => _navigateToExamTimetables(),
            examNotificationCount,
          ),
          
          const SizedBox(height: 12),
          
          _buildQuickActionButton(
            'View Modules',
            Icons.menu_book,
            Colors.deepPurple,
            () => _navigateToModules(),
          ),
          
          const SizedBox(height: 12),
          
          _buildQuickActionButton(
            'Export Schedule',
            Icons.share,
            Colors.orange,
            () => _exportSchedule(),
          ),
        ],
      ),
    );
  }

  Widget _buildTodaysScheduleCard() {
    if (selectedView == 'Week') {
      return _buildWeekView();
    }
    
    // Today view
    final todaySessions = getTodaySessions();
    final todayStudySessions = getTodayStudySessions();
    
    return Column(
      children: [
        // Day selection tabs
        _buildDaySelectionTabs(),
        
        const SizedBox(height: 16),
        
        // Schedule content
        GlassCard(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.blue.withValues(alpha: 0.3),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.schedule,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      '${selectedDay}\'s Schedule',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
              
              const SizedBox(height: 16),
              
              if (isSearching)
                _buildSearchResults()
              else if (todaySessions.isEmpty && todayStudySessions.isEmpty)
                _buildEmptySchedule()
              else
                Column(
                  children: [
                    // University classes
                    if (todaySessions.isNotEmpty) ...[
                      _buildSessionSection('University Classes', todaySessions, Icons.school),
                      if (todayStudySessions.isNotEmpty) const SizedBox(height: 16),
                    ],
                    // Study sessions
                    if (todayStudySessions.isNotEmpty)
                      _buildStudySessionSection('Study Sessions', todayStudySessions, Icons.book),
                  ],
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildDaySelectionTabs() {
    return Container(
      height: 50,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        itemCount: days.length,
        itemBuilder: (context, index) {
          final day = days[index];
          final isSelected = selectedDay == day;
          
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: GestureDetector(
              onTap: () {
                setState(() {
                  selectedDay = day;
                });
              },
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: isSelected 
                      ? Colors.white.withValues(alpha: 0.3)
                      : Colors.white.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(25),
                  border: Border.all(
                    color: isSelected 
                        ? Colors.white.withValues(alpha: 0.5)
                        : Colors.white.withValues(alpha: 0.2),
                    width: 1,
                  ),
                ),
                child: Center(
                  child: Text(
                    day,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 14,
                      fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
                    ),
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildWeekView() {
    return Column(
      children: [
        // Week overview header
        GlassCard(
          padding: const EdgeInsets.all(20),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.green.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.calendar_view_week,
                  color: Colors.white,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'Weekly Overview',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
        ),
        
        const SizedBox(height: 16),
        
        // Week grid
        ...days.map((day) => _buildWeekDayCard(day)),
      ],
    );
  }

  Widget _buildWeekDayCard(String day) {
    final daySessions = timetableData[day]?.values.expand((sessions) => sessions).toList() ?? [];
    
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: GlassCard(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  day,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: daySessions.isNotEmpty 
                        ? Colors.green.withValues(alpha: 0.3)
                        : Colors.grey.withValues(alpha: 0.3),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    '${daySessions.length} classes',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),
            if (daySessions.isNotEmpty) ...[
              const SizedBox(height: 8),
              ...daySessions.take(3).map((session) => Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: GestureDetector(
                  onTap: () => _showModuleDetailsPopup(session),
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 8),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(6),
                      color: Colors.white.withValues(alpha: 0.1),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 4,
                          height: 4,
                          decoration: const BoxDecoration(
                            color: Colors.white70,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            '${session.startTime} - ${session.moduleName}',
                            style: const TextStyle(
                              color: Colors.white70,
                              fontSize: 12,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        const Icon(
                          Icons.info_outline,
                          color: Colors.white70,
                          size: 14,
                        ),
                      ],
                    ),
                  ),
                ),
              )),
              if (daySessions.length > 3)
                Text(
                  '+${daySessions.length - 3} more',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.6),
                    fontSize: 11,
                  ),
                ),
            ] else
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'No classes',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.6),
                    fontSize: 12,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchResults() {
    if (filteredSessions.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(20),
        child: const Column(
          children: [
            Icon(
              Icons.search_off,
              color: Colors.white70,
              size: 48,
            ),
            SizedBox(height: 12),
            Text(
              'No results found',
              style: TextStyle(
                color: Colors.white70,
                fontSize: 16,
              ),
            ),
          ],
        ),
      );
    }
    
    return Column(
      children: [
        Text(
          'Search Results (${filteredSessions.length})',
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 12),
        ...filteredSessions.map((session) => _buildSessionItem(session)),
      ],
    );
  }

  Widget _buildEmptySchedule() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          const Icon(
            Icons.event_available,
            color: Colors.white70,
            size: 48,
          ),
          const SizedBox(height: 12),
          const Text(
            'No classes today!',
            style: TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Perfect time for self-study or relaxation',
            style: TextStyle(
              color: Colors.white70,
              fontSize: 14,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildSessionSection(String title, List<Session> sessions, IconData icon) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, color: Colors.white70, size: 16),
            const SizedBox(width: 8),
            Text(
              title,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ...sessions.map((session) => _buildSessionItem(session)),
      ],
    );
  }

  Widget _buildStudySessionSection(String title, List<StudySession> sessions, IconData icon) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, color: Colors.white70, size: 16),
            const SizedBox(width: 8),
            Text(
              title,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ...sessions.map((session) => _buildStudySessionItem(session)),
      ],
    );
  }

  Widget _buildSessionItem(Session session) {
    final sessionTypeColors = {
      'lecture': Colors.blue,
      'tutorial': Colors.orange,
      'practical': Colors.green,
      'lab': Colors.purple,
      'seminar': Colors.red,
      'workshop': Colors.amber,
    };
    
    final color = sessionTypeColors[session.sessionType?.toLowerCase()] ?? Colors.blue;
    
    return GestureDetector(
      onTap: () => _showModuleDetailsPopup(session),
      child: Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: color.withValues(alpha: 0.3),
          width: 1,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 4,
            height: 40,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  session.moduleName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  '${session.startTime} - ${session.endTime} • ${session.venueName}',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 12,
                  ),
                ),
                Text(
                  session.lecturerName,
                  style: const TextStyle(
                    color: Colors.white60,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          const Icon(
            Icons.info_outline,
            color: Colors.white60,
            size: 18,
          ),
        ],
      ),
    ),
    );
  }

  Widget _buildStudySessionItem(StudySession session) {
    final sessionTypeColors = {
      'study': Colors.blue,
      'revision': Colors.green,
      'assignment': Colors.orange,
      'exam_prep': Colors.red,
    };
    
    final color = sessionTypeColors[session.sessionType] ?? Colors.blue;
    
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: color.withValues(alpha: 0.3),
          width: 1,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 4,
            height: 40,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  session.title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  '${session.startTime} - ${session.endTime} • ${session.moduleCode}',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 12,
                  ),
                ),
                if (session.venue != null && session.venue!.isNotEmpty)
                  Text(
                    session.venue!,
                    style: const TextStyle(
                      color: Colors.white60,
                      fontSize: 11,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildWeeklyProgressCard() {
    final progress = totalStudySessions > 0 ? completedStudySessions / totalStudySessions : 0.0;
    
    return GlassCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.green.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.trending_up,
                  color: Colors.white,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'Weekly Progress',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 16),
          
          Row(
            children: [
              // Progress ring
              SizedBox(
                width: 60,
                height: 60,
                child: Stack(
                  children: [
                    CircularProgressIndicator(
                      value: progress,
                      strokeWidth: 6,
                      backgroundColor: Colors.white.withValues(alpha: 0.2),
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.green),
                    ),
                    Center(
                      child: Text(
                        '${(progress * 100).toInt()}%',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              
              const SizedBox(width: 16),
              
              // Progress details
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$completedStudySessions of $totalStudySessions sessions',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Study sessions completed this week',
                      style: const TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildWeeklyCalendarCard() {
    return GlassCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.purple.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.calendar_view_week,
                  color: Colors.white,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'This Week',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 16),
          
          // Weekly calendar view
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: days.map((day) => _buildDayCard(day)).toList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDayCard(String day) {
    final isSelected = day == selectedDay;
    final daySessions = timetableData[day]?.values.expand((sessions) => sessions).length ?? 0;
    
    return GestureDetector(
      onTap: () {
        setState(() {
          selectedDay = day;
        });
      },
      child: Container(
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: isSelected 
              ? Colors.white.withValues(alpha: 0.3)
              : Colors.white.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected 
                ? Colors.white.withValues(alpha: 0.5)
                : Colors.white.withValues(alpha: 0.2),
            width: 1,
          ),
        ),
        child: Column(
          children: [
            Text(
              day.substring(0, 3),
              style: TextStyle(
                color: isSelected ? Colors.white : Colors.white70,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              daySessions.toString(),
              style: TextStyle(
                color: isSelected ? Colors.white : Colors.white70,
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
            Text(
              'sessions',
              style: TextStyle(
                color: isSelected ? Colors.white70 : Colors.white60,
                fontSize: 10,
              ),
            ),
          ],
        ),
      ),
    );
  }

// Method was moved to the top of the file for better organization during refactor.

  Widget _buildQuickActionButton(String title, IconData icon, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: double.infinity, // Full width for mobile
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20), // Larger touch target
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.2),
          borderRadius: BorderRadius.circular(12), // Slightly rounded for modern look
          border: Border.all(
            color: color.withValues(alpha: 0.3),
            width: 1,
          ),
          boxShadow: [
            BoxShadow(
              color: color.withValues(alpha: 0.1),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row( // Horizontal layout for mobile
          children: [
            Icon(icon, color: Colors.white, size: 28), // Larger icon
            const SizedBox(width: 16),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 16, // Larger text for mobile
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const Icon(Icons.arrow_forward_ios, color: Colors.white70, size: 16), // Navigation indicator
          ],
        ),
      ),
    );
  }

  Widget _buildQuickActionButtonWithBadge(String title, IconData icon, Color color, VoidCallback onTap, int badgeCount) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: double.infinity, // Full width for mobile
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20), // Larger touch target
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.2),
          borderRadius: BorderRadius.circular(12), // Slightly rounded for modern look
          border: Border.all(
            color: color.withValues(alpha: 0.3),
            width: 1,
          ),
          boxShadow: [
            BoxShadow(
              color: color.withValues(alpha: 0.1),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row( // Horizontal layout for mobile
          children: [
            Stack(
              children: [
                Icon(icon, color: Colors.white, size: 28), // Larger icon
                if (badgeCount > 0)
                  Positioned(
                    right: -4,
                    top: -4,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: Colors.red,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.white, width: 1.5),
                      ),
                      constraints: const BoxConstraints(
                        minWidth: 18,
                        minHeight: 18,
                      ),
                      child: Text(
                        badgeCount > 99 ? '99+' : '$badgeCount',
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
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 16, // Larger text for mobile
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const Icon(Icons.arrow_forward_ios, color: Colors.white70, size: 16), // Navigation indicator
          ],
        ),
      ),
    );
  }

  Widget _buildAISuggestionCard() {
    return GlassCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.pink.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.auto_awesome,
                  color: Colors.white,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'AI Study Suggestion',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 16),
          
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '💡 ${aiSuggestion?['tip'] ?? 'Study Tip'}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  aiSuggestion?['suggestion'] ?? 'Review your notes and prepare for upcoming classes.',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
                
                // Enhanced features
                if (aiSuggestion?['motivation'] != null) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.green.withOpacity(0.2),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      aiSuggestion!['motivation'],
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                ],
                
                if (aiSuggestion?['insight'] != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    aiSuggestion!['insight'],
                    style: TextStyle(
                      color: Colors.blue.withOpacity(0.8),
                      fontSize: 11,
                      fontStyle: FontStyle.italic,
                    ),
                  ),
                ],
                
                if (aiSuggestion?['personalTip'] != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    aiSuggestion!['personalTip'],
                    style: TextStyle(
                      color: Colors.orange.withOpacity(0.8),
                      fontSize: 11,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(
                          Icons.schedule,
                          color: Colors.white70,
                          size: 16,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            aiSuggestion?['reasoning']?.contains('no timetable constraints') == true
                              ? 'Flexible study time: ${_formatTime(aiSuggestion?['suggestedTime'] ?? '14:00')} - ${_formatTime(_calculateEndTime(aiSuggestion?['suggestedTime'] ?? '14:00', aiSuggestion?['duration'] ?? '60'))} on ${aiSuggestion?['suggestedDay'] ?? 'Monday'}'
                              : 'Suggested time: ${_formatTime(aiSuggestion?['suggestedTime'] ?? '14:00')} - ${_formatTime(_calculateEndTime(aiSuggestion?['suggestedTime'] ?? '14:00', aiSuggestion?['duration'] ?? '60'))} on ${aiSuggestion?['suggestedDay'] ?? 'Monday'}',
                            style: const TextStyle(
                              color: Colors.white70,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (aiSuggestion?['reasoning'] != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        aiSuggestion!['reasoning'],
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.6),
                          fontSize: 10,
                          fontStyle: FontStyle.italic,
                        ),
                      ),
                    ],
                    
                    // Module-specific recommendations
                    if (aiSuggestion?['moduleRecommendations'] != null && (aiSuggestion!['moduleRecommendations'] as List).isNotEmpty) ...[
                      const SizedBox(height: 16),
                      const Divider(color: Colors.white24),
                      const SizedBox(height: 12),
                      const Text(
                        '📚 Module Study Plan',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 8),
                      ...(aiSuggestion!['moduleRecommendations'] as List).take(3).map((module) => 
                        Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.05),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: module['urgency'] == 'High' 
                                  ? Colors.red.withValues(alpha: 0.3)
                                  : Colors.blue.withValues(alpha: 0.3),
                              width: 1,
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: module['urgency'] == 'High' 
                                          ? Colors.red.withValues(alpha: 0.3)
                                          : module['urgency'] == 'Medium'
                                              ? Colors.orange.withValues(alpha: 0.3)
                                              : Colors.green.withValues(alpha: 0.3),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: Text(
                                      module['moduleCode'],
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      module['moduleName'],
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w500,
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  Text(
                                    '${module['recommendedTime']}min',
                                    style: const TextStyle(
                                      color: Colors.white70,
                                      fontSize: 10,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(
                                module['studyFocus'],
                                style: const TextStyle(
                                  color: Colors.white70,
                                  fontSize: 11,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                    
                    // Daily Study Plan
                    if (aiSuggestion?['dailyPlan'] != null) ...[
                      const SizedBox(height: 16),
                      const Divider(color: Colors.white24),
                      const SizedBox(height: 12),
                      const Text(
                        '📅 Weekly Study Plan',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 8),
                      ..._buildDailyPlanWidgets(),
                    ],
                    
                    const SizedBox(height: 8),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: () async {
                          await _createStudySessionFromSuggestion();
                        },
                        style: TextButton.styleFrom(
                          foregroundColor: Colors.pink,
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        ),
                        child: const Text(
                          'Add Full Week',
                          style: TextStyle(fontSize: 12),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFloatingActionButton() {
    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: AppColors.primaryGradient,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(25),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.4),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
          BoxShadow(
            color: AppColors.secondary.withValues(alpha: 0.2),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: FloatingActionButton.extended(
        onPressed: _showQuickActionSheet,
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        elevation: 0,
        icon: const Icon(Icons.add_rounded, size: 24),
        label: const Text(
          'Add Weekly Plan',
          style: TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 16,
            letterSpacing: 0.5,
          ),
        ),
      ),
    );
  }

  void _showQuickActionSheet() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (context) => Container(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.surface,
              AppColors.card,
            ],
          ),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(30)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.3),
              blurRadius: 20,
              offset: const Offset(0, -5),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Modern handle
            Container(
              width: 50,
              height: 5,
              margin: const EdgeInsets.symmetric(vertical: 16),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: AppColors.primaryGradient,
                ),
                borderRadius: BorderRadius.circular(3),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
              child: Column(
                children: [
                  const Text(
                    'Quick Actions',
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                      letterSpacing: -0.5,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Choose an action to get started',
                    style: TextStyle(
                      fontSize: 16,
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 20),
                  // Mobile-friendly vertical action layout
                  _buildActionSheetButton(
                    'View Schedule',
                    Icons.schedule,
                    AppColors.accent,
                    () {
                      Navigator.pop(context);
                      _navigateToStudySessions();
                    },
                  ),
                  const SizedBox(height: 12),
                  _buildActionSheetButton(
                    'Quick Study',
                    Icons.play_circle_filled,
                    Colors.green,
                    () {
                      Navigator.pop(context);
                      _checkForUpcomingSession();
                    },
                  ),
                  const SizedBox(height: 12),
                  _buildActionSheetButton(
                    'Create Session',
                    Icons.add_circle,
                    AppColors.secondary,
                    () {
                      Navigator.pop(context);
                      _navigateToCreateSession();
                    },
                  ),
                  const SizedBox(height: 12),
                  _buildActionSheetButton(
                    'Add Full Week',
                    Icons.auto_awesome,
                    AppColors.primary,
                    () {
                      Navigator.pop(context);
                      _createStudySessionFromSuggestion();
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionSheetButton(String title, IconData icon, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              color.withValues(alpha: 0.15),
              color.withValues(alpha: 0.05),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: color.withValues(alpha: 0.3),
            width: 1,
          ),
          boxShadow: [
            BoxShadow(
              color: color.withValues(alpha: 0.1),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [color, color.withValues(alpha: 0.8)],
                ),
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: color.withValues(alpha: 0.3),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Icon(icon, color: Colors.white, size: 24),
            ),
            const SizedBox(height: 12),
            Text(
              title,
              style: TextStyle(
                color: AppColors.textPrimary,
                fontSize: 16,
                fontWeight: FontWeight.bold,
                letterSpacing: 0.5,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  // Navigation methods
  void _navigateToCreateSession() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => CreateSessionScreen(
          student: widget.student,
          studentModules: studentModules,
        ),
      ),
    ).then((_) {
      _loadStudySessions();
      _generateAISuggestion(); // Regenerate AI suggestion after creating session
      _calculateWeeklyProgress();
    });
  }



  void _navigateToStudySessions() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => StudySessionsScreen(
          student: widget.student,
          studySessions: [], // Pass empty list - StudySessionsScreen will load fresh data
          studentModules: studentModules,
        ),
      ),
    ).then((_) {
      _loadStudySessions();
      _calculateWeeklyProgress();
    });
  }

  void _navigateToExamTimetables() async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => const ExamTimetableScreen(),
      ),
    );
    // Reload notifications when returning from exam timetable screen
    _loadExamNotifications();
  }

  void _navigateToModules() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => StudyPlanScreen(student: widget.student),
      ),
    );
  }

  void _exportSchedule() {
    ExportService.showExportOptions(
      context: context,
      student: widget.student,
      timetableData: timetableData,
      studySessions: studySessions,
    );
  }

  Future<void> _generateAISuggestion() async {
    try {
      print('Debug: Generating AI suggestion with timetable data: ${timetableData.keys}');
      print('Debug: Total sessions across all days: ${timetableData.values.map((day) => day.values.expand((sessions) => sessions).length).fold(0, (a, b) => a + b)}');
      
      // Get all unique modules from timetable
      final allModules = <String>{};
      for (final day in timetableData.values) {
        for (final timeSlot in day.values) {
          for (final session in timeSlot) {
            allModules.add('${session.moduleCode} - ${session.moduleName}');
          }
        }
      }
      print('Debug: All modules found for AI: ${allModules.toList()}');
      
      // Get user's study preference from SharedPreferences
      final prefs = await SharedPreferences.getInstance();
      final studyPreference = prefs.getString('study_preference') ?? 'balanced';
      // Study preference retrieved
      
      aiSuggestion = AISuggestionService.generateStudySuggestion(
        timetableData,
        studySessions,
        studyPreference: studyPreference,
      );
      
      print('Debug: AI suggestion generated: ${aiSuggestion?['suggestion']}');
      print('Debug: Module recommendations: ${aiSuggestion?['moduleRecommendations']?.length ?? 0} modules');
      print('Debug: Full AI suggestion data: $aiSuggestion');
    } catch (e) {
      print('Error generating AI suggestion: $e');
      // Fallback suggestion
      aiSuggestion = {
        'suggestion': 'Review your notes and prepare for upcoming classes.',
        'tip': 'Consistent study habits lead to better academic performance.',
        'suggestedDay': 'Monday',
        'suggestedTime': '14:00',
        'duration': '60',
        'priority': 'Medium',
        'reasoning': 'This time slot fits well with your schedule.',
      };
    }
  }

  String _calculateEndTime(String startTime, String duration) {
    try {
      final timeParts = startTime.split(':');
      final startHour = int.parse(timeParts[0]);
      final startMinute = int.parse(timeParts[1]);
      final durationMinutes = int.parse(duration);
      
      final totalMinutes = startHour * 60 + startMinute + durationMinutes;
      final endHour = (totalMinutes ~/ 60) % 24;
      final endMinute = totalMinutes % 60;
      
      return '${endHour.toString().padLeft(2, '0')}:${endMinute.toString().padLeft(2, '0')}';
    } catch (e) {
      return '15:00'; // Fallback
    }
  }

  Future<void> _createStudySessionFromSuggestion() async {
    if (aiSuggestion == null || aiSuggestion!['dailyPlan'] == null) return;
    
    // Show confirmation dialog
    final shouldAdd = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) {
        final dailyPlan = aiSuggestion!['dailyPlan'] as Map<String, dynamic>;
        int totalSessions = 0;
        for (final suggestions in dailyPlan.values) {
          totalSessions += (suggestions as List).length;
        }
        
        return AlertDialog(
          backgroundColor: Colors.grey[900],
          title: const Row(
            children: [
              Icon(Icons.schedule, color: Colors.blue),
              SizedBox(width: 8),
              Text('Add Weekly Study Plan', style: TextStyle(color: Colors.white)),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'This will add $totalSessions study sessions to your schedule:',
                style: const TextStyle(color: Colors.white70),
              ),
              const SizedBox(height: 12),
              ...dailyPlan.entries.map((entry) {
                final day = entry.key;
                final sessions = entry.value as List;
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Text(
                    '• $day: ${sessions.length} sessions',
                    style: const TextStyle(color: Colors.white60, fontSize: 14),
                  ),
                );
              }).toList(),
              const SizedBox(height: 12),
              const Text(
                'All sessions will be scheduled based on your study preferences.',
                style: TextStyle(color: Colors.green, fontSize: 12),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Cancel', style: TextStyle(color: Colors.white70)),
            ),
            ElevatedButton(
              onPressed: () => Navigator.of(context).pop(true),
              style: ElevatedButton.styleFrom(backgroundColor: Colors.blue),
              child: const Text('Add All Sessions', style: TextStyle(color: Colors.white)),
            ),
          ],
        );
      },
    );

    if (shouldAdd != true) return;
    
    try {
      final dailyPlan = aiSuggestion!['dailyPlan'] as Map<String, dynamic>;
      int totalSessionsAdded = 0;
      int failedSessions = 0;
      
      // Show loading indicator
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Row(
            children: [
              SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              ),
              SizedBox(width: 16),
              Text('Adding all study sessions to your schedule...'),
            ],
          ),
          backgroundColor: Colors.blue,
          duration: Duration(seconds: 2),
        ),
      );
      
      // Add all study sessions from the daily plan
      for (final dayEntry in dailyPlan.entries) {
        final day = dayEntry.key;
        final suggestions = dayEntry.value as List<dynamic>;
        
        for (final suggestion in suggestions) {
          try {
            final studySession = StudySession(
              title: '${suggestion['suggestion']}',
              moduleCode: suggestion['moduleCode'] ?? 'STUDY',
              moduleName: suggestion['moduleName'] ?? 'Study Session',
              dayOfWeek: day,
              startTime: suggestion['time'],
              endTime: suggestion['endTime'],
              venue: 'Study Area',
              sessionType: 'study',
              notes: suggestion['focus'] ?? 'AI-generated study session',
              duration: suggestion['duration'] ?? 90,
              isAutoGenerated: true,
            );
            
            final success = await StudySessionService.addStudySession(
              widget.student.studentId, 
              studySession
            );
            
            if (success) {
              totalSessionsAdded++;
            } else {
              failedSessions++;
            }
            
            // Small delay to prevent overwhelming the system
            await Future.delayed(const Duration(milliseconds: 100));
            
          } catch (e) {
            print('Error adding session for $day: $e');
            failedSessions++;
          }
        }
      }
      
      // Reload study sessions to get the updated list
      await _loadStudySessions();
      
      // Show comprehensive success message
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '🎉 Weekly Study Plan Added!',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 4),
                Text('✅ $totalSessionsAdded sessions added successfully'),
                if (failedSessions > 0) 
                  Text('⚠️ $failedSessions sessions failed to add'),
                const SizedBox(height: 4),
                const Text('Check your Study Sessions for the complete schedule'),
              ],
            ),
            backgroundColor: totalSessionsAdded > 0 ? Colors.green : Colors.orange,
            duration: const Duration(seconds: 4),
            action: SnackBarAction(
              label: 'View Schedule',
              textColor: Colors.white,
              onPressed: () async {
                // Auto-navigate to Study Sessions screen
                await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => StudySessionsScreen(
                      student: widget.student,
                      studySessions: studySessions,
                      studentModules: studentModules,
                    ),
                  ),
                );
                // Refresh data when returning
                await _loadStudySessions();
              },
            ),
          ),
        );
      }
      
      // Generate new suggestion for next week
      await _generateAISuggestion();
      
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error creating study sessions: $e'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  // Check for upcoming study sessions and offer quick actions
  void _checkForUpcomingSession() {
    if (studySessions.isEmpty) return;
    
    final now = DateTime.now();
    final currentDay = _getCurrentDayOfWeek();
    
    // Find sessions for today
    final todaySessions = studySessions.where((session) => 
      session.dayOfWeek.toLowerCase() == currentDay.toLowerCase()
    ).toList();
    
    if (todaySessions.isEmpty) {
      _showNoSessionsDialog();
      return;
    }
    
    // Sort by start time and find next session
    todaySessions.sort((a, b) => a.startTime.compareTo(b.startTime));
    
    StudySession? nextSession;
    for (final session in todaySessions) {
      final sessionTime = _parseTimeString(session.startTime);
      if (sessionTime.isAfter(now) || sessionTime.difference(now).inMinutes.abs() <= 30) {
        nextSession = session;
        break;
      }
    }
    
    if (nextSession != null) {
      _showStartSessionDialog(nextSession);
    } else {
      _showNoUpcomingSessionsDialog();
    }
  }
  
  String _getCurrentDayOfWeek() {
    final days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    return days[DateTime.now().weekday - 1];
  }
  
  DateTime _parseTimeString(String timeStr) {
    final parts = timeStr.split(':');
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day, int.parse(parts[0]), int.parse(parts[1]));
  }
  
  void _showStartSessionDialog(StudySession session) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          backgroundColor: Colors.grey[900],
          title: const Row(
            children: [
              Icon(Icons.play_circle, color: Colors.green),
              SizedBox(width: 8),
              Text('🎯 Ready to Study?', style: TextStyle(color: Colors.white)),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Your next session:',
                style: TextStyle(color: Colors.white70, fontSize: 14),
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.blue.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.blue.withOpacity(0.3)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      session.title,
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${session.moduleCode} • ${_formatTime(session.startTime)} - ${_formatTime(session.endTime)}',
                      style: TextStyle(color: Colors.white70),
                    ),
                    if (session.notes != null && session.notes!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        session.notes!,
                        style: TextStyle(color: Colors.white60, fontSize: 12),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Later', style: TextStyle(color: Colors.grey)),
            ),
            ElevatedButton.icon(
              onPressed: () {
                Navigator.of(context).pop();
                _startPomodoroSession(session);
              },
              style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
              icon: const Icon(Icons.timer, color: Colors.white),
              label: const Text('Start Focus Session', style: TextStyle(color: Colors.white)),
            ),
          ],
        );
      },
    );
  }
  
  void _showNoSessionsDialog() {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          backgroundColor: Colors.grey[900],
          title: const Row(
            children: [
              Icon(Icons.calendar_today, color: Colors.orange),
              SizedBox(width: 8),
              Text('No Sessions Today', style: TextStyle(color: Colors.white)),
            ],
          ),
          content: const Text(
            'You don\'t have any study sessions scheduled for today. Would you like to create one?',
            style: TextStyle(color: Colors.white70),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Maybe Later', style: TextStyle(color: Colors.grey)),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.of(context).pop();
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => CreateSessionScreen(
                      student: widget.student,
                      studentModules: studentModules,
                    ),
                  ),
                );
              },
              style: ElevatedButton.styleFrom(backgroundColor: Colors.blue),
              child: const Text('Create Session', style: TextStyle(color: Colors.white)),
            ),
          ],
        );
      },
    );
  }
  
  void _showNoUpcomingSessionsDialog() {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          backgroundColor: Colors.grey[900],
          title: const Row(
            children: [
              Icon(Icons.schedule, color: Colors.blue),
              SizedBox(width: 8),
              Text('All Done for Today!', style: TextStyle(color: Colors.white)),
            ],
          ),
          content: const Text(
            'You\'ve completed all your scheduled study sessions for today. Great work! 🎉',
            style: TextStyle(color: Colors.white70),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Awesome!', style: TextStyle(color: Colors.green)),
            ),
          ],
        );
      },
    );
  }
  
  void _startPomodoroSession(StudySession session) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => StudyTimerScreen(
          studySession: session,
        ),
      ),
    );
  }

  // Helper function to format time from 24-hour to 12-hour format
  String _formatTime(String time24) {
    try {
      // Handle special cases
      if (time24 == '00:00') return '12:00 AM';
      if (time24 == '00:30') return '12:30 AM';
      
      final parts = time24.split(':');
      if (parts.length != 2) return time24;
      
      int hour = int.parse(parts[0]);
      final minute = parts[1];
      
      if (hour == 0) {
        return '12:$minute AM';
      } else if (hour < 12) {
        return '$hour:$minute AM';
      } else if (hour == 12) {
        return '12:$minute PM';
      } else {
        return '${hour - 12}:$minute PM';
      }
    } catch (e) {
      return time24; // Return original if parsing fails
    }
  }

  void _showModuleDetailsPopup(Session session) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return Dialog(
          backgroundColor: Colors.transparent,
          child: Container(
            margin: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: AppColors.primaryGradient,
              ),
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.3),
                  blurRadius: 20,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Header
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppColors.surface.withValues(alpha: 0.12),
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(20),
                      topRight: Radius.circular(20),
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(
                          Icons.school,
                          color: Colors.white,
                          size: 24,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              session.moduleName,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              session.moduleCode,
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.8),
                                fontSize: 14,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.of(context).pop(),
                        icon: const Icon(
                          Icons.close,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
                
                // Content
                Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildDetailRow(
                        Icons.schedule,
                        'Time',
                        '${session.startTime} - ${session.endTime}',
                      ),
                      const SizedBox(height: 16),
                      _buildDetailRow(
                        Icons.calendar_today,
                        'Day',
                        session.dayOfWeek ?? 'Unknown',
                      ),
                      const SizedBox(height: 16),
                      _buildDetailRow(
                        Icons.person,
                        'Lecturer',
                        session.lecturerName.isNotEmpty ? session.lecturerName : 'Not specified',
                      ),
                      const SizedBox(height: 16),
                      _buildDetailRow(
                        Icons.location_on,
                        'Venue',
                        session.venueName.isNotEmpty ? session.venueName : 'Not specified',
                      ),
                      const SizedBox(height: 16),
                      _buildDetailRow(
                        Icons.category,
                        'Type',
                        session.sessionType ?? 'Lecture',
                      ),
                    ],
                  ),
                ),
                
                // Action buttons
                Container(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
                  child: Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: () {
                            Navigator.of(context).pop();
                            _navigateToCreateSession();
                          },
                          icon: const Icon(Icons.add, size: 18),
                          label: const Text('Add Study Session'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.green,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => Navigator.of(context).pop(),
                          icon: const Icon(Icons.close, size: 18),
                          label: const Text('Close'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.white,
                            side: const BorderSide(color: Colors.white70),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildDetailRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(
          icon,
          color: Colors.white70,
          size: 20,
        ),
        const SizedBox(width: 12),
        Text(
          '$label:',
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 14,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }

  List<Widget> _buildDailyPlanWidgets() {
    final dailyPlan = aiSuggestion?['dailyPlan'] as Map<String, dynamic>?;
    if (dailyPlan == null) return [];

    final widgets = <Widget>[];
    final days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    for (final day in days) {
      final daySuggestions = dailyPlan[day] as List<dynamic>?;
      if (daySuggestions != null && daySuggestions.isNotEmpty) {
        // Day header
        widgets.add(
          Padding(
            padding: const EdgeInsets.only(top: 8, bottom: 4),
            child: Text(
              day,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        );

        // Day suggestions
        for (final suggestion in daySuggestions.take(2)) {
          widgets.add(
            Container(
              margin: const EdgeInsets.only(bottom: 4),
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.05),
                borderRadius: BorderRadius.circular(6),
                border: Border.all(
                  color: suggestion['urgency'] == 'High' 
                    ? Colors.red.withValues(alpha: 0.3)
                    : Colors.blue.withValues(alpha: 0.2),
                  width: 1,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        '${_formatTime(suggestion['time'])}-${_formatTime(suggestion['endTime'])}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                        decoration: BoxDecoration(
                          color: suggestion['studyType'] == 'Deep Work'
                            ? Colors.blue.withValues(alpha: 0.3)
                            : suggestion['studyType'] == 'Practice'
                              ? Colors.green.withValues(alpha: 0.3)
                              : Colors.orange.withValues(alpha: 0.3),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          suggestion['studyType'] ?? 'Study',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 8,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                      const Spacer(),
                      Text(
                        '${suggestion['duration']}min',
                        style: const TextStyle(
                          color: Colors.white60,
                          fontSize: 9,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    suggestion['suggestion'],
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 9,
                    ),
                  ),
                ],
              ),
            ),
          );
        }
      }
    }

    return widgets;
  }

  Widget _buildModulesCard() {
    return GlassCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.purple.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.book,
                  color: Colors.white,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'My Modules',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.purple.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '${studentModules.length}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 16),
          
          if (studentModules.isEmpty)
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.05),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.1),
                  width: 1,
                ),
              ),
              child: Column(
                children: [
                  Icon(
                    Icons.book_outlined,
                    color: Colors.white.withValues(alpha: 0.5),
                    size: 32,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'No modules assigned yet',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.7),
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Contact your administrator to assign modules',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.5),
                      fontSize: 12,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            )
          else
            Column(
              children: studentModules.map((module) => Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color: Colors.purple.withValues(alpha: 0.3),
                    width: 1,
                  ),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 4,
                      height: 40,
                      decoration: BoxDecoration(
                        color: Colors.purple,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            module.moduleName,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            module.moduleCode,
                            style: const TextStyle(
                              color: Colors.white70,
                              fontSize: 12,
                            ),
                          ),
                          if (module.programmeName != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              '${module.programmeName} • ${module.yearName ?? ''}',
                              style: const TextStyle(
                                color: Colors.white60,
                                fontSize: 11,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    const Icon(
                      Icons.info_outline,
                      color: Colors.white60,
                      size: 18,
                    ),
                  ],
                ),
              )).toList(),
            ),
        ],
      ),
    );
  }
}
