import 'package:flutter/material.dart';
import '../models/lecturer.dart';
import '../services/api_service.dart';
import '../services/local_storage_service.dart';

class LecturerDashboardScreen extends StatefulWidget {
  final Lecturer lecturer;

  const LecturerDashboardScreen({
    super.key,
    required this.lecturer,
  });

  @override
  State<LecturerDashboardScreen> createState() =>
      _LecturerDashboardScreenState();
}

class _LecturerDashboardScreenState extends State<LecturerDashboardScreen> {
  bool _isLoading = true;
  bool _isPublishing = false;
  String? _error;

  List<Map<String, dynamic>> _sessions = [];
  List<Map<String, dynamic>> _modules = [];
  List<Map<String, dynamic>> _sharedCalendarItems = [];

  int? _selectedModuleId;
  final _titleController = TextEditingController();
  final _durationController = TextEditingController(text: '60');
  DateTime? _selectedDate;
  TimeOfDay? _selectedTime;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _titleController.dispose();
    _durationController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    final response =
        await ApiService.getLecturerTimetable(widget.lecturer.lecturerId);
    if (response['success'] != true) {
      setState(() {
        _error =
            response['message']?.toString() ?? 'Failed to load lecturer data.';
        _isLoading = false;
      });
      return;
    }

    final data = (response['data'] is Map<String, dynamic>)
        ? response['data'] as Map<String, dynamic>
        : <String, dynamic>{};
    final sessionsRaw = (data['sessions'] is List)
        ? List<dynamic>.from(data['sessions'])
        : <dynamic>[];
    final sessions = sessionsRaw
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();

    final moduleMap = <int, Map<String, dynamic>>{};
    for (final row in sessions) {
      final moduleId = int.tryParse('${row['module_id'] ?? 0}') ?? 0;
      if (moduleId > 0) {
        moduleMap[moduleId] = {
          'module_id': moduleId,
          'module_code': '${row['module_code'] ?? ''}',
          'module_name': '${row['module_name'] ?? ''}',
        };
      }
    }

    final modules = moduleMap.values.toList()
      ..sort((a, b) => '${a['module_code']}'.compareTo('${b['module_code']}'));

    setState(() {
      _sessions = sessions;
      _modules = modules;
      _selectedModuleId =
          modules.isNotEmpty ? modules.first['module_id'] as int : null;
      _isLoading = false;
    });

