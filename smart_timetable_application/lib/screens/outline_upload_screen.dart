import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
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
  PlatformFile? _selectedFile;
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
    super.dispose();
  }

  Future<void> _pickFile() async {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'docx'],
      withData: true, // Required for web to get bytes
    );

    if (result != null) {
      setState(() {
        _selectedFile = result.files.single;
      });
    }
  }

  Future<void> _startExtraction() async {
    if (_selectedFile == null || _selectedModule == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a module and a file first.')),
      );
      return;
    }

    setState(() {
      _isExtracting = true;
      _extractedEvents = [];
    });

    try {
      final events = await OutlineService.extractEventsFromDocument(
        _selectedFile!.bytes!,
        _selectedFile!.name,
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
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Extraction failed: $e')),
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
            'Scan Module Outline',
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
            'Extract dates from your syllabus',
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
            ),
          ),
          const SizedBox(height: 20),

          // File Picker
          GestureDetector(
            onTap: _pickFile,
            child: Container(
              height: 120,
              width: double.infinity,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.05),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: Colors.white.withOpacity(0.2),
                  style: BorderStyle.solid,
                ),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    _selectedFile == null ? Icons.upload_file : Icons.check_circle,
                    size: 40,
                    color: _selectedFile == null ? Colors.white54 : Colors.greenAccent,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _selectedFile == null 
                        ? 'Select PDF or DOCX file' 
                        : _selectedFile!.name,
                    style: const TextStyle(color: Colors.white70),
                    textAlign: TextAlign.center,
                  ),
                ],
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
                  : const Text('Start AI Scan', style: TextStyle(fontWeight: FontWeight.bold)),
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
              'AI is analyzing your document...',
              style: TextStyle(color: Colors.white70),
            ),
            const SizedBox(height: 4),
            const Text(
              'This usually takes about 10-15 seconds',
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
