import 'package:flutter/material.dart';
import '../services/outline_service.dart';
import '../models/outline_event.dart';
import '../models/module.dart';
import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import '../config/app_colors.dart';
import '../services/local_storage_service.dart';
import '../config/ai_config.dart';
import 'package:intl/intl.dart';

class OutlineUploadScreen extends StatefulWidget {
  final List<Module> modules;
  
  const OutlineUploadScreen({
    Key? key,
    required this.modules,
  }) : super(key: key);

  @override
  State<OutlineUploadScreen> createState() => _OutlineUploadScreenState();
}

class _OutlineUploadScreenState extends State<OutlineUploadScreen> {
  final TextEditingController _textController = TextEditingController();
  Module? _selectedModule;
  bool _isExtracting = false;
  List<OutlineEvent> _extractedEvents = [];
  final _storageService = LocalStorageService();
  
  @override
  void initState() {
    super.initState();
    _initStorage();
  }

  Future<void> _initStorage() async {
    await _storageService.initialize();
  }

  @override
  void dispose() {
    _textController.dispose();
    super.dispose();
  }

  Future<void> _startExtraction() async {
    if (_textController.text.trim().isEmpty || _selectedModule == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a module and paste the syllabus text first.')),
      );
      return;
    }

    setState(() {
      _isExtracting = true;
      _extractedEvents = [];
    });

    try {
      final events = await OutlineService.extractEventsFromText(
        _textController.text,
        AIConfig.geminiApiKey,
        _selectedModule!.moduleCode,
      );

      setState(() {
        _extractedEvents = events;
        _isExtracting = false;
      });
    } catch (e) {
      setState(() {
        _isExtracting = false;
      });
      
      String errorMessage = e.toString();
      if (errorMessage.contains('Exception: ')) {
        errorMessage = errorMessage.split('Exception: ').last;
      } 
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(errorMessage),
          duration: const Duration(seconds: 5),
          action: SnackBarAction(label: 'Dismiss', onPressed: () {}),
        ),
      );
    }
  }

  Future<void> _saveEvents() async {
    if (_extractedEvents.isEmpty) return;

    await _storageService.saveOutlineEvents(_extractedEvents);
    
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Successfully saved ${_extractedEvents.length} events to your schedule!')),
      );
      Navigator.pop(context);
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
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildUploadForm(),
                      if (_isExtracting) _buildLoadingState(),
                      if (_extractedEvents.isNotEmpty) ...[
                        const SizedBox(height: 24),
                        const Text(
                          'Review Extracted Dates',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 12),
                        ..._extractedEvents.map((e) => _buildEventCard(e)).toList(),
                        const SizedBox(height: 24),
                        GlassButton(
                          onPressed: _saveEvents,
                          child: const Center(
                            child: Text(
                              'Confirm & Add to Schedule',
                              style: TextStyle(fontWeight: FontWeight.bold),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
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
            'Smart Paste Schedule',
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildUploadForm() {
    return GlassCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Paste your syllabus text here',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 16),
          
          // Module Selection
          DropdownButtonFormField<Module>(
            value: _selectedModule,
            dropdownColor: AppColors.surface,
            style: const TextStyle(color: Colors.white),
            items: widget.modules.map((m) {
              return DropdownMenuItem(
                value: m,
                child: Text('${m.moduleCode}: ${m.moduleName}'),
              );
            }).toList(),
            onChanged: (val) => setState(() => _selectedModule = val),
            decoration: const InputDecoration(
              labelText: 'Select Module',
              labelStyle: TextStyle(color: Colors.white70),
              prefixIcon: Icon(Icons.book, color: Colors.white70),
              filled: true,
              fillColor: Colors.black12,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.all(Radius.circular(12)),
              ),
            ),
          ),
          const SizedBox(height: 20),

          // Text Paste Area
          TextField(
            controller: _textController,
            maxLines: 10,
            minLines: 5,
            style: const TextStyle(color: Colors.white, fontSize: 13),
            decoration: InputDecoration(
              hintText: "Open your PDF/DOCX, select all text (Ctrl+A / Cmd+A), copy, and paste it all here...",
              hintStyle: const TextStyle(color: Colors.white38),
              filled: true,
              fillColor: Colors.black12,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.white.withOpacity(0.2)),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.white.withOpacity(0.2)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: AppColors.primary),
              ),
            ),
          ),
          
          const SizedBox(height: 24),

          GlassButton(
            onPressed: _isExtracting ? null : _startExtraction,
            child: Center(
              child: _isExtracting 
                  ? const SizedBox(
                      height: 20, 
                      width: 20, 
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Text('Extract Dates', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLoadingState() {
    return Padding(
      padding: const EdgeInsets.only(top: 40),
      child: Center(
        child: Column(
          children: [
            const CircularProgressIndicator(color: AppColors.primary),
            const SizedBox(height: 16),
            const Text(
              'AI is analyzing your text...',
              style: TextStyle(color: Colors.white70),
            ),
            const SizedBox(height: 4),
            const Text(
              'This usually takes about 5-10 seconds',
              style: TextStyle(color: Colors.white38, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEventCard(OutlineEvent event) {
    final dateStr = DateFormat('EEEE, MMMM d, y').format(event.date);
    
    return GlassCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: _getTypeColor(event.type).withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _getTypeColor(event.type).withOpacity(0.3)),
            ),
            child: Icon(
              _getTypeIcon(event.type),
              color: _getTypeColor(event.type),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  event.title,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  dateStr,
                  style: const TextStyle(color: Colors.white70, fontSize: 13),
                ),
                if (event.venue != null || event.time != null)
                  Text(
                    '${event.time ?? ''} ${event.venue != null ? 'at ${event.venue}' : ''}',
                    style: const TextStyle(color: Colors.white54, fontSize: 12),
                  ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.1),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              event.type.toUpperCase(),
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: _getTypeColor(event.type),
              ),
            ),
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
