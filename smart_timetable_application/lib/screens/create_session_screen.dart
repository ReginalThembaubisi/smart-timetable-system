import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/student.dart';
import '../models/module.dart';
import '../models/study_session.dart';
import '../screens/session.dart';
import '../services/study_session_service.dart';
import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import '../config/app_colors.dart';

class CreateSessionScreen extends StatefulWidget {
  final Student student;
  final List<Module> studentModules;
  /// Optional: student's class timetable keyed by day → time → sessions.
  /// When provided, smart defaults will skip any slots that conflict with a class.
  final Map<String, Map<String, List<Session>>>? timetableData;

  const CreateSessionScreen({
    Key? key,
    required this.student,
    required this.studentModules,
    this.timetableData,
  }) : super(key: key);

  @override
  State<CreateSessionScreen> createState() => _CreateSessionScreenState();
}

class _CreateSessionScreenState extends State<CreateSessionScreen> {
  final _formKey = GlobalKey<FormState>();
  
  Module? selectedModule;
  String selectedDay = 'Monday';
  String selectedStartTime = '09:00';
  String selectedEndTime = '10:00';
  String selectedSessionType = 'study';
  String _studyPreference = 'balanced'; // loaded from prefs
  bool _conflictAdjusted = false; // true when defaults were shifted to avoid a class
  // Venue removed - not needed per user request
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();
  
  final List<String> days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  // Generate 24-hour time slots with 30-minute intervals
  final List<String> timeSlots = List.generate(48, (index) {
    final hour = (index ~/ 2).toString().padLeft(2, '0');
    final minute = (index % 2 == 0) ? '00' : '30';
    return '$hour:$minute';
  });
  final List<String> sessionTypes = ['study', 'revision', 'assignment', 'exam_prep'];
  // Venues removed - not needed per user request

  @override
  void initState() {
    super.initState();
    debugPrint('CreateSessionScreen: Received ${widget.studentModules.length} modules');
    for (final module in widget.studentModules) {
      debugPrint('Module: ${module.moduleCode} - ${module.moduleName}');
    }
    // Load preference first, then set smart defaults
    _loadAndApplyPreference();
  }

  Future<void> _loadAndApplyPreference() async {
    final prefs = await SharedPreferences.getInstance();
    final pref = prefs.getString('study_preference') ?? 'balanced';
    setState(() {
      _studyPreference = pref;
    });
    _setSmartDefaults();
  }
  
  void _setSmartDefaults() {
    // Set default module and title
    if (widget.studentModules.isNotEmpty) {
      selectedModule = widget.studentModules.first;
      _titleController.text = 'Study ${selectedModule!.moduleName}';
    } else {
      debugPrint('CreateSessionScreen: No modules available!');
      selectedModule = null;
    }

    // Set default day to today or next weekday
    final now = DateTime.now();
    final weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    selectedDay = (now.weekday >= 6) ? 'Monday' : weekdays[now.weekday - 1];

    // Determine preferred start time from saved study preference
    String preferredStart;
    switch (_studyPreference) {
      case 'morning':
        preferredStart = '07:00';
        break;
      case 'afternoon':
        preferredStart = '13:00';
        break;
      case 'evening':
        preferredStart = '17:30';
        break;
      case 'night':
        preferredStart = '20:00';
        break;
      default: // 'balanced' — clock-based
        final h = now.hour;
        if (h < 8) {
          preferredStart = '09:00';
        } else if (h < 12) {
          preferredStart = '${(h + 1).toString().padLeft(2, '0')}:00';
        } else if (h < 17) {
          preferredStart = '14:00';
        } else {
          preferredStart = '19:00';
        }
    }

    // Find the next class-conflict-free slot starting from preferredStart
    final freeStart = _findNextFreeSlot(selectedDay, preferredStart);
    _conflictAdjusted = freeStart != preferredStart;

    final startMinutes = _timeToMinutes(freeStart);
    final endMinutes = startMinutes + 90; // 1.5 h session
    final endHour = (endMinutes ~/ 60) % 24;
    final endMin = endMinutes % 60;

    selectedStartTime = freeStart;
    selectedEndTime = '${endHour.toString().padLeft(2, '0')}:${endMin.toString().padLeft(2, '0')}';

    // Set default notes based on selected module
    if (selectedModule != null) {
      _notesController.text = 'Focus on ${selectedModule!.moduleName} concepts and practice';
    }
  }

