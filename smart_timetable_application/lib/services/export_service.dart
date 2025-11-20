import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';
import 'dart:io';
import '../models/student.dart';
import '../models/study_session.dart';
import '../screens/session.dart';

class ExportService {
  // Export study sessions as CSV
  static Future<void> exportStudySessionsAsCSV({
    required BuildContext context,
    required Student student,
    required List<StudySession> studySessions,
  }) async {
    try {
      // Show loading dialog
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(
          child: CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
          ),
        ),
      );

      // Create CSV content
      final csvContent = _generateStudySessionsCSV(student, studySessions);
      
      // Save CSV to temporary directory
      final directory = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final filename = 'study_sessions_${student.studentNumber}_$timestamp.csv';
      final file = File('${directory.path}/$filename');
      
      await file.writeAsString(csvContent);
      
      // Close loading dialog
      Navigator.pop(context);
      
      // Share the CSV
      await Share.shareXFiles(
        [XFile(file.path)],
        text: 'My Study Sessions - ${student.fullName}',
        subject: 'Study Sessions Export',
      );
      
    } catch (e) {
      // Close loading dialog
      Navigator.pop(context);
      
      // Show error message
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error exporting study sessions: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  // Generate study sessions CSV
  static String _generateStudySessionsCSV(Student student, List<StudySession> studySessions) {
    final buffer = StringBuffer();
    
    // CSV header
    buffer.writeln('Title,Module Code,Module Name,Day,Start Time,End Time,Venue,Session Type,Notes,Duration (minutes),Created Date');
    
    // Add each session
    for (final session in studySessions) {
      buffer.writeln([
        '"${session.title}"',
        '"${session.moduleCode}"',
        '"${session.moduleName}"',
        '"${session.dayOfWeek}"',
        '"${session.startTime}"',
        '"${session.endTime}"',
        '"${session.venue ?? ''}"',
        '"${session.sessionType}"',
        '"${session.notes ?? ''}"',
        '${session.duration ?? 0}',
        '"${session.createdAt.toIso8601String()}"',
      ].join(','));
    }
    
    return buffer.toString();
  }

  // Show export options dialog
  static void showExportOptions({
    required BuildContext context,
    required Student student,
    required Map<String, Map<String, List<Session>>> timetableData,
    required List<StudySession> studySessions,
  }) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.symmetric(vertical: 12),
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  const Text(
                    'Export Options',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF2E5BBA),
                    ),
                  ),
                  const SizedBox(height: 20),
                  
                  // Export Study Sessions as CSV
                  _buildExportOption(
                    context,
                    'Export Study Sessions (CSV)',
                    'Download your study sessions as a spreadsheet',
                    Icons.table_chart,
                    Colors.green,
                    () {
                      Navigator.pop(context);
                      exportStudySessionsAsCSV(
                        context: context,
                        student: student,
                        studySessions: studySessions,
                      );
                    },
                  ),
                  
                  const SizedBox(height: 12),
                  
                  // Export as PDF (coming soon)
                  _buildExportOption(
                    context,
                    'Export as PDF (Coming Soon)',
                    'Download your complete timetable as PDF',
                    Icons.picture_as_pdf,
                    Colors.orange,
                    () {
                      Navigator.pop(context);
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('PDF export coming soon!'),
                          backgroundColor: Colors.blue,
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  static Widget _buildExportOption(
    BuildContext context,
    String title,
    String subtitle,
    IconData icon,
    Color color,
    VoidCallback onTap,
  ) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withValues(alpha: 0.3)),
        ),
        child: Row(
          children: [
            Icon(icon, color: color, size: 32),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: color,
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: TextStyle(
                      color: color.withValues(alpha: 0.7),
                      fontSize: 14,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.chevron_right,
              color: color.withValues(alpha: 0.7),
            ),
          ],
        ),
      ),
    );
  }
}