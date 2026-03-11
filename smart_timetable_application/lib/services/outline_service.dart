import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:google_generative_ai/google_generative_ai.dart';
import '../models/outline_event.dart';
import '../config/app_config.dart';

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

  /// Sends syllabus text to the PHP backend, which calls Gemini server-side.
  static Future<List<OutlineEvent>> extractEventsFromText(
    String text,
    String moduleCode,
  ) async {
    if (text.trim().isEmpty) {
      throw Exception('No text provided. Please paste your syllabus content.');
    }
    return _postToScanEndpoint(moduleCode, text: text);
  }

  /// Uploads a PDF file to the PHP backend, which extracts text and calls Gemini.
  static Future<List<OutlineEvent>> extractEventsFromFile(
    List<int> fileBytes,
    String fileName,
    String moduleCode,
  ) async {
    if (fileBytes.isEmpty) {
      throw Exception('The selected file is empty.');
    }

    final url = Uri.parse('${AppConfig.apiBaseUrl}/api/scan_outline.php');

    try {
      final request = http.MultipartRequest('POST', url)
        ..fields['module_code'] = moduleCode
        ..files.add(http.MultipartFile.fromBytes(
          'file',
          fileBytes,
          filename: fileName,
        ));

      final streamed = await request.send().timeout(const Duration(seconds: 60));
      final response = await http.Response.fromStream(streamed);
      return _parseResponse(response.body);
    } catch (e, stackTrace) {
      debugPrint('Error in OutlineService.extractEventsFromFile: $e\n$stackTrace');
      rethrow;
    }
  }

  static Future<List<OutlineEvent>> _postToScanEndpoint(
    String moduleCode, {
    String text = '',
  }) async {
    final url = Uri.parse('${AppConfig.apiBaseUrl}/api/scan_outline.php');
    try {
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'text': text, 'module_code': moduleCode}),
      ).timeout(const Duration(seconds: 45));
      return _parseResponse(response.body);
    } catch (e, stackTrace) {
      debugPrint('Error in OutlineService._postToScanEndpoint: $e\n$stackTrace');
      rethrow;
    }
  }

  static List<OutlineEvent> _parseResponse(String responseBody) {
    if (responseBody.trim().isEmpty) {
      throw Exception('Server returned an empty response. Please try again.');
    }
    Map<String, dynamic> body;
    try {
      body = jsonDecode(responseBody) as Map<String, dynamic>;
    } catch (_) {
      throw Exception('Server error. Please try again in a moment.');
    }
    if (body['success'] != true) {
      throw Exception(body['message'] ?? 'Server error during scan.');
    }
    final List<dynamic> events = (body['data']?['events'] as List?) ?? [];
    return events
        .whereType<Map>()
        .map((e) => OutlineEvent.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  /// Sends PDF bytes directly to Gemini for analysis — no JS interop, no text extraction.
  static Future<List<OutlineEvent>> extractEventsFromPdfBytes(
    Uint8List pdfBytes,
    String apiKey,
    String moduleCode,
  ) async {
    if (apiKey.isEmpty) {
      throw Exception('Gemini API key is not configured. Please contact the administrator.');
    }
    if (pdfBytes.isEmpty) {
      throw Exception('The selected file is empty.');
    }

    try {
      final model = GenerativeModel(model: _modelName, apiKey: apiKey);

      final parts = [
        TextPart('$_prompt\n\nModule code: $moduleCode'),
        DataPart('application/pdf', pdfBytes),
      ];

      final response = await model.generateContent([Content.multi(parts)]);

      final responseText = response.text;
      if (responseText == null || responseText.isEmpty) {
        throw Exception('AI returned an empty response. The document might not contain identifiable dates.');
      }

      // Strip markdown fences and find the JSON array
      String jsonString = responseText.trim();
      if (jsonString.startsWith('```')) {
        jsonString = jsonString.replaceAll(
          RegExp(r'^```json\n?|^```\n?|```$', multiLine: true),
          '',
        );
      }
      jsonString = jsonString.trim();

      // Fallback: extract first [...] block in case of surrounding text
      final start = jsonString.indexOf('[');
      final end = jsonString.lastIndexOf(']');
      if (start != -1 && end != -1 && end > start) {
        jsonString = jsonString.substring(start, end + 1);
      }

      return _parseGeminiResponse(responseText, moduleCode);
    } catch (e, stackTrace) {
      debugPrint('Error in OutlineService.extractEventsFromPdfBytes: $e\n$stackTrace');
      rethrow;
    }
  }

  static List<OutlineEvent> _parseGeminiResponse(String responseText, String moduleCode) {
    String jsonString = responseText.trim();
    if (jsonString.startsWith('```')) {
      jsonString = jsonString.replaceAll(
        RegExp(r'^```json\n?|^```\n?|```$', multiLine: true),
        '',
      );
    }
    jsonString = jsonString.trim();

    final start = jsonString.indexOf('[');
    final end = jsonString.lastIndexOf(']');
    if (start != -1 && end != -1 && end > start) {
      jsonString = jsonString.substring(start, end + 1);
    }

    final List<dynamic> decoded = jsonDecode(jsonString);

    return decoded
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item as Map))
        .where((map) => _isValidDate(map['date']?.toString()))
        .map((map) {
          map['moduleCode'] = moduleCode;
          map['type'] = _normaliseType(map['type']?.toString());
          return OutlineEvent.fromJson(map);
        })
        .toList();
  }

  static bool _isValidDate(String? value) {
    if (value == null || value.trim().isEmpty) return false;
    try {
      DateTime.parse(value.trim());
      return true;
    } catch (_) {
      return false;
    }
  }

  static String _normaliseType(String? raw) {
    final v = (raw ?? '').toLowerCase();
    if (v.contains('test')) return 'Test';
    if (v.contains('exam')) return 'Exam';
    if (v.contains('practical') || v.contains('lab')) return 'Practical';
    return 'Assignment';
  }
}
