import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:file_picker/file_picker.dart';
import 'package:google_generative_ai/google_generative_ai.dart';
import '../models/outline_event.dart';

// Use conditional imports to prevent crashing on iOS/Android devices where dart:js is missing.
import 'pdf_js_interop.dart' if (dart.library.io) 'pdf_stub_interop.dart' as pdf_js;

class OutlineService {
  static const String _modelName = 'gemini-1.5-flash';

  static const String _prompt = """
You are an academic assistant for a university student. Your goal is to find all important assessment dates from the attached syllabus/outline text.

Extract events such as:
- Tests (Test 1, Semester Test, Class Test, etc.)
- Assignments (Submission dates, Projects, Lab reports)
- Exams (Final Exams, Assessments)
- Practicals or Lab sessions with specific dates

For each event, find:
1. title: A descriptive name (e.g., "Assignment 1: Data Structures")
2. date: The date in YYYY-MM-DD format. If only day/month is given, assume the year is 2026.
3. type: One of these exact strings: "Test", "Assignment", "Exam", "Practical".
4. time: The start time (e.g., "09:30"), or null if not found.
5. venue: The location (e.g., "Building 5, Room 202"), or null if not found.

Output the results ONLY as a valid JSON list. No markdown, no explanation.
Example: [{"title": "Test 1", "date": "2026-03-20", "type": "Test", "time": "14:00", "venue": "Lab"}]
If no events are found, return an empty list: []
""";

  /// Extracts text locally from a PDF using pdf.js browser-interop to bypass WASM memory limits.
  static Future<String> _extractTextFromPdfBytes(Uint8List bytes) async {
    try {
      if (kIsWeb) {
        // Run the browser-native JS extraction logic explicitly to skip Flutter memory constraints
        return await pdf_js.extractPdfText(bytes);
      } else {
         throw Exception("This version of Outline Scanner only supports Flutter Web. Contact admin.");
      }
    } catch (e) {
      debugPrint('Browser JS PDF Extraction Error: $e');
      throw Exception('The browser could not read the PDF file text. Ensure it is a valid text-based syllabus PDF.');
    }
  }

  /// Extracts academic events directly from a PlatformFile using Gemini.
  static Future<List<OutlineEvent>> extractEventsFromDocument(
    PlatformFile file,
    String apiKey, 
    String moduleCode
  ) async {
    if (apiKey.isEmpty) {
      throw Exception('Gemini API key is not configured. Please contact the administrator.');
    }
    if (file.bytes == null) {
      throw Exception('File bytes are empty. Ensure file was picked with `withData: true`.');
    }

    try {
      final String extension = file.extension?.toLowerCase() ?? '';
      String extractedText = '';

      if (extension == 'pdf') {
        extractedText = await _extractTextFromPdfBytes(file.bytes!);
      } else {
        throw Exception('Only PDFs are supported for local parsing right now. Please save your file as a PDF and try again.');
      }

      if (extractedText.trim().isEmpty) {
        throw Exception('No readable text could be found inside this PDF. Is it just an image without text?');
      }

      final model = GenerativeModel(model: _modelName, apiKey: apiKey);

      final parts = [
        TextPart('$_prompt\n\nSyllabus Text to Analyze:\n---\n${extractedText.substring(0, extractedText.length.clamp(0, 50000))}\n---'),
      ];

      final response = await model.generateContent([Content.multi(parts)]);
      
      final responseText = response.text;
      if (responseText == null || responseText.isEmpty) {
        throw Exception('AI returned an empty response. The text might not contain identifiable dates.');
      }

      // Strip any markdown code fences
      String jsonString = responseText.trim();
      if (jsonString.startsWith('```')) {
        jsonString = jsonString.replaceAll(RegExp(r'^```json\n?|^```\n?|```$', multiLine: true), '');
      }
      jsonString = jsonString.trim();

      final List<dynamic> decoded = jsonDecode(jsonString);
      
      return decoded.map((item) {
        final map = Map<String, dynamic>.from(item);
        map['moduleCode'] = moduleCode;
        return OutlineEvent.fromJson(map);
      }).toList();

    } catch (e, stackTrace) {
      debugPrint('Error in OutlineService Direct Extract: $e\n$stackTrace');
      rethrow;
    }
  }
}