    if (_selectedModuleId != null) {
      await _loadSharedCalendar(_selectedModuleId!);
    }
  }

  Future<void> _loadSharedCalendar(int moduleId) async {
    final response =
        await ApiService.getSharedAssessmentCalendar(moduleId, days: 45);
    if (response['success'] == true) {
      final data = (response['data'] is Map<String, dynamic>)
          ? response['data'] as Map<String, dynamic>
          : <String, dynamic>{};
      final itemsRaw = (data['items'] is List)
          ? List<dynamic>.from(data['items'])
          : <dynamic>[];
      final items = itemsRaw
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList();
      if (mounted) {
        setState(() {
          _sharedCalendarItems = items;
        });
      }
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: now.add(const Duration(days: 7)),
      firstDate: now,
      lastDate: now.add(const Duration(days: 365)),
    );
    if (selected != null && mounted) {
      setState(() {
        _selectedDate = selected;
      });
    }
  }

  Future<void> _pickTime() async {
    final selected = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 9, minute: 0),
    );
    if (selected != null && mounted) {
      setState(() {
        _selectedTime = selected;
      });
    }
  }

  String _dateToApi(DateTime value) {
    final m = value.month.toString().padLeft(2, '0');
    final d = value.day.toString().padLeft(2, '0');
    return '${value.year}-$m-$d';
  }

  String _timeToApi(TimeOfDay value) {
    final h = value.hour.toString().padLeft(2, '0');
    final m = value.minute.toString().padLeft(2, '0');
    return '$h:$m:00';
  }

  String _shortTime(String value) {
    if (value.length >= 5) return value.substring(0, 5);
    return value;
  }

  Future<void> _publishAssessment() async {
    if (_selectedModuleId == null) return;
    if (_titleController.text.trim().isEmpty ||
        _selectedDate == null ||
        _selectedTime == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Complete title, date and time.')),
      );
      return;
    }

    final duration = int.tryParse(_durationController.text.trim()) ?? 60;
    setState(() {
      _isPublishing = true;
    });

    final response = await ApiService.createLecturerAssessment(
      lecturerId: widget.lecturer.lecturerId,
      moduleId: _selectedModuleId!,
      title: _titleController.text.trim(),
      assessmentDate: _dateToApi(_selectedDate!),
      assessmentTime: _timeToApi(_selectedTime!),
      duration: duration,
    );

    if (!mounted) return;
    setState(() {
      _isPublishing = false;
    });

    if (response['success'] == true) {
      final data = (response['data'] is Map<String, dynamic>)
          ? response['data'] as Map<String, dynamic>
          : <String, dynamic>{};
      final risk = '${data['risk'] ?? ''}'.toUpperCase();
      final conflictCount = '${data['conflict_count'] ?? 0}';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content:
                Text('Published. Risk: $risk, nearby items: $conflictCount.')),
      );
      _titleController.clear();
      _durationController.text = '60';
      _selectedDate = null;
      _selectedTime = null;
      await _loadSharedCalendar(_selectedModuleId!);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text(
                response['message']?.toString() ?? 'Could not publish test.')),
      );
    }
  }

  Future<void> _logout() async {
    final storage = LocalStorageService();
    await storage.initialize();
    await storage.clearLecturer();
    if (!mounted) return;
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Lecturer: ${widget.lecturer.lecturerName}'),
        actions: [
          IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
          IconButton(
            onPressed: _logout,
            icon: const Icon(Icons.logout),
            tooltip: 'Sign out',
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    _buildPublishCard(),
                    const SizedBox(height: 16),
                    _buildSessionsCard(),
                    const SizedBox(height: 16),
                    _buildSharedCalendarCard(),
                  ],
                ),
    );
  }

  Widget _buildPublishCard() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Publish Test',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              value: _selectedModuleId,
              decoration: const InputDecoration(labelText: 'Module'),
              items: _modules
                  .map(
                    (m) => DropdownMenuItem<int>(
                      value: m['module_id'] as int,
                      child: Text('${m['module_code']} - ${m['module_name']}'),
                    ),
                  )
                  .toList(),
              onChanged: (value) {
                setState(() => _selectedModuleId = value);
                if (value != null) {
                  _loadSharedCalendar(value);
                }
              },
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _titleController,
              decoration: const InputDecoration(labelText: 'Assessment title'),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _pickDate,
                    icon: const Icon(Icons.calendar_today),
                    label: Text(
                      _selectedDate == null
                          ? 'Select date'
                          : _dateToApi(_selectedDate!),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _pickTime,
                    icon: const Icon(Icons.schedule),
                    label: Text(
                      _selectedTime == null
                          ? 'Select time'
                          : _selectedTime!.format(context),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _durationController,
              keyboardType: TextInputType.number,
              decoration:
                  const InputDecoration(labelText: 'Duration (minutes)'),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _isPublishing ? null : _publishAssessment,
                child: _isPublishing
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Publish + Notify Students'),
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Notifications: immediate, 7 days before, and 1 day before.',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSessionsCard() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('My Timetable',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 10),
            if (_sessions.isEmpty)
              const Text('No timetable sessions found.')
            else
              ..._sessions.take(15).map((s) {
                final module =
                    '${s['module_code'] ?? ''} ${s['module_name'] ?? ''}'
                        .trim();
                final day = '${s['day_of_week'] ?? ''}';
                final start = '${s['start_time'] ?? ''}';
                final end = '${s['end_time'] ?? ''}';
                final venue = '${s['venue_name'] ?? '-'}';
                return ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  title: Text(module),
                  subtitle: Text(
                      '$day  ${_shortTime(start)}-${_shortTime(end)}  |  $venue'),
                );
              }),
          ],
        ),
      ),
    );
  }

  Widget _buildSharedCalendarCard() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Shared-Course Assessment Calendar',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            const Text(
              'Use this to avoid test clustering for shared cohorts.',
              style: TextStyle(color: Colors.grey),
            ),
            const SizedBox(height: 10),
            if (_sharedCalendarItems.isEmpty)
              const Text('No shared assessment items found.')
            else
              ..._sharedCalendarItems.take(20).map((item) {
                final type = '${item['item_type'] ?? 'Item'}';
                final module = '${item['module_code'] ?? ''}';
                final date = '${item['item_date'] ?? ''}';
                final time = '${item['item_time'] ?? ''}';
                final lecturerName = item['lecturer_name']?.toString();
                final lecturerPart =
                    (lecturerName != null && lecturerName.isNotEmpty)
                        ? ' | $lecturerName'
                        : '';
                return ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: CircleAvatar(
                    radius: 14,
                    child: Text(type.substring(0, 1)),
                  ),
                  title: Text('$type - $module'),
                  subtitle: Text(
                      '$date ${time.length >= 5 ? time.substring(0, 5) : time}$lecturerPart'),
                );
              }),
          ],
        ),
      ),
    );
  }
}
