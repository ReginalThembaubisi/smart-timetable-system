import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:file_picker/file_picker.dart';
import 'package:google_generative_ai/google_generative_ai.dart';
import '../models/outline_event.dart';

class OutlineService {
  static const String _modelName = 'gemini-1.5-flash';

  static const String _prompt = """
You are an academic assistant for a university student. Your goal is to find all important assessment dates from the attached syllabus/outline.

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

  /// Extracts academic events directly from a PlatformFile using Gemini's native Document processing.
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
      final model = GenerativeModel(model: _modelName, apiKey: apiKey);

      // Determine MIME Type for Gemini
      String mimeType;
      if (extension == 'pdf') {
        mimeType = 'application/pdf';
      } else if (extension == 'docx') {
        mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
      } else if (extension == 'txt') {
        mimeType = 'text/plain';
      } else {
        throw Exception('Unsupported file format ($extension). Please upload a PDF, DOCX, or TXT file.');
      }

      // Build the raw data part for Gemini
      final documentPart = DataPart(mimeType, file.bytes!);
      
      final parts = [
        TextPart(_prompt),
        documentPart,
      ];

      final response = await model.generateContent([Content.multi(parts)]);
      
      final responseText = response.text;
      if (responseText == null || responseText.isEmpty) {
        throw Exception('AI returned an empty response. The document might not contain easily readable text.');
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
      
      // Provide clearer error messaging for WASM/Browser constraints
      String errorStr = e.toString().toLowerCase();
      if (errorStr.contains('minified:') || errorStr.contains('out of memory')) {
        throw Exception('File too large for your browser to process. Please try a smaller PDF or use the mobile app.');
      }
      rethrow;
    }
  }
}
