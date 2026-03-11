import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import '../models/outline_event.dart';
import '../models/module.dart';
import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import '../config/app_colors.dart';
import '../services/local_storage_service.dart';
import '../config/ai_config.dart';
import 'package:intl/intl.dart';
import 'dart:convert';

import '../services/pdf_js_interop.dart' if (dart.library.io) '../services/pdf_stub_interop.dart' as pdf_js;

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
  String? _selectedFileName;
  Module? _selectedModule;
  bool _isExtracting = false;
  String? _pdfJobError;
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
    if (!kIsWeb) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Syllabus Scanner is only available on the web app.'),
        ),
      );
      return;
    }

    if (_selectedModule == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a module first.')),
      );
      return;
    }
    if (AIConfig.geminiApiKey.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Gemini API key is missing. Add GEMINI_API_KEY in .env or --dart-define.',
          ),
          duration: Duration(seconds: 6),
        ),
      );
      return;
    }

    setState(() {
      _isExtracting = true;
      _extractedEvents = [];
      _selectedFileName = null;
      _pdfJobError = null;
    });

    try {
      // This call does: pick file -> extract text -> call Gemini -> return small result
      // All inside JS. Only ~2KB result crosses the WASM bridge.
      final result = await pdf_js.pickAndExtractAndAnalyzePdf(
        geminiApiKey: AIConfig.geminiApiKey,
        moduleCode: _selectedModule!.moduleCode,
      );

      final fileName = result['name'] as String;
      final rawEventsJson = result['events'] as String;

      // Strip markdown fences Gemini sometimes wraps around JSON
      final cleaned = rawEventsJson
          .replaceAll(RegExp(r'```json\n?|^```\n?|```$', multiLine: true), '')
          .trim();

      final List<dynamic> decoded = _decodeEventsJson(cleaned);
      final events = decoded
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .map((e) => _normalizeRawEvent(e, _selectedModule!.moduleCode))
          .whereType<Map<String, dynamic>>()
          .map(OutlineEvent.fromJson)
          .toList();

      if (!mounted) return;
      setState(() {
        _selectedFileName = fileName;
        _extractedEvents = events;
        _isExtracting = false;
        _pdfJobError = null;
      });

      if (events.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No dates found in this document. Try a different PDF.'),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isExtracting = false;
        _pdfJobError = e.toString();
      });

      final msg = e.toString();
      if (msg.toLowerCase().contains('cancel')) return; // user cancelled, stay silent

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed: $msg'),
          duration: const Duration(seconds: 6),
          action: SnackBarAction(label: 'Dismiss', onPressed: () {}),
        ),
      );
    }
  }

  Future<void> _startExtraction() async => _pickFile();

  List<dynamic> _decodeEventsJson(String rawJson) {
    try {
      return jsonDecode(rawJson) as List<dynamic>;
    } catch (_) {
      final start = rawJson.indexOf('[');
      final end = rawJson.lastIndexOf(']');
      if (start == -1 || end == -1 || end <= start) {
        throw const FormatException('Could not find a valid JSON array in AI response.');
      }
      final sliced = rawJson.substring(start, end + 1);
      return jsonDecode(sliced) as List<dynamic>;
    }
  }

  Map<String, dynamic>? _normalizeRawEvent(
    Map<String, dynamic> raw,
    String moduleCode,
  ) {
    final parsedDate = _tryParseDate(raw['date']?.toString());
    if (parsedDate == null) return null;

    return {
      'title': (raw['title'] ?? '').toString().trim().isEmpty
          ? 'Untitled event'
          : raw['title'].toString().trim(),
      'date': parsedDate.toIso8601String(),
      'type': _normalizeType(raw['type']?.toString()),
      'moduleCode': moduleCode,
      'venue': raw['venue']?.toString(),
      'time': raw['time']?.toString(),
      'isReminderSet': false,
    };
  }

  DateTime? _tryParseDate(String? input) {
    if (input == null || input.trim().isEmpty) return null;
    final value = input.trim();

    try {
      return DateTime.parse(value);
    } catch (_) {}

    const formats = ['dd/MM/yyyy', 'd/M/yyyy', 'dd-MM-yyyy', 'd-M-yyyy'];
    for (final format in formats) {
      try {
        return DateFormat(format).parseStrict(value);
      } catch (_) {}
    }
    return null;
  }

  String _normalizeType(String? rawType) {
    final value = (rawType ?? '').toLowerCase().trim();
    if (value.contains('test')) return 'Test';
    if (value.contains('exam')) return 'Exam';
    if (value.contains('practical') || value.contains('lab')) return 'Practical';
    return 'Assignment';
  }

  Future<void> _saveEvents() async {
    if (_extractedEvents.isEmpty) return;

    await _storageService.saveOutlineEvents(_extractedEvents);

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Successfully saved ${_extractedEvents.length} events to your schedule!')),
    );
    Navigator.pop(context);
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
                      if (_pdfJobError != null) ...[
                        const SizedBox(height: 12),
                        GlassCard(
                          padding: const EdgeInsets.all(12),
                          child: Text(
                            'Upload error: $_pdfJobError',
                            style: const TextStyle(
                              color: Colors.redAccent,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
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
            'Extract dates from your syllabus PDF',
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
                    _selectedFileName == null ? Icons.upload_file : Icons.check_circle,
                    size: 40,
                    color: _selectedFileName == null ? Colors.white54 : Colors.greenAccent,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _selectedFileName == null 
                        ? 'Select PDF file' 
                        : _selectedFileName!,
                    style: const TextStyle(color: Colors.white70),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Note: For older Word files (.doc), please "Save As PDF" before uploading.',
            style: TextStyle(color: Colors.white54, fontSize: 12, fontStyle: FontStyle.italic),
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
