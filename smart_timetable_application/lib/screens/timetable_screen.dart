import 'package:flutter/material.dart';
import 'session.dart';
import '../models/student.dart';
import '../models/module.dart';
import '../services/api_service.dart';
import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import '../widgets/skeleton_loader.dart';
import '../config/app_colors.dart';
import 'study_plan_screen.dart';

class TimetableScreen extends StatefulWidget {
  final Student student;

  const TimetableScreen({
    Key? key,
    required this.student,
  }) : super(key: key);

  @override
  State<TimetableScreen> createState() => _TimetableScreenState();
}

class _TimetableScreenState extends State<TimetableScreen>
    with SingleTickerProviderStateMixin {
  String selectedDay = 'Monday';
  final List<String> _allDays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday'
  ];
  List<String> days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

  // Real data from API
  Map<String, Map<String, List<Session>>> timetableData = {};
  List<Module> studentModules = [];
  bool isLoading = true;
  String? errorMessage;

  late AnimationController _fadeController;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _fadeController = AnimationController(
      duration: const Duration(milliseconds: 600),
      vsync: this,
    );
    _fadeAnimation = CurvedAnimation(
      parent: _fadeController,
      curve: Curves.easeInOut,
    );
    _setTodayAsDefault();
    _loadData();
  }

  @override
  void dispose() {
    _fadeController.dispose();
    super.dispose();
  }

  void _setTodayAsDefault() {
    final today = DateTime.now();
    final names = [
      'Monday',
      'Tuesday',
      'Wednesday',
      'Thursday',
      'Friday',
      'Saturday',
      'Sunday'
    ];
    final currentDay = names[today.weekday - 1];
    if (_allDays.contains(currentDay)) {
      selectedDay = currentDay;
    }
  }

  Future<void> _loadData() async {
    try {
      setState(() {
        isLoading = true;
        errorMessage = null;
      });

      // Load timetable data
      final timetableResponse =
          await ApiService.getStudentTimetable(widget.student.studentId);
      if (timetableResponse['success'] == true) {
        _processTimetableData(timetableResponse['timetable']);
      }

      // Load student modules
      final modulesResponse =
          await ApiService.getStudentModules(widget.student.studentId);
      if (modulesResponse['success'] == true) {
        setState(() {
          studentModules = (modulesResponse['modules'] as List)
              .map((json) => Module.fromJson(json))
              .toList();
        });
      }

      setState(() {
        isLoading = false;
      });

      _fadeController.forward();
    } catch (e) {
      setState(() {
        isLoading = false;
        errorMessage = 'Failed to load data: $e';
      });
    }
  }

  void _processTimetableData(Map<String, dynamic> timetableMap) {
    final Map<String, Map<String, List<Session>>> processedData = {};

    timetableMap.forEach((day, sessionsList) {
      if (sessionsList is List) {
        for (final sessionData in sessionsList) {
          final session = Session.fromJson(sessionData);

          String startTime = session.startTime ?? '00:00';
          if (startTime.length > 5) {
            startTime = startTime.substring(0, 5);
          }

          if (!processedData.containsKey(day)) {
            processedData[day] = {};
          }
          if (!processedData[day]!.containsKey(startTime)) {
            processedData[day]![startTime] = [];
          }

          processedData[day]![startTime]!.add(session);
        }
      }
    });

    setState(() {
      timetableData = processedData;

      // Build sorted list of days that have data
      final dayOrder = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
      ];
      days = processedData.keys.toList()
        ..sort((a, b) {
          final ia = dayOrder.indexOf(a);
          final ib = dayOrder.indexOf(b);
          return (ia == -1 ? 99 : ia).compareTo(ib == -1 ? 99 : ib);
        });

      if (!days.contains(selectedDay) && days.isNotEmpty) {
        selectedDay = days.first;
      }
    });
  }

  Color _moduleColor(Session session) {
    final type = session.sessionType?.toLowerCase() ?? '';
    return AppColors.getModuleColor(type);
  }

  IconData _moduleIcon(Session session) {
    final type = session.sessionType?.toLowerCase() ?? '';
    switch (type) {
      case 'lecture':
        return Icons.cast_for_education;
      case 'tutorial':
        return Icons.people;
      case 'practical':
      case 'lab':
      case 'laboratory':
        return Icons.science;
      case 'seminar':
        return Icons.forum;
      case 'workshop':
        return Icons.build;
      default:
        return Icons.school;
    }
  }

  List<Session> _getSessionsForDay(String day) {
    if (!timetableData.containsKey(day)) return [];
    final allSessions = <Session>[];
    for (final timeSlot in timetableData[day]!.values) {
      allSessions.addAll(timeSlot);
    }
    allSessions.sort((a, b) =>
        (a.startTime ?? '').compareTo(b.startTime ?? ''));
    return allSessions;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: AppColors.backgroundGradient,
        ),
      ),
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildHeader(),
            _buildDaySelector(),
            const SizedBox(height: 4),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      child: Row(
        children: [
          const Icon(Icons.calendar_month, color: Colors.white, size: 26),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Class Timetable',
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
                if (studentModules.isNotEmpty)
                  Text(
                    '${studentModules.length} module${studentModules.length == 1 ? '' : 's'} enrolled',
                    style: const TextStyle(
                      fontSize: 13,
                      color: Colors.white60,
                    ),
                  ),
              ],
            ),
          ),
          // Refresh button
          GlassButton(
            onPressed: _loadData,
            padding: const EdgeInsets.all(8),
            borderRadius: 20,
            child: const Icon(Icons.refresh, color: Colors.white, size: 20),
          ),
          const SizedBox(width: 8),
          // Study plan icon
          GlassButton(
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (context) =>
                      StudyPlanScreen(student: widget.student),
                ),
              );
            },
            padding: const EdgeInsets.all(8),
            borderRadius: 20,
            child: const Icon(Icons.book, color: Colors.white, size: 20),
          ),
        ],
      ),
    );
  }

  Widget _buildDaySelector() {
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: SizedBox(
        height: 44,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: _allDays.length,
          itemBuilder: (context, index) {
            final day = _allDays[index];
            final isSelected = day == selectedDay;
            final hasData = timetableData.containsKey(day) &&
                (timetableData[day]?.isNotEmpty ?? false);
            final dayColor = AppColors.getDayColor(day);

            return GestureDetector(
              onTap: () => setState(() => selectedDay = day),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.only(right: 10),
                padding:
                    const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
                decoration: BoxDecoration(
                  color: isSelected
                      ? dayColor.withValues(alpha: 0.85)
                      : Colors.white.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(
                    color: isSelected
                        ? dayColor
                        : Colors.white.withValues(alpha: 0.15),
                    width: 1.5,
                  ),
                  boxShadow: isSelected
                      ? [
                          BoxShadow(
                            color: dayColor.withValues(alpha: 0.4),
                            blurRadius: 8,
                            offset: const Offset(0, 3),
                          )
                        ]
                      : [],
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      day.substring(0, 3),
                      style: TextStyle(
                        color: isSelected ? Colors.white : Colors.white60,
                        fontWeight: isSelected
                            ? FontWeight.bold
                            : FontWeight.normal,
                        fontSize: 13,
                      ),
                    ),
                    if (hasData) ...[
                      const SizedBox(width: 5),
                      Container(
                        width: 6,
                        height: 6,
                        decoration: BoxDecoration(
                          color: isSelected
                              ? Colors.white
                              : dayColor,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return ListView(
        padding: const EdgeInsets.all(16),
        children: List.generate(3, (_) => const SkeletonCard()),
      );
    }

    if (errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 56, color: Colors.red),
            const SizedBox(height: 16),
            Text(
              errorMessage!,
              style: const TextStyle(color: Colors.white70, fontSize: 14),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            GlassButton(
              onPressed: _loadData,
              padding:
                  const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              borderRadius: 12,
              child: const Text('Retry',
                  style: TextStyle(color: Colors.white, fontSize: 15)),
            ),
          ],
        ),
      );
    }

    final sessions = _getSessionsForDay(selectedDay);

    if (sessions.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.event_available,
              size: 64,
              color: Colors.white.withValues(alpha: 0.3),
            ),
            const SizedBox(height: 16),
            Text(
              timetableData.isEmpty
                  ? 'No schedule available yet'
                  : 'No classes on $selectedDay! 🎉',
              style: const TextStyle(
                fontSize: 18,
                color: Colors.white70,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Enjoy your free day or plan some study time.',
              style: TextStyle(fontSize: 13, color: Colors.white38),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      );
    }

    return FadeTransition(
      opacity: _fadeAnimation,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        itemCount: sessions.length,
        itemBuilder: (context, index) => _buildSessionCard(sessions[index]),
      ),
    );
  }

  Widget _buildSessionCard(Session session) {
    final color = _moduleColor(session);
    final icon = _moduleIcon(session);
    final startTime = session.startTime ?? '--:--';
    final endTime = session.endTime ?? '--:--';

    return GlassCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          // Color-coded icon block
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: color.withValues(alpha: 0.5),
                width: 1.5,
              ),
            ),
            child: Icon(icon, color: color, size: 24),
          ),

          const SizedBox(width: 14),

          // Session details
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  session.moduleName,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.access_time,
                        size: 13, color: Colors.white.withValues(alpha: 0.6)),
                    const SizedBox(width: 4),
                    Text(
                      '$startTime – $endTime',
                      style: TextStyle(
                        fontSize: 13,
                        color: Colors.white.withValues(alpha: 0.8),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    if (session.sessionType != null &&
                        session.sessionType!.isNotEmpty) ...[
                      const SizedBox(width: 10),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 7, vertical: 2),
                        decoration: BoxDecoration(
                          color: color.withValues(alpha: 0.18),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          session.sessionType!.toUpperCase(),
                          style: TextStyle(
                              fontSize: 10,
                              color: color,
                              fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.location_on,
                        size: 13, color: Colors.white.withValues(alpha: 0.5)),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        session.venueName,
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.white.withValues(alpha: 0.6),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                if (session.lecturerName.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Row(
                    children: [
                      Icon(Icons.person,
                          size: 13,
                          color: Colors.white.withValues(alpha: 0.5)),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          session.lecturerName,
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.white.withValues(alpha: 0.6),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),

          // Info button
          IconButton(
            icon: Icon(Icons.info_outline,
                color: Colors.white.withValues(alpha: 0.5), size: 20),
            onPressed: () => _showSessionDetails(session),
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(),
          ),
        ],
      ),
    );
  }

  void _showSessionDetails(Session session) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppColors.surface,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(
          session.moduleName,
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _detailRow(Icons.code, 'Code', session.moduleCode),
            _detailRow(Icons.access_time, 'Time',
                '${session.startTime} – ${session.endTime}'),
            _detailRow(Icons.location_on, 'Venue', session.venueName),
            _detailRow(Icons.person, 'Lecturer', session.lecturerName),
            if (session.sessionType != null)
              _detailRow(Icons.category, 'Type', session.sessionType!),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close', style: TextStyle(color: AppColors.primary)),
          ),
        ],
      ),
    );
  }

  Widget _detailRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppColors.primary),
          const SizedBox(width: 8),
          Text('$label: ',
              style: const TextStyle(
                  color: Colors.white60,
                  fontSize: 13,
                  fontWeight: FontWeight.w500)),
          Expanded(
            child: Text(
              value.isEmpty ? '–' : value,
              style: const TextStyle(color: Colors.white, fontSize: 13),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}
