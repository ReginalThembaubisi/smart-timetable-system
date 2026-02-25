import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/outline_event.dart';
import '../models/module.dart';
import '../services/local_storage_service.dart';
import '../services/notification_service.dart';
import '../widgets/glass_card.dart';
import '../config/app_colors.dart';

class DeadlinesScreen extends StatefulWidget {
  final List<Module> modules;

  const DeadlinesScreen({Key? key, required this.modules}) : super(key: key);

  @override
  State<DeadlinesScreen> createState() => _DeadlinesScreenState();
}

class _DeadlinesScreenState extends State<DeadlinesScreen> {
  final _storageService = LocalStorageService();
  List<OutlineEvent> _allEvents = [];
  List<OutlineEvent> _filteredEvents = [];
  String _selectedType = 'All';
  Module? _selectedModule;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    await _storageService.initialize();
    final events = await _storageService.getOutlineEvents();
    setState(() {
      _allEvents = events;
      _filteredEvents = events;
      _isLoading = false;
    });
  }

  void _filterEvents() {
    setState(() {
      _filteredEvents = _allEvents.where((e) {
        final matchesType = _selectedType == 'All' || e.type.toLowerCase() == _selectedType.toLowerCase();
        final matchesModule = _selectedModule == null || e.moduleCode == _selectedModule!.moduleCode;
        return matchesType && matchesModule;
      }).toList();
      
      // Sort by date (nearest first)
      _filteredEvents.sort((a, b) => a.date.compareTo(b.date));
    });
  }

  Future<void> _toggleReminder(OutlineEvent event) async {
    if (event.isReminderSet) {
      // Cancel reminder
      if (event.reminderId != null) {
        await NotificationService.cancelDeadlineReminder(event.reminderId!);
      }
      
      final updatedEvent = event.copyWith(isReminderSet: false, reminderId: null);
      await _updateEvent(updatedEvent);
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Reminder canceled')),
        );
      }
    } else {
      // Schedule reminder (7 days before)
      final reminderId = await NotificationService.scheduleDeadlinesReminder(
        event.title, 
        event.date, 
        event.type
      );
      
      if (reminderId != null) {
        final updatedEvent = event.copyWith(isReminderSet: true, reminderId: reminderId);
        await _updateEvent(updatedEvent);
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Reminder set for 7 days before the deadline!')),
          );
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Could not schedule reminder. Date might be too close.')),
          );
        }
      }
    }
  }

  Future<void> _updateEvent(OutlineEvent updatedEvent) async {
    // Find and update in the local list
    final index = _allEvents.indexWhere((e) => e.title == updatedEvent.title && e.date == updatedEvent.date);
    if (index != -1) {
      setState(() {
        _allEvents[index] = updatedEvent;
        _filterEvents();
      });
      // Save all events to storage (storage service handles list replacement or merging)
      await _storageService.saveOutlineEvents(_allEvents);
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
              _buildFilters(),
              Expanded(
                child: _isLoading 
                  ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
                  : _filteredEvents.isEmpty 
                    ? _buildEmptyState()
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        itemCount: _filteredEvents.length,
                        itemBuilder: (context, index) => _buildEventCard(_filteredEvents[index]),
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
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.arrow_back, color: Colors.white),
          ),
          const SizedBox(width: 8),
          const Text(
            'Academic Deadlines',
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilters() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        children: [
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: ['All', 'Test', 'Assignment', 'Exam', 'Practical'].map((type) {
                final isSelected = _selectedType == type;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    label: Text(type),
                    selected: isSelected,
                    onSelected: (val) {
                      setState(() => _selectedType = type);
                      _filterEvents();
                    },
                    backgroundColor: Colors.white.withOpacity(0.1),
                    selectedColor: AppColors.primary.withOpacity(0.3),
                    labelStyle: TextStyle(
                      color: isSelected ? Colors.white : Colors.white70,
                      fontSize: 12,
                    ),
                    checkmarkColor: Colors.white,
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 8),
          DropdownButtonFormField<Module>(
            value: _selectedModule,
            dropdownColor: AppColors.surface,
            style: const TextStyle(color: Colors.white),
            decoration: InputDecoration(
              isDense: true,
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              prefixIcon: const Icon(Icons.book, size: 16, color: Colors.white70),
              labelText: 'Filter by Module',
              labelStyle: const TextStyle(color: Colors.white70, fontSize: 13),
            ),
            items: [
              const DropdownMenuItem<Module>(
                value: null,
                child: Text('All Modules'),
              ),
              ...widget.modules.map((m) => DropdownMenuItem(
                value: m,
                child: Text('${m.moduleCode}: ${m.moduleName}', overflow: TextOverflow.ellipsis),
              )),
            ],
            onChanged: (val) {
              setState(() => _selectedModule = val);
              _filterEvents();
            },
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.event_busy, size: 64, color: Colors.white.withOpacity(0.2)),
          const SizedBox(height: 16),
          const Text(
            'No deadlines found',
            style: TextStyle(color: Colors.white70, fontSize: 16),
          ),
          const Text(
            'Scan a module outline to get started',
            style: TextStyle(color: Colors.white38, fontSize: 14),
          ),
        ],
      ),
    );
  }

  Widget _buildEventCard(OutlineEvent event) {
    final bool isPast = event.date.isBefore(DateTime.now().subtract(const Duration(days: 1)));
    final dateStr = DateFormat('EEE, MMM d, y').format(event.date);
    
    return GlassCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: _getTypeColor(event.type).withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(_getTypeIcon(event.type), color: _getTypeColor(event.type), size: 24),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  event.title,
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: isPast ? Colors.white38 : Colors.white,
                    fontSize: 15,
                    decoration: isPast ? TextDecoration.lineThrough : null,
                  ),
                ),
                Text(
                  event.moduleCode,
                  style: TextStyle(color: AppColors.primary, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                   Icon(Icons.calendar_today, size: 12, color: Colors.white54),
                   const SizedBox(width: 4),
                   Text(dateStr, style: const TextStyle(color: Colors.white54, fontSize: 12)),
                  ],
                ),
              ],
            ),
          ),
          // Reminder Toggle
          if (!isPast)
            IconButton(
              icon: Icon(
                event.isReminderSet ? Icons.notifications_active : Icons.notifications_none,
                color: event.isReminderSet ? Colors.yellowAccent : Colors.white38,
              ),
              onPressed: () => _toggleReminder(event),
            ),
        ],
      ),
    );
  }

  Color _getTypeColor(String type) {
    switch (type.toLowerCase()) {
      case 'test': return Colors.orangeAccent;
      case 'assignment': return Colors.blueAccent;
      case 'exam': return Colors.redAccent;
      case 'practical': return Colors.tealAccent;
      default: return Colors.white70;
    }
  }

  IconData _getTypeIcon(String type) {
    switch (type.toLowerCase()) {
      case 'test': return Icons.quiz;
      case 'assignment': return Icons.assignment;
      case 'exam': return Icons.workspace_premium;
      case 'practical': return Icons.science;
      default: return Icons.event;
    }
  }
}
