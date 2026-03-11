import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/outline_event.dart';
import '../models/module.dart';
import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import '../config/app_colors.dart';
import '../services/local_storage_service.dart';
import '../services/outline_service.dart';
import '../services/web_file_picker.dart' if (dart.library.io) '../services/web_file_picker_stub.dart' as web_picker;

class OutlineUploadScreen extends StatefulWidget {
  final List<Module> modules;

  const OutlineUploadScreen({super.key, required this.modules});

  @override
  State<OutlineUploadScreen> createState() => _OutlineUploadScreenState();
}

class _OutlineUploadScreenState extends State<OutlineUploadScreen> {
  final _textController = TextEditingController();
  Module? _selectedModule;
  bool _isAnalyzing = false;
  String? _errorMessage;
  List<OutlineEvent> _extractedEvents = [];
  final _storageService = LocalStorageService();

  @override
  void initState() {
    super.initState();
    _storageService.initialize();
  }

  @override
  void dispose() {
    _textController.dispose();
    super.dispose();
  }

  /// Upload a PDF file directly — PHP extracts the text and calls Gemini.
  Future<void> _uploadFile() async {
    if (_selectedModule == null) {
      _snack('Please select a module first.');
      return;
    }

    try {
      final picked = await web_picker.pickPdfFile();
      if (picked == null) return; // cancelled

      if (!mounted) return;
      setState(() {
        _isAnalyzing = true;
        _extractedEvents = [];
        _errorMessage = null;
      });

      final events = await OutlineService.extractEventsFromFile(
        picked.bytes,
        picked.name,
        _selectedModule!.moduleCode,
      );

      if (!mounted) return;
      setState(() {
        _extractedEvents = events;
        _isAnalyzing = false;
      });

      if (events.isEmpty) {
        _snack('No dates found. Try pasting the text instead.');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isAnalyzing = false;
        _errorMessage = e.toString();
      });
      final msg = e.toString();
      if (msg.toLowerCase().contains('cancel')) return;
      _snack('Upload failed: $msg', seconds: 8);
    }
  }

  Future<void> _analyze() async {
    if (_selectedModule == null) {
      _snack('Please select a module first.');
      return;
    }
    final text = _textController.text.trim();
    if (text.isEmpty) {
      _snack('Please paste your syllabus text first.');
      return;
    }
    setState(() {
      _isAnalyzing = true;
      _extractedEvents = [];
      _errorMessage = null;
    });

    try {
      final events = await OutlineService.extractEventsFromText(
        text,
        _selectedModule!.moduleCode,
      );

      if (!mounted) return;
      setState(() {
        _extractedEvents = events;
        _isAnalyzing = false;
      });

      if (events.isEmpty) {
        _snack('No dates found. Try adding more text from your syllabus.');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isAnalyzing = false;
        _errorMessage = e.toString();
      });
    }
  }

  Future<void> _saveEvents() async {
    if (_extractedEvents.isEmpty) return;
    await _storageService.saveOutlineEvents(_extractedEvents);
    if (!mounted) return;
    _snack('Saved ${_extractedEvents.length} events to your schedule!');
    Navigator.pop(context);
  }

  void _snack(String message, {int seconds = 3}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        duration: Duration(seconds: seconds),
      ),
    );
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
                      _buildInputForm(),
                      if (_isAnalyzing) _buildLoading(),
                      if (_errorMessage != null) _buildError(),
                      if (_extractedEvents.isNotEmpty) ...[
                        const SizedBox(height: 24),
                        const Text(
                          'Extracted Dates',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 12),
                        ..._extractedEvents.map(_buildEventCard),
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

  Widget _buildInputForm() {
    return GlassCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // How-to banner
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
            ),
            child: const Row(
              children: [
                Icon(Icons.lightbulb_outline, color: Colors.amber, size: 18),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Open your syllabus PDF → Select All (Ctrl+A) → Copy (Ctrl+C) → Paste below',
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Module dropdown
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
          const SizedBox(height: 16),

          // ── File upload button ────────────────────────────────────────
          GestureDetector(
            onTap: _isAnalyzing ? null : _uploadFile,
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.07),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: AppColors.primary.withValues(alpha: 0.5),
                  style: BorderStyle.solid,
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.upload_file,
                      color: AppColors.primary, size: 22),
                  const SizedBox(width: 10),
                  const Text(
                    'Upload PDF File',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w600,
                      fontSize: 15,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          const Row(
            children: [
              Expanded(child: Divider(color: Colors.white24)),
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 10),
                child: Text('or paste text below',
                    style: TextStyle(color: Colors.white38, fontSize: 12)),
              ),
              Expanded(child: Divider(color: Colors.white24)),
            ],
          ),
          const SizedBox(height: 12),

          // Paste area
          TextField(
            controller: _textController,
            onChanged: (_) => setState(() {}),
            maxLines: 10,
            style: const TextStyle(color: Colors.white, fontSize: 13),
            decoration: InputDecoration(
              hintText: 'Paste your syllabus / module outline text here...',
              hintStyle: const TextStyle(color: Colors.white38),
              filled: true,
              fillColor: Colors.white.withValues(alpha: 0.05),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.2)),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.2)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.5)),
              ),
              contentPadding: const EdgeInsets.all(12),
            ),
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              Text(
                '${_textController.text.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).length} words',
                style: const TextStyle(color: Colors.white38, fontSize: 11),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Analyze button
          GlassButton(
            onPressed: _isAnalyzing ? null : _analyze,
            child: Center(
              child: _isAnalyzing
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.auto_awesome, size: 18),
                        SizedBox(width: 8),
                        Text(
                          'Extract Dates with AI',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLoading() {
    return const Padding(
      padding: EdgeInsets.only(top: 32),
      child: Center(
        child: Column(
          children: [
            CircularProgressIndicator(color: AppColors.primary),
            SizedBox(height: 16),
            Text(
              'AI is reading your syllabus...',
              style: TextStyle(color: Colors.white70),
            ),
            SizedBox(height: 4),
            Text(
              'This usually takes 5–10 seconds',
              style: TextStyle(color: Colors.white38, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildError() {
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: GlassCard(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            const Icon(Icons.error_outline, color: Colors.redAccent, size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                _errorMessage ?? '',
                style: const TextStyle(color: Colors.redAccent, fontSize: 12),
              ),
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
              color: _typeColor(event.type).withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _typeColor(event.type).withValues(alpha: 0.4)),
            ),
            child: Icon(_typeIcon(event.type), color: _typeColor(event.type)),
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
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  dateStr,
                  style: const TextStyle(color: Colors.white70, fontSize: 13),
                ),
                if (event.time != null || event.venue != null)
                  Text(
                    [if (event.time != null) event.time!, if (event.venue != null) event.venue!].join(' · '),
                    style: const TextStyle(color: Colors.white54, fontSize: 11),
                  ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: _typeColor(event.type).withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              event.type.toUpperCase(),
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: _typeColor(event.type),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Color _typeColor(String type) {
    switch (type.toLowerCase()) {
      case 'test': return Colors.orangeAccent;
      case 'exam': return Colors.redAccent;
      case 'practical': return Colors.tealAccent;
      default: return Colors.blueAccent;
    }
  }

  IconData _typeIcon(String type) {
    switch (type.toLowerCase()) {
      case 'test': return Icons.quiz;
      case 'exam': return Icons.workspace_premium;
      case 'practical': return Icons.science;
      default: return Icons.assignment;
    }
  }
}
