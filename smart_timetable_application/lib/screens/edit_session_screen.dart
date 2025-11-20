import 'package:flutter/material.dart';
import '../models/student.dart';
import '../models/module.dart';
import '../models/study_session.dart';

import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import '../services/study_session_service.dart';

class EditSessionScreen extends StatefulWidget {
  final Student student;
  final StudySession session;
  final List<Module> studentModules;

  const EditSessionScreen({
    Key? key,
    required this.student,
    required this.session,
    required this.studentModules,
  }) : super(key: key);

  @override
  State<EditSessionScreen> createState() => _EditSessionScreenState();
}

class _EditSessionScreenState extends State<EditSessionScreen> {
  final _formKey = GlobalKey<FormState>();
  
  late Module selectedModule;
  late String selectedDay;
  late String selectedStartTime;
  late String selectedEndTime;
  late String selectedSessionType;
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
    // Initialize with current session values
    if (widget.studentModules.isNotEmpty) {
      try {
        selectedModule = widget.studentModules.firstWhere(
          (module) => module.moduleCode == widget.session.moduleCode,
        );
      } catch (e) {
        // If no matching module found, use the first available module
        selectedModule = widget.studentModules.first;
      }
    } else {
      // Create a fallback module if no modules are available
      selectedModule = Module(
        moduleId: 0,
        moduleCode: widget.session.moduleCode,
        moduleName: widget.session.moduleName,
        credits: 0,
        semester: 'Unknown',
      );
    }
    selectedDay = widget.session.dayOfWeek;
    selectedStartTime = widget.session.startTime;
    selectedEndTime = widget.session.endTime;
    selectedSessionType = widget.session.sessionType;
    // Venue removed - not needed per user request
    _titleController.text = widget.session.title;
    _notesController.text = widget.session.notes ?? '';
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
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFF4A90E2),
              Color(0xFF3B82F6),
              Color(0xFF50E3C2),
            ],
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
          
          SizedBox(width: 16),
          
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Edit Study Session',
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
                Text(
                  'Update your study session details',
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
              child: DropdownButtonFormField<Module>(
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
                     selectedModule = value!;
                     _titleController.text = 'Study ${value.moduleName}';
                   });
                 },
                validator: (value) {
                  if (value == null) {
                    return 'Please select a module';
                  }
                  return null;
                },
              ),
            ),
            
            SizedBox(height: 24),
            
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
                  
                  SizedBox(height: 16),
                  
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
                     selectedSessionType = value!;
                   });
                 },
                  ),
                ],
              ),
            ),
            
            SizedBox(height: 24),
            
            // Time and Day Selection
            _buildSectionTitle('Schedule'),
            GlassCard(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  // Day Selection
                  Row(
                    children: [
                      const Icon(Icons.calendar_today, color: Colors.white70),
                      SizedBox(width: 12),
                      const Text(
                        'Day:',
                        style: TextStyle(color: Colors.white70),
                      ),
                      SizedBox(width: 16),
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
                  
                  SizedBox(height: 16),
                  
                  // Time Selection
                  Row(
                    children: [
                      const Icon(Icons.access_time, color: Colors.white70),
                      SizedBox(width: 12),
                      const Text(
                        'Time:',
                        style: TextStyle(color: Colors.white70),
                      ),
                      SizedBox(width: 16),
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
                      
                      SizedBox(width: 16),
                      
                      const Text(
                        'to',
                        style: TextStyle(color: Colors.white70),
                      ),
                      
                      SizedBox(width: 16),
                      
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
            
            SizedBox(height: 24),
            
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
            
            SizedBox(height: 32),
            
            // Action Buttons
            Row(
              children: [
                // Delete Button
                Expanded(
                  child: SizedBox(
                    height: 50,
                    child: OutlinedButton.icon(
                      onPressed: _showDeleteConfirmation,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.red,
                        side: const BorderSide(color: Colors.red),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      icon: const Icon(Icons.delete),
                      label: const Text(
                        'Delete Session',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                ),
                
                SizedBox(width: 16),
                
                // Update Button
                Expanded(
                  child: SizedBox(
                    height: 50,
                    child: ElevatedButton(
                      onPressed: _updateSession,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.white,
                        foregroundColor: Colors.blue,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        'Update Session',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
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

  Future<void> _updateSession() async {
    if (_formKey.currentState!.validate()) {
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
        
        // Create updated session
        final updatedSession = StudySession(
          title: _titleController.text.trim(),
          moduleCode: selectedModule.moduleCode,
          moduleName: selectedModule.moduleName,
          dayOfWeek: selectedDay,
          startTime: selectedStartTime,
          endTime: selectedEndTime,
          venue: null, // Venue removed per user request
          sessionType: selectedSessionType,
          notes: _notesController.text.trim().isEmpty ? null : _notesController.text.trim(),
          duration: duration,
        );

        // Update the session using the service
        final success = await StudySessionService.updateStudySession(
          widget.student.studentId, 
          widget.session, 
          updatedSession
        );
        
        if (!success) {
          throw Exception('Failed to update study session');
        }

        // Close loading dialog
        Navigator.pop(context);

        // Show success message
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Study session "${updatedSession.title}" updated successfully!'),
            backgroundColor: Colors.green,
            duration: const Duration(seconds: 3),
          ),
        );

        // Return to previous screen with the updated session
        Navigator.pop(context, updatedSession);
      } catch (e) {
        // Close loading dialog
        Navigator.pop(context);

        // Show error message
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error updating session: $e'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  void _showDeleteConfirmation() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.red[800],
        title: const Text(
          'Delete Study Session',
          style: TextStyle(color: Colors.white),
        ),
        content: Text(
          'Are you sure you want to delete "${widget.session.title}"? This action cannot be undone.',
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
            onPressed: () {
              Navigator.pop(context);
              _deleteSession();
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

  Future<void> _deleteSession() async {
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

      // Delete the session using the service
      final success = await StudySessionService.deleteStudySession(
        widget.student.studentId, 
        widget.session
      );
      
      if (!success) {
        throw Exception('Failed to delete study session');
      }

      // Close loading dialog
      Navigator.pop(context);

      // Show success message
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Study session "${widget.session.title}" deleted successfully!'),
          backgroundColor: Colors.green,
          duration: const Duration(seconds: 3),
        ),
      );

      // Return to previous screen with deletion signal
      Navigator.pop(context, 'deleted');
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
