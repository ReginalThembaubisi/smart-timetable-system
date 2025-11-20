import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:ui';
import 'dart:convert';
import '../models/student.dart';
import '../models/module.dart';
import '../models/study_session.dart';
import '../services/api_service.dart';
import '../services/local_storage_service.dart';
import '../services/study_session_service.dart';
import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import 'session.dart';
import 'study_sessions_screen.dart';
import 'create_session_screen.dart';
import 'settings_screen.dart';

class NewTimetableScreen extends StatefulWidget {
  final Student student;

  const NewTimetableScreen({
    Key? key,
    required this.student,
  }) : super(key: key);

  @override
  State<NewTimetableScreen> createState() => _NewTimetableScreenState();
}

class _NewTimetableScreenState extends State<NewTimetableScreen> {
  String selectedDay = 'Monday';
  List<String> days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
  
  // Real data from API
  Map<String, Map<String, List<Session>>> timetableData = {};
  List<Module> studentModules = [];
  bool isLoading = true;
  String? errorMessage;
  
  // Study sessions
  List<StudySession> studySessions = [];
  
  // Offline mode
  bool isOfflineMode = false;
  bool hasLocalData = false;
  
  // Search state
  String searchQuery = '';
  List<Session> filteredSessions = [];
  bool isSearching = false;

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

      // Try to load from API first
      await _loadTimetableData();
      await _loadModulesData();
      await _loadStudySessions();
      
      // Load local data as fallback
      await _loadLocalData();
      
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

  Future<void> _loadTimetableData() async {
    try {
      print('Debug: Loading timetable for student ID: ${widget.student.studentId}');
      final response = await ApiService.getStudentTimetable(widget.student.studentId);
      print('Debug: API response: $response');
      
      if (response['success'] == true) {
        final timetableMap = response['timetable'] as Map<String, dynamic>;
        print('Debug: Timetable map keys: ${timetableMap.keys}');
        print('Debug: Timetable data: $timetableMap');
        _processTimetableData(timetableMap);
        await _saveTimetableLocally(timetableMap);
        setState(() {
          isOfflineMode = false;
          hasLocalData = true;
        });
      } else {
        print('Debug: API returned success: false, message: ${response['message']}');
        // Fallback to local data
        await _loadLocalData();
        setState(() {
          isOfflineMode = true;
        });
      }
    } catch (e) {
      print('Debug: Error loading timetable data: $e');
      // Fallback to local data
      await _loadLocalData();
      setState(() {
        isOfflineMode = true;
      });
    }
  }

  Future<void> _loadModulesData() async {
    try {
      final response = await ApiService.getStudentModules(widget.student.studentId);
      
      if (response['success'] == true) {
        final modulesList = response['modules'] as List;
        setState(() {
          studentModules = modulesList
              .map((json) => Module.fromJson(json))
              .toList();
        });
        await _saveModulesLocally(modulesList);
      }
    } catch (e) {
      print('Error loading modules: $e');
    }
  }

  Future<void> _loadLocalData() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      
      // Load timetable from local storage
      final timetableKey = 'timetable_${widget.student.studentId}';
      final timetableJson = prefs.getString(timetableKey);
      if (timetableJson != null) {
        final timetableMap = jsonDecode(timetableJson) as Map<String, dynamic>;
        _processTimetableData(timetableMap);
        print('Debug: Loaded timetable from local storage');
      }
      
      // Load modules from local storage
      final modulesKey = 'modules_${widget.student.studentId}';
      final modulesJson = prefs.getString(modulesKey);
      if (modulesJson != null) {
        final modulesList = jsonDecode(modulesJson) as List;
        setState(() {
          studentModules = modulesList
              .map((json) => Module.fromJson(json))
              .toList();
        });
        print('Debug: Loaded ${studentModules.length} modules from local storage');
      }
      
