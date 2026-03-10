import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:google_generative_ai/google_generative_ai.dart';
import 'package:flutter/foundation.dart';
import '../models/outline_event.dart';
import '../config/app_config.dart';

class OutlineService {
  static const String _modelName = 'gemini-1.5-flash';

  /// Uploads the DOCX to the PHP backend for safe native server-side extraction
  static Future<String> _extractTextFromDocx(Uint8List bytes, String fileName) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}/api/extract_docx_text.php');
      var request = http.MultipartRequest('POST', uri);
      
      request.files.add(http.MultipartFile.fromBytes(
        'file',
        bytes,
        filename: fileName,
      ));

      final streamedResponse = await request.send();
      final response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true && data['data'] != null) {
          return data['data']['text'] ?? '';
        } else {
          throw Exception(data['message'] ?? 'Failed to extract text on server.');
        }
      } else {
        throw Exception('Server error during DOCX parsing: HTTP ${response.statusCode} - ${response.body}');
      }
    } catch (e) {
      debugPrint('DOCX Server Parsing Error: $e');
      if (e.toString().contains('minified:')) {
        throw Exception('Network or CORS error connecting to backend. Please ensure the server allows web requests.');
      }
      throw Exception('Could not extract DOCX text: $e');
    }
  }

  /// Uploads the PDF to the PHP backend for text extraction
  static Future<String> _extractTextFromPdf(Uint8List bytes, String fileName) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}/api/extract_pdf_text.php');
      var request = http.MultipartRequest('POST', uri);
      
      request.files.add(http.MultipartFile.fromBytes(
        'file',
        bytes,
        filename: fileName,
      ));

      final streamedResponse = await request.send();
      final response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true && data['data'] != null) {
          return data['data']['text'] ?? '';
        } else {
          throw Exception(data['message'] ?? 'Failed to extract text on server.');
        }
      } else {
        throw Exception('Server error during PDF parsing: HTTP ${response.statusCode} - ${response.body}');
      }
    } catch (e) {
      debugPrint('PDF Server Parsing Error: $e');
      if (e.toString().contains('minified:')) {
        throw Exception('Network or CORS error connecting to backend. Please ensure the server allows web requests.');
      }
      throw Exception('Could not extract PDF text. Does the file contain readable text? Error: $e');
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
        // Upload the PDF to the backend to get raw text, bypassing web limit/minified issues with Gemini direct docs
        final text = await _extractTextFromPdf(bytes, fileName);
        if (text.trim().isEmpty) {
          throw Exception('No readable text could be extracted from the PDF file.');
        }
        parts = [
          TextPart('$_prompt\n\nExtracted text from module outline PDF:\n---\n${text.substring(0, text.length.clamp(0, 15000))}\n---'),
        ];
      } else if (extension == 'docx') {
        // Upload the DOCX file to the PHP backend for extraction, then send as text prompt
        final text = await _extractTextFromDocx(bytes, fileName);
        if (text.trim().isEmpty) {
          throw Exception('No readable text could be extracted from the DOCX file.');
        }
        parts = [
          TextPart('$_prompt\n\nExtracted text from module outline DOCX:\n---\n${text.substring(0, text.length.clamp(0, 15000))}\n---'),
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

    } catch (e, stackTrace) {
      debugPrint('Error in OutlineService: $e\n$stackTrace');
      if (e.toString().contains('minified:')) {
        throw Exception('An unexpected web error occurred. If you uploaded a DOC, please convert it to PDF or DOCX first.');
      }
      rethrow;
    }
  }
}
