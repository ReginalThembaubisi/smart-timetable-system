import 'dart:convert';
import 'package:archive/archive.dart';
import 'package:google_generative_ai/google_generative_ai.dart';
import 'package:flutter/foundation.dart';
import '../models/outline_event.dart';

class OutlineService {
  static const String _modelName = 'gemini-1.5-flash';

  /// Extracts text from a DOCX file (which is a ZIP with word/document.xml inside).
  static String _extractTextFromDocx(Uint8List bytes) {
    try {
      final archive = ZipDecoder().decodeBytes(bytes);
      final docXml = archive.files.firstWhere(
        (f) => f.name == 'word/document.xml',
        orElse: () => throw Exception('Invalid DOCX file — word/document.xml not found.'),
      );
      final xmlContent = utf8.decode(docXml.content as List<int>);
      // Strip XML tags, keep text content
      final text = xmlContent.replaceAll(RegExp(r'<[^>]+>'), ' ');
      // Collapse whitespace
      return text.replaceAll(RegExp(r'\s+'), ' ').trim();
    } catch (e) {
      throw Exception('Could not read DOCX: $e');
    }
  }

  static const String _prompt = """
You are an academic assistant for a university student. Your goal is to find all important assessment dates.

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

  /// Extracts academic events from a PDF or DOCX file using Gemini AI.
  static Future<List<OutlineEvent>> extractEventsFromDocument(
    Uint8List bytes,
    String fileName,
    String apiKey, 
    String moduleCode
  ) async {
    if (apiKey.isEmpty) {
      throw Exception('Gemini API key is not configured. Please contact the administrator.');
    }

    try {
      final String extension = fileName.split('.').last.toLowerCase();
      final model = GenerativeModel(model: _modelName, apiKey: apiKey);

      List<Part> parts;

      if (extension == 'pdf') {
        // PDFs work natively as inline DataPart with Gemini
        parts = [
          TextPart(_prompt + '\n\nPlease analyze the attached PDF document.'),
          DataPart('application/pdf', bytes),
        ];
      } else if (extension == 'docx') {
        // Extract text from the DOCX ZIP archive, then send as text prompt
        final text = _extractTextFromDocx(bytes);
        if (text.trim().isEmpty) {
          throw Exception('No text could be extracted from the DOCX file.');
        }
        parts = [
          TextPart('$_prompt\n\nExtracted text from module outline:\n---\n${text.substring(0, text.length.clamp(0, 15000))}\n---'),
        ];
      } else {
        throw Exception('Unsupported file format ($extension). Please upload a PDF or DOCX.');
      }

      final response = await model.generateContent([Content.multi(parts)]);
      
      final responseText = response.text;
      if (responseText == null || responseText.isEmpty) {
        throw Exception('AI returned an empty response. Please try again.');
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

    } catch (e) {
      debugPrint('Error in OutlineService: $e');
      rethrow;
    }
  }
}