  /// Walk forward in 30-min steps from [startTime] on [day] until a 90-minute
  /// window has no overlap with any class in [widget.timetableData].
  String _findNextFreeSlot(String day, String startTime) {
    if (widget.timetableData == null) return startTime;
    final daySchedule = widget.timetableData![day] ?? {};
    if (daySchedule.isEmpty) return startTime;

    // Collect all class intervals for this day
    final classTimes = <_TimeInterval>[];
    for (final sessions in daySchedule.values) {
      for (final session in sessions) {
        final s = session.startTime;
        final e = session.endTime;
        if (s != null && e != null) {
          classTimes.add(_TimeInterval(_timeToMinutes(s), _timeToMinutes(e)));
        }
      }
    }

    var candidate = _timeToMinutes(startTime);
    const sessionDuration = 90; // minutes
    const maxSearch = 24 * 60; // don't search past midnight

    while (candidate + sessionDuration <= maxSearch) {
      final candidateEnd = candidate + sessionDuration;
      final conflict = classTimes.any((c) => candidate < c.end && candidateEnd > c.start);
      if (!conflict) {
        final h = (candidate ~/ 60) % 24;
        final m = candidate % 60;
        return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
      }
      candidate += 30; // try next 30-min slot
    }

    return startTime; // fallback: return original if nothing found
  }

  static int _timeToMinutes(String time) {
    try {
      final parts = time.split(':');
      return int.parse(parts[0]) * 60 + int.parse(parts[1]);
    } catch (_) {
      return 0;
    }
  }


