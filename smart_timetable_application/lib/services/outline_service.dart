import 'dart:convert';
import 'dart:typed_data';
import 'package:google_generative_ai/google_generative_ai.dart';
import 'package:flutter/foundation.dart';
import '../models/outline_event.dart';

class OutlineService {
  static const String _modelName = 'gemini-1.5-flash'; // Better document support model

  /// Extracts academic events from a PDF or DOCX file using Gemini AI natively.
  static Future<List<OutlineEvent>> extractEventsFromDocument(
    Uint8List bytes,
    String fileName,
    String apiKey, 
    String moduleCode
  ) async {
    try {
      final String extension = fileName.split('.').last.toLowerCase();
      String mimeType;

      if (extension == 'pdf') {
        mimeType = 'application/pdf';
      } else if (extension == 'docx') {
        mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
      } else {
        throw Exception('Unsupported file format ($extension). Please upload a PDF or DOCX.');
      }

      final model = GenerativeModel(model: _modelName, apiKey: apiKey);
      
      final prompt = """
You are an academic assistant for a university student. I have attached a module outline document.
Your goal is to read the attached document and find all important dates related to the course schedule.

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

Output the results ONLY as a valid JSON list of objects. Do not include any markdown formatting (like ```json) or explanation text.
Example output format:
[{"title": "Test 1", "date": "2026-03-20", "type": "Test", "time": "14:00", "venue": "Major Lab"}]
""";

      final fileData = DataPart(mimeType, bytes);
      final content = [
        Content.multi([
          TextPart(prompt),
          fileData,
        ])
      ];

      final response = await model.generateContent(content);
      
      final responseText = response.text;
      if (responseText == null || responseText.isEmpty) {
        throw Exception('AI failed to generate a response from the document.');
      }

      // Clean up response in case AI included markdown code blocks
      String jsonString = responseText.trim();
      if (jsonString.startsWith('```')) {
        jsonString = jsonString.replaceAll(RegExp(r'^```json\n?|```$', multiLine: true), '');
      }

      final List<dynamic> decoded = jsonDecode(jsonString);
      
      return decoded.map((item) {
        // Add the module code to each event
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