      // Update offline status
      setState(() {
        hasLocalData = timetableData.isNotEmpty || studentModules.isNotEmpty;
      });
    } catch (e) {
      print('Debug: Error loading local data: $e');
    }
  }

  // Save timetable data to local storage
  Future<void> _saveTimetableLocally(Map<String, dynamic> timetableMap) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final timetableKey = 'timetable_${widget.student.studentId}';
      await prefs.setString(timetableKey, jsonEncode(timetableMap));
      print('Debug: Timetable saved to local storage');
    } catch (e) {
      print('Debug: Error saving timetable locally: $e');
    }
  }

  // Save modules data to local storage
  Future<void> _saveModulesLocally(List<dynamic> modulesList) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final modulesKey = 'modules_${widget.student.studentId}';
      await prefs.setString(modulesKey, jsonEncode(modulesList));
      print('Debug: Modules saved to local storage');
    } catch (e) {
      print('Debug: Error saving modules locally: $e');
    }
  }

  // Search sessions based on query
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
    
    // Filter university timetable sessions based on search query
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

  // Load study sessions using the service
  Future<void> _loadStudySessions() async {
    try {
      final loadedSessions = await StudySessionService.getStudySessions(widget.student.studentId);
      
      setState(() {
        studySessions = loadedSessions;
      });
      
      debugPrint('Loaded ${loadedSessions.length} study sessions from service');
    } catch (e) {
      debugPrint('Error loading study sessions: $e');
    }
  }

  void _processTimetableData(Map<String, dynamic> timetableMap) {
    print('Debug: Processing timetable data. Raw map keys: ${timetableMap.keys}');
    print('Debug: Raw timetable data: $timetableMap');
    
    final Map<String, Map<String, List<Session>>> processedData = {};
    
    // The API returns a map where keys are days and values are lists of sessions
    timetableMap.forEach((day, sessionsList) {
      if (sessionsList is List) {
        for (final sessionData in sessionsList) {
          print('Debug: Processing session: $sessionData');
          final session = Session.fromJson(sessionData);
          
          // Use the day from the map key instead of session object
          String normalizedDay = _normalizeDayName(day);
          
          // Format time to HH:MM (remove seconds if present)
          String startTime = session.startTime ?? '00:00';
          if (startTime.length > 5) {
            startTime = startTime.substring(0, 5); // Remove seconds part
          }
          
          print('Debug: Extracted day: $normalizedDay, startTime: $startTime');
          
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
    
    print('Debug: Processed data structure: $processedData');
    print('Debug: Processed data keys: ${processedData.keys}');
    
    setState(() {
      timetableData = processedData;
    });
    
    // Update the days list to match available data
    _updateDaysList();
    
    print('Debug: State updated. New timetableData keys: ${timetableData.keys}');
  }

  // Normalize day names from various formats
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

  // Get all sessions for a selected day (timetable + custom study sessions)
  Map<String, List<Session>> _getAllSessionsForDay(String day) {
    final Map<String, List<Session>> allSessions = {};
    
    // Add timetable sessions
    if (timetableData.containsKey(day)) {
      allSessions.addAll(timetableData[day]!);
    }
    
    return allSessions;
  }

  // Update days list based on available data
  void _updateDaysList() {
    final availableDays = timetableData.keys.toList();
    if (availableDays.isNotEmpty) {
      setState(() {
        days = availableDays;
        if (!days.contains(selectedDay)) {
          selectedDay = days.first;
        }
      });
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
            colors: [
              Color(0xFF667eea),
              Color(0xFF764ba2),
            ],
          ),
        ),
        child: SafeArea(
          child: isLoading
              ? _buildLoadingState()
              : errorMessage != null
                  ? _buildErrorState()
                  : Column(
                      children: [
                        _buildHeader(),
                        Expanded(
                          child: isSearching
                              ? _buildSearchResults()
                              : _buildTimetableContent(),
                        ),
                      ],
                    ),
        ),
      ),
    );
  }

  Widget _buildLoadingState() {
    return const Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
          ),
          SizedBox(height: 16),
          Text(
            'Loading timetable...',
            style: TextStyle(
              color: Colors.white,
              fontSize: 16,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.error_outline,
              color: Colors.white,
              size: 64,
            ),
            const SizedBox(height: 16),
            Text(
              'Error loading timetable',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              errorMessage ?? 'Unknown error occurred',
              style: const TextStyle(
                color: Colors.white70,
                fontSize: 14,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: _loadData,
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          // Title and actions
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'University Timetable',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      'Official class schedule',
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.8),
                        fontSize: 14,
                      ),
                    ),
                  ],
                ),
              ),
              // Action buttons
              Row(
                children: [
                  GlassButton(
                    onPressed: _loadData,
                    padding: const EdgeInsets.all(8),
                    borderRadius: 20,
                    child: const Icon(
                      Icons.refresh,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 8),
                  GlassButton(
                    onPressed: () {
                      // Set to today
                      final today = DateTime.now();
                      final weekdayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                      final currentWeekday = weekdayNames[today.weekday - 1];
                      if (days.contains(currentWeekday)) {
                        setState(() {
                          selectedDay = currentWeekday;
                        });
                      }
                    },
                    padding: const EdgeInsets.all(8),
                    borderRadius: 20,
                    child: const Icon(
                      Icons.today,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 8),
                  GlassButton(
                    onPressed: () {
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
                    padding: const EdgeInsets.all(8),
                    borderRadius: 20,
                    child: const Icon(
                      Icons.add,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 8),
                  GlassButton(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => StudySessionsScreen(
                            student: widget.student,
                            studySessions: studySessions,
                            studentModules: studentModules,
                          ),
                        ),
                      );
                    },
                    padding: const EdgeInsets.all(8),
                    borderRadius: 20,
                    child: const Icon(
                      Icons.school,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 8),
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
                ],
              ),
            ],
          ),
          
          const SizedBox(height: 16),
          
          // Search bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.15),
              borderRadius: BorderRadius.circular(25),
              border: Border.all(
                color: Colors.white.withOpacity(0.3),
                width: 1,
              ),
            ),
            child: TextField(
              onChanged: _performSearch,
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(
                hintText: 'Search university timetable...',
                hintStyle: TextStyle(color: Colors.white.withOpacity(0.7)),
                prefixIcon: Icon(
                  Icons.search,
                  color: Colors.white.withOpacity(0.7),
                ),
                suffixIcon: isSearching
                    ? IconButton(
                        icon: Icon(
                          Icons.clear,
                          color: Colors.white.withOpacity(0.7),
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
          
          const SizedBox(height: 16),
          
          // Day selection tabs
          Container(
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
                            ? Colors.white.withOpacity(0.3)
                            : Colors.white.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(25),
                        border: Border.all(
                          color: isSelected 
                              ? Colors.white.withOpacity(0.5)
                              : Colors.white.withOpacity(0.2),
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
          ),
        ],
      ),
    );
  }


  Widget _buildTimetableContent() {
    // Debug: Show raw data
    if (timetableData.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.bug_report,
                color: Colors.white70,
                size: 64,
              ),
              const SizedBox(height: 16),
              const Text(
                'No timetable data loaded',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Timetable data keys: ${timetableData.keys}',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.7),
                  fontSize: 14,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: _loadData,
                child: const Text('Reload Data'),
              ),
            ],
          ),
        ),
      );
    }
    
    // List view
    final daySessions = _getAllSessionsForDay(selectedDay);
    
    if (daySessions.isEmpty) {
      return _buildEmptyDay();
    }
    
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: daySessions.length,
      itemBuilder: (context, index) {
        final timeSlot = daySessions.keys.elementAt(index);
        final sessions = daySessions[timeSlot]!;
        
        return Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: GlassCard(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Time slot header
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.blue.withOpacity(0.3),
                        borderRadius: BorderRadius.circular(15),
                      ),
                      child: Text(
                        timeSlot,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '${sessions.length} class${sessions.length > 1 ? 'es' : ''}',
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.7),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
                
                const SizedBox(height: 12),
                
                // Sessions for this time slot
                ...sessions.map((session) => _buildSessionItem(session)),
              ],
            ),
          ),
        );
      },
    );
  }


  Widget _buildSessionItem(Session session) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: Colors.white.withOpacity(0.2),
          width: 1,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Module name and code
          Row(
            children: [
              Expanded(
                child: Text(
                  session.moduleName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.green.withOpacity(0.3),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  session.moduleCode,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 8),
          
          // Time and duration
          Row(
            children: [
              Icon(
                Icons.access_time,
                color: Colors.white.withOpacity(0.7),
                size: 16,
              ),
              const SizedBox(width: 4),
              Text(
                '${session.startTime} - ${session.endTime}',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.7),
                  fontSize: 14,
                ),
              ),
              const SizedBox(width: 16),
              Icon(
                Icons.location_on,
                color: Colors.white.withOpacity(0.7),
                size: 16,
              ),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  session.venueName,
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.7),
                    fontSize: 14,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 4),
          
          // Lecturer
          Row(
            children: [
              Icon(
                Icons.person,
                color: Colors.white.withOpacity(0.7),
                size: 16,
              ),
              const SizedBox(width: 4),
              Text(
                'Lecturer: ${session.lecturerName}',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.7),
                  fontSize: 14,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyDay() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.event_available,
              color: Colors.white70,
              size: 64,
            ),
            const SizedBox(height: 16),
            Text(
              'No classes on $selectedDay',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Enjoy your free day!',
              style: TextStyle(
                color: Colors.white.withOpacity(0.7),
                fontSize: 14,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchResults() {
    if (filteredSessions.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.search_off,
                color: Colors.white70,
                size: 64,
              ),
              const SizedBox(height: 16),
              const Text(
                'No results found',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Try searching for a different term',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.7),
                  fontSize: 14,
                ),
              ),
            ],
          ),
        ),
      );
    }
    
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: filteredSessions.length,
      itemBuilder: (context, index) {
        final session = filteredSessions[index];
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: GlassCard(
            padding: const EdgeInsets.all(16),
            child: _buildSessionItem(session),
          ),
        );
      },
    );
  }
}