  @override
  void dispose() {
    _titleController.dispose();
    _notesController.dispose();
    super.dispose();
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
              Expanded(
                child: _buildForm(),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Row(
        children: [
          GlassButton(
            onPressed: () => Navigator.pop(context),
            padding: const EdgeInsets.all(12),
            borderRadius: 25,
            child: const Icon(
              Icons.arrow_back,
              color: Colors.white,
              size: 24,
            ),
          ),
          
          const SizedBox(width: 16),
          
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Create Study Session',
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
                Text(
                  'Plan your study time effectively',
                  style: TextStyle(
                    fontSize: 16,
                    color: Colors.white70,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildForm() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Module Selection
            _buildSectionTitle('Select Module'),
            GlassCard(
              padding: const EdgeInsets.all(16),
              child: widget.studentModules.isEmpty
                ? const Padding(
                    padding: EdgeInsets.all(16.0),
                    child: Text(
                      'Loading modules...',
                      style: TextStyle(color: Colors.white70),
                    ),
                  )
                : DropdownButtonFormField<Module>(
                    value: selectedModule,
                    decoration: const InputDecoration(
                      labelText: 'Module',
                      border: InputBorder.none,
                      labelStyle: TextStyle(color: Colors.white70),
                    ),
                    style: const TextStyle(color: Colors.white),
                    dropdownColor: Colors.blue[800],
                    items: widget.studentModules.map((module) {
                      return DropdownMenuItem(
                        value: module,
                        child: Text(
                          '${module.moduleCode} - ${module.moduleName}',
                          style: const TextStyle(color: Colors.white),
                        ),
                      );
                    }).toList(),
                onChanged: (Module? value) {
                  setState(() {
                    selectedModule = value;
                    if (value != null) {
                      _titleController.text = 'Study ${value.moduleName}';
                    }
                  });
                },
                    validator: (value) {
                      if (widget.studentModules.isEmpty) {
                        return 'Modules are still loading';
                      }
                      if (value == null) {
                        return 'Please select a module';
                      }
                      return null;
                    },
              ),
            ),
            
            const SizedBox(height: 24),
            
            // Session Details
            _buildSectionTitle('Session Details'),
            GlassCard(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  TextFormField(
                    controller: _titleController,
                    decoration: const InputDecoration(
                      labelText: 'Session Title',
                      border: InputBorder.none,
                      labelStyle: TextStyle(color: Colors.white70),
                    ),
                    style: const TextStyle(color: Colors.white),
                    validator: (value) {
                      if (value == null || value.trim().isEmpty) {
                        return 'Please enter a session title';
                      }
                      return null;
                    },
                  ),
                  
                  const SizedBox(height: 16),
                  
                  DropdownButtonFormField<String>(
                    value: selectedSessionType,
                    decoration: const InputDecoration(
                      labelText: 'Session Type',
                      border: InputBorder.none,
                      labelStyle: TextStyle(color: Colors.white70),
                    ),
                    style: const TextStyle(color: Colors.white),
                    dropdownColor: Colors.blue[800],
                    items: sessionTypes.map((type) {
                      return DropdownMenuItem(
                        value: type,
                        child: Text(
                          type.toUpperCase(),
                          style: const TextStyle(color: Colors.white),
                        ),
                      );
                    }).toList(),
                    onChanged: (String? value) {
                      setState(() {
                        selectedSessionType = value ?? 'study';
                      });
                    },
                  ),
                ],
              ),
            ),
            
            const SizedBox(height: 24),
            
            // Time and Day Selection
            _buildSectionTitle('Schedule'),
            _buildPreferenceBadge(),
            GlassCard(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  // Day Selection
                  Row(
                    children: [
                      const Icon(Icons.calendar_today, color: Colors.white70),
                      const SizedBox(width: 12),
                      const Text(
                        'Day:',
                        style: TextStyle(color: Colors.white70),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: selectedDay,
                          decoration: const InputDecoration(
                            border: InputBorder.none,
                            labelStyle: TextStyle(color: Colors.white70),
                          ),
                          style: const TextStyle(color: Colors.white),
                          dropdownColor: Colors.blue[800],
                          items: days.map((day) {
                            return DropdownMenuItem(
                              value: day,
                              child: Text(
                                day,
                                style: const TextStyle(color: Colors.white),
                              ),
                            );
                          }).toList(),
                          onChanged: (String? value) {
                            setState(() {
                              selectedDay = value ?? 'Monday';
                            });
                          },
                        ),
                      ),
                    ],
                  ),
                  
                  const SizedBox(height: 16),
                  
                  // Time Selection
                  Row(
                    children: [
                      const Icon(Icons.access_time, color: Colors.white70),
                      const SizedBox(width: 12),
                      const Text(
                        'Time:',
                        style: TextStyle(color: Colors.white70),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: selectedStartTime,
                          decoration: const InputDecoration(
                            labelText: 'Start',
                            border: InputBorder.none,
                            labelStyle: TextStyle(color: Colors.white70),
                          ),
                          style: const TextStyle(color: Colors.white),
                          dropdownColor: Colors.blue[800],
                          items: timeSlots.map((time) {
                            return DropdownMenuItem(
                              value: time,
                              child: Text(
                                time,
                                style: const TextStyle(color: Colors.white),
                              ),
                            );
                          }).toList(),
                          onChanged: (String? value) {
                            setState(() {
                              selectedStartTime = value ?? '09:00';
                              // Auto-adjust end time
                              final startHour = int.parse(value!.split(':')[0]);
                              final endHour = startHour + 1;
                              selectedEndTime = '${endHour.toString().padLeft(2, '0')}:00';
                            });
                          },
                        ),
                      ),
                      
                      const SizedBox(width: 16),
                      
                      const Text(
                        'to',
                        style: TextStyle(color: Colors.white70),
                      ),
                      
                      const SizedBox(width: 16),
                      
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: selectedEndTime,
                          decoration: const InputDecoration(
                            labelText: 'End',
                            border: InputBorder.none,
                            labelStyle: TextStyle(color: Colors.white70),
                          ),
                          style: const TextStyle(color: Colors.white),
                          dropdownColor: Colors.blue[800],
                          items: timeSlots.where((time) {
                            final startHour = int.parse(selectedStartTime.split(':')[0]);
                            final timeHour = int.parse(time.split(':')[0]);
                            return timeHour > startHour;
                          }).map((time) {
                            return DropdownMenuItem(
                              value: time,
                              child: Text(
                                time,
                                style: const TextStyle(color: Colors.white),
                              ),
                            );
                          }).toList(),
                          onChanged: (String? value) {
                            setState(() {
                              selectedEndTime = value ?? '10:00';
                            });
                          },
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            
            const SizedBox(height: 24),
            
            // Notes only (venue removed)
            _buildSectionTitle('Additional Details'),
            GlassCard(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  
                  TextFormField(
                    controller: _notesController,
                    decoration: const InputDecoration(
                      labelText: 'Notes (Optional)',
                      border: InputBorder.none,
                      labelStyle: TextStyle(color: Colors.white70),
                    ),
                    style: const TextStyle(color: Colors.white),
                    maxLines: 3,
                  ),
                ],
              ),
            ),
            
            const SizedBox(height: 32),
            
            // Create Button
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: _createSession,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: Colors.blue,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text(
                  'Create Study Session',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),
            
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Text(
        title,
        style: const TextStyle(
          fontSize: 18,
          fontWeight: FontWeight.bold,
          color: Colors.white,
        ),
      ),
    );
  }

  Widget _buildPreferenceBadge() {
    const emojis = {
      'morning': '🌅',
      'afternoon': '☀️',
      'evening': '🌆',
      'night': '🌙',
      'balanced': '⚡',
    };
    const labels = {
      'morning': 'Early Bird',
      'afternoon': 'Afternoon',
      'evening': 'Evening',
      'night': 'Night Owl',
      'balanced': 'Flexible',
    };
    final emoji = emojis[_studyPreference] ?? '⚡';
    final label = labels[_studyPreference] ?? 'Flexible';
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _conflictAdjusted ? '📚' : emoji,
                  style: const TextStyle(fontSize: 13),
                ),
                const SizedBox(width: 5),
                Text(
                  _conflictAdjusted
                      ? 'Adjusted to avoid your class schedule'
                      : 'Based on your $label preference',
                  style: TextStyle(
                    color: _conflictAdjusted ? Colors.orange[200] : Colors.white60,
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

  Future<void> _createSession() async {
    if (_formKey.currentState!.validate() && selectedModule != null) {
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

        // Calculate duration in minutes
        final duration = _calculateDuration(selectedStartTime, selectedEndTime);
        
        final session = StudySession(
          title: _titleController.text.trim(),
          moduleCode: selectedModule!.moduleCode,
          moduleName: selectedModule!.moduleName,
          dayOfWeek: selectedDay,
          startTime: selectedStartTime,
          endTime: selectedEndTime,
          venue: null, // Venue not needed per user request
          sessionType: selectedSessionType,
          notes: _notesController.text.trim().isEmpty ? null : _notesController.text.trim(),
          duration: duration,
        );
        
        // Save the session using the service
        final success = await StudySessionService.addStudySession(widget.student.studentId, session);
        
        if (!success) {
          throw Exception('Failed to save study session');
        }

        // Close loading dialog
        Navigator.pop(context);
        
        // Show success message
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Study session "${session.title}" created and saved successfully!'),
            backgroundColor: Colors.green,
            duration: const Duration(seconds: 3),
          ),
        );
        
        // Return to previous screen with the created session
        Navigator.pop(context, session);
      } catch (e) {
        // Close loading dialog
        Navigator.pop(context);
        
        // Show error message
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error creating session: $e'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  // Auto-generate methods removed per user request

  // Calculate duration in minutes
  int _calculateDuration(String startTime, String endTime) {
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
      debugPrint('Error calculating duration: $e');
    }
    
    return 60; // Default fallback
  }
}

/// Simple start/end minute range for class-conflict detection.
class _TimeInterval {
  final int start;
  final int end;
  const _TimeInterval(this.start, this.end);
}
