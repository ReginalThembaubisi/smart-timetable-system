import 'package:flutter/material.dart';
import 'session.dart';
import '../models/student.dart';
import '../models/module.dart';
import '../services/api_service.dart';
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

class _TimetableScreenState extends State<TimetableScreen> {
  String selectedDay = 'Monday';
  final List<String> days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
  
  // Real data from API
  Map<String, Map<String, List<Session>>> timetableData = {};
  List<Module> studentModules = [];
  bool isLoading = true;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    try {
      setState(() {
        isLoading = true;
        errorMessage = null;
      });

      // Load timetable data
      final timetableResponse = await ApiService.getStudentTimetable(widget.student.studentId);
      if (timetableResponse['success'] == true) {
        _processTimetableData(timetableResponse['timetable']);
      }

      // Load student modules
      final modulesResponse = await ApiService.getStudentModules(widget.student.studentId);
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
    } catch (e) {
      setState(() {
        isLoading = false;
        errorMessage = 'Failed to load data: $e';
      });
    }
  }

  void _processTimetableData(Map<String, dynamic> timetableMap) {
    final Map<String, Map<String, List<Session>>> processedData = {};
    
    // The API returns a map where keys are days and values are lists of sessions
    timetableMap.forEach((day, sessionsList) {
      if (sessionsList is List) {
        for (final sessionData in sessionsList) {
          final session = Session.fromJson(sessionData);
          
          // Format time to HH:MM (remove seconds if present)
          String startTime = session.startTime ?? '00:00';
          if (startTime.length > 5) {
            startTime = startTime.substring(0, 5); // Remove seconds part
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
    });
  }
    

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Timetable - ${widget.student.fullName}'),
        backgroundColor: Colors.blue[600],
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadData,
          ),
          IconButton(
            icon: const Icon(Icons.school),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (context) => StudyPlanScreen(student: widget.student),
                ),
              );
            },
            tooltip: 'Study Plan',
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () {
              Navigator.of(context).pushReplacementNamed('/');
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // Day Selector
          Container(
            height: 60,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: days.length,
              itemBuilder: (context, index) {
                final day = days[index];
                final isSelected = day == selectedDay;
                
                return GestureDetector(
                  onTap: () {
                    setState(() {
                      selectedDay = day;
                    });
                  },
                  child: Container(
                    margin: const EdgeInsets.only(right: 12, top: 8, bottom: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                    decoration: BoxDecoration(
                      color: isSelected ? Colors.blue[600] : Colors.grey[200],
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      day,
                      style: TextStyle(
                        color: isSelected ? Colors.white : Colors.black87,
                        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          
          // Timetable Content
          Expanded(
            child: _buildTimetableContent(),
          ),
          
          // Student Modules Summary
          if (studentModules.isNotEmpty)
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                border: Border(
                  top: BorderSide(color: Colors.grey[300]!),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Your Modules (${studentModules.length})',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 4,
                    children: studentModules.map((module) => Chip(
                      label: Text('${module.moduleCode}'),
                      backgroundColor: Colors.blue[100],
                    )).toList(),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildTimetableContent() {
    if (isLoading) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text(
              'Loading your timetable...',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey,
              ),
            ),
          ],
        ),
      );
    }

    if (errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.error_outline,
              size: 64,
              color: Colors.red,
            ),
            SizedBox(height: 16),
            Text(
              'Error loading timetable',
              style: const TextStyle(
                fontSize: 18,
                color: Colors.red,
              ),
            ),
            SizedBox(height: 8),
            Text(
              errorMessage!,
              style: const TextStyle(
                fontSize: 14,
                color: Colors.grey,
              ),
              textAlign: TextAlign.center,
            ),
            SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadData,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    final daySessions = timetableData[selectedDay] ?? {};
    
    if (daySessions.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.event_busy,
              size: 64,
              color: Colors.grey,
            ),
            SizedBox(height: 16),
            Text(
              timetableData.isEmpty 
                ? 'No class schedule available yet'
                : 'No classes on $selectedDay!',
              style: const TextStyle(
                fontSize: 18,
                color: Colors.grey,
              ),
            ),
            if (timetableData.isEmpty) ...[
              const SizedBox(height: 8),
              const Text(
                'Your class schedule will appear here once added by administration',
                style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey,
                ),
                textAlign: TextAlign.center,
              ),
            ],
            SizedBox(height: 8),
            const Text(
              'Select a different day or check your schedule',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      );
    }

    final timeSlots = daySessions.keys.toList()..sort();
    
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: timeSlots.length,
      itemBuilder: (context, index) {
        final timeSlot = timeSlots[index];
        final sessions = daySessions[timeSlot]!;
        
        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Time Header
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: Colors.blue[100],
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  timeSlot,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: Colors.blue[800],
                  ),
                ),
              ),
              
              SizedBox(height: 8),
              
              // Sessions
              ...sessions.map((session) => _buildSessionCard(session)),
            ],
          ),
        );
      },
    );
  }

  Widget _buildSessionCard(Session session) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      elevation: 2,
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        leading: Container(
          width: 50,
          height: 50,
          decoration: BoxDecoration(
            color: Colors.blue[100],
            borderRadius: BorderRadius.circular(8),
          ),
          child: const Icon(
            Icons.school,
            color: Colors.blue,
            size: 24,
          ),
        ),
        title: Text(
          session.moduleName,
          style: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(height: 4),
            Text('${session.startTime} - ${session.endTime}'),
            Text('Location: ${session.venueName}'),
            Text('Lecturer: ${session.lecturerName}'),
          ],
        ),
        trailing: IconButton(
          icon: const Icon(Icons.info_outline),
          onPressed: () {
            _showSessionDetails(session);
          },
        ),
      ),
    );
  }

  void _showSessionDetails(Session session) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(session.moduleName),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Time: ${session.startTime} - ${session.endTime}'),
            SizedBox(height: 8),
            Text('Location: ${session.venueName}'),
            SizedBox(height: 8),
            Text('Lecturer: ${session.lecturerName}'),
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
}
