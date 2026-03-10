import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'dart:io';
import 'dart:typed_data';
import '../models/student.dart';
import '../models/study_session.dart';
import '../screens/session.dart';

class ExportService {
  // ─── CSV Export ──────────────────────────────────────────────────────

  static Future<void> exportStudySessionsAsCSV({
    required BuildContext context,
    required Student student,
    required List<StudySession> studySessions,
  }) async {
    try {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(
          child: CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
          ),
        ),
      );

      final csvContent = _generateStudySessionsCSV(student, studySessions);
      final directory = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final filename =
          'study_sessions_${student.studentNumber}_$timestamp.csv';
      final file = File('${directory.path}/$filename');
      await file.writeAsString(csvContent);

      Navigator.pop(context);

      await Share.shareXFiles(
        [XFile(file.path)],
        text: 'My Study Sessions - ${student.fullName}',
        subject: 'Study Sessions Export',
      );
    } catch (e) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error exporting study sessions: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  static String _generateStudySessionsCSV(
      Student student, List<StudySession> studySessions) {
    final buffer = StringBuffer();
    buffer.writeln(
        'Title,Module Code,Module Name,Day,Start Time,End Time,Venue,Session Type,Notes,Duration (minutes),Created Date');
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

  // ─── PDF Grid Timetable Export ───────────────────────────────────────

  /// Generate and share/print a wall-poster-style timetable grid PDF.
  static Future<void> exportTimetableAsPDF({
    required BuildContext context,
    required Student student,
    required Map<String, Map<String, List<Session>>> timetableData,
  }) async {
    try {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(
          child: CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
          ),
        ),
      );

      final pdfBytes = await _buildTimetablePDF(student, timetableData);

      Navigator.pop(context);

      // Use the printing package to show print/share dialog
      await Printing.sharePdf(
        bytes: pdfBytes,
        filename: 'timetable_${student.studentNumber}.pdf',
      );
    } catch (e) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error exporting timetable: $e'),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  // ─── PDF Grid Study Timetable Export ────────────────────────────────

  /// Generate and share/print a wall-poster-style study plan grid PDF.
  static Future<void> exportStudySessionsAsPDF({
    required BuildContext context,
    required Student student,
    required List<StudySession> studySessions,
  }) async {
    try {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(
          child: CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
          ),
        ),
      );

      // Convert flat `List<StudySession>` into day -> time -> sessions structure
      final Map<String, Map<String, List<StudySession>>> groupedData = {};

      for (final session in studySessions) {
        final day = session.dayOfWeek.isNotEmpty ? session.dayOfWeek : 'General';
        String startTime = session.startTime.isNotEmpty ? session.startTime : '00:00';
        if (startTime.length > 5) startTime = startTime.substring(0, 5);

        if (!groupedData.containsKey(day)) {
          groupedData[day] = {};
        }
        if (!groupedData[day]!.containsKey(startTime)) {
          groupedData[day]![startTime] = [];
        }
        groupedData[day]![startTime]!.add(session);
      }

      final pdfBytes = await _buildStudySessionsPDF(student, groupedData);

      if (context.mounted) Navigator.pop(context);

      await Printing.sharePdf(
        bytes: pdfBytes,
        filename: 'study_plan_${student.studentNumber}.pdf',
      );
    } catch (e) {
      if (context.mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error exporting study plan: $e'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  /// Build the Study Sessions PDF document bytes.
  static Future<Uint8List> _buildStudySessionsPDF(
    Student student,
    Map<String, Map<String, List<StudySession>>> timetableData,
  ) async {
    final pdf = pw.Document();

    final allTimeSlots = <String>{};
    for (final dayData in timetableData.values) {
      allTimeSlots.addAll(dayData.keys);
    }
    final sortedSlots = allTimeSlots.toList()
      ..sort((a, b) => _timeToMinutes(a).compareTo(_timeToMinutes(b)));

    const dayOrder = [
      'Monday',
      'Tuesday',
      'Wednesday',
      'Thursday',
      'Friday',
      'Saturday',
      'Sunday'
    ];
    final activeDays = dayOrder
        .where((d) =>
            timetableData.containsKey(d) && timetableData[d]!.isNotEmpty)
        .toList();

    if (activeDays.isEmpty || sortedSlots.isEmpty) {
      pdf.addPage(pw.Page(
        pageFormat: PdfPageFormat.a4.landscape,
        build: (context) => pw.Center(
          child: pw.Text(
            'No study sessions available.',
            style: pw.TextStyle(fontSize: 20),
          ),
        ),
      ));
      return pdf.save();
    }

    final slotRows = <_TimeSlotRow>[];
    for (final slot in sortedSlots) {
      String endTime = _addMinutes(slot, 60);
      for (final day in activeDays) {
        final sessions = timetableData[day]?[slot] ?? [];
        for (final s in sessions) {
          final sEnd = s.endTime.isNotEmpty ? s.endTime : endTime;
          if (_timeToMinutes(sEnd) > _timeToMinutes(endTime)) {
            endTime = sEnd;
          }
        }
      }
      slotRows.add(_TimeSlotRow(
        startTime: _formatTimePretty(slot),
        endTime: _formatTimePretty(endTime),
      ));
    }

    final dayColors = <String, PdfColor>{
      'Monday': const PdfColor.fromInt(0xFFE74C3C),
      'Tuesday': const PdfColor.fromInt(0xFFE67E22),
      'Wednesday': const PdfColor.fromInt(0xFFF39C12),
      'Thursday': const PdfColor.fromInt(0xFF27AE60),
      'Friday': const PdfColor.fromInt(0xFF2E5BBA),
      'Saturday': const PdfColor.fromInt(0xFF8E44AD),
      'Sunday': const PdfColor.fromInt(0xFF95A5A6),
    };

    PdfColor cellColor(String type) {
      switch (type.toLowerCase()) {
        case 'study':
          return const PdfColor.fromInt(0xFFD6E4FF);
        case 'revision':
          return const PdfColor.fromInt(0xFFFFE8CC);
        case 'exam_prep':
        case 'exam':
          return const PdfColor.fromInt(0xFFFFD6D6);
        case 'assignment':
          return const PdfColor.fromInt(0xFFD4EDDA);
        default:
          return const PdfColor.fromInt(0xFFE8E8FF);
      }
    }

    final dayColumnCount = activeDays.length;
    final timeColumnWidth = 65.0;

    pdf.addPage(
      pw.Page(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.all(24),
        build: (context) {
          return pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Container(
                width: double.infinity,
                padding: const pw.EdgeInsets.symmetric(
                    vertical: 12, horizontal: 16),
                decoration: pw.BoxDecoration(
                  color: const PdfColor.fromInt(0xFF8E44AD), // Study Purple
                  borderRadius: pw.BorderRadius.circular(8),
                ),
                child: pw.Row(
                  mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                  children: [
                    pw.Column(
                      crossAxisAlignment: pw.CrossAxisAlignment.start,
                      children: [
                        pw.Text(
                          'Smart Study Plan',
                          style: pw.TextStyle(
                            fontSize: 20,
                            fontWeight: pw.FontWeight.bold,
                            color: PdfColors.white,
                          ),
                        ),
                        pw.SizedBox(height: 2),
                        pw.Text(
                          '${student.fullName}  •  ${student.studentNumber}',
                          style: const pw.TextStyle(
                            fontSize: 11,
                            color: PdfColors.white,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              pw.SizedBox(height: 12),

              pw.Expanded(
                child: pw.Table(
                  border: pw.TableBorder.all(
                    color: const PdfColor.fromInt(0xFFCCCCCC),
                    width: 0.5,
                  ),
                  columnWidths: {
                    0: pw.FixedColumnWidth(timeColumnWidth),
                    for (int i = 0; i < dayColumnCount; i++)
                      i + 1: const pw.FlexColumnWidth(1),
                  },
                  children: [
                    pw.TableRow(
                      decoration: const pw.BoxDecoration(
                        color: PdfColor.fromInt(0xFFF0F0F0),
                      ),
                      children: [
                        pw.Container(
                          height: 32,
                          alignment: pw.Alignment.center,
                          child: pw.Text(
                            'Time',
                            style: pw.TextStyle(
                              fontSize: 9,
                              fontWeight: pw.FontWeight.bold,
                            ),
                          ),
                        ),
                        ...activeDays.map((day) {
                          return pw.Container(
                            height: 32,
                            alignment: pw.Alignment.center,
                            decoration: pw.BoxDecoration(
                              color: dayColors[day] ?? PdfColors.purple,
                            ),
                            child: pw.Text(
                              day,
                              style: pw.TextStyle(
                                fontSize: 10,
                                fontWeight: pw.FontWeight.bold,
                                color: PdfColors.white,
                              ),
                            ),
                          );
                        }),
                      ],
                    ),

                    ...List.generate(sortedSlots.length, (rowIndex) {
                      final slot = sortedSlots[rowIndex];
                      final slotRow = slotRows[rowIndex];
                      final isEvenRow = rowIndex % 2 == 0;

                      return pw.TableRow(
                        decoration: pw.BoxDecoration(
                          color: isEvenRow
                              ? PdfColors.white
                              : const PdfColor.fromInt(0xFFFAFAFA),
                        ),
                        children: [
                          pw.Container(
                            padding: const pw.EdgeInsets.symmetric(
                                vertical: 6, horizontal: 4),
                            alignment: pw.Alignment.center,
                            child: pw.Column(
                              mainAxisAlignment:
                                  pw.MainAxisAlignment.center,
                              children: [
                                pw.Text(
                                  slotRow.startTime,
                                  style: pw.TextStyle(
                                    fontSize: 9,
                                    fontWeight: pw.FontWeight.bold,
                                  ),
                                ),
                                pw.Text(
                                  slotRow.endTime,
                                  style: const pw.TextStyle(
                                    fontSize: 7,
                                    color: PdfColor.fromInt(0xFF888888),
                                  ),
                                ),
                              ],
                            ),
                          ),

                          ...activeDays.map((day) {
                            final sessions =
                                timetableData[day]?[slot] ?? [];

                            if (sessions.isEmpty) {
                              return pw.Container(
                                padding: const pw.EdgeInsets.all(4),
                              );
                            }

                            return pw.Container(
                              padding: const pw.EdgeInsets.all(3),
                              child: pw.Column(
                                crossAxisAlignment:
                                    pw.CrossAxisAlignment.stretch,
                                children: sessions.map((session) {
                                  return pw.Container(
                                    margin: sessions.length > 1
                                        ? const pw.EdgeInsets.only(
                                            bottom: 2)
                                        : pw.EdgeInsets.zero,
                                    padding: const pw.EdgeInsets.symmetric(
                                        vertical: 4, horizontal: 5),
                                    decoration: pw.BoxDecoration(
                                      color:
                                          cellColor(session.sessionType),
                                      borderRadius:
                                          pw.BorderRadius.circular(4),
                                      border: pw.Border.all(
                                        color: const PdfColor.fromInt(
                                            0xFFDDDDDD),
                                        width: 0.5,
                                      ),
                                    ),
                                    child: pw.Column(
                                      crossAxisAlignment:
                                          pw.CrossAxisAlignment.start,
                                      children: [
                                        pw.Text(
                                          session.title.length > 20
                                              ? session.title
                                                  .substring(0, 20)
                                              : session.title,
                                          style: pw.TextStyle(
                                            fontSize: 7,
                                            fontWeight:
                                                pw.FontWeight.bold,
                                          ),
                                          maxLines: 1,
                                        ),
                                        pw.Text(
                                          session.moduleName.length > 28
                                              ? '${session.moduleName.substring(0, 25)}...'
                                              : session.moduleName,
                                          style: const pw.TextStyle(
                                            fontSize: 6,
                                          ),
                                          maxLines: 2,
                                        ),
                                        pw.SizedBox(height: 1),
                                        if (session.venue != null &&
                                            session.venue!.isNotEmpty)
                                          pw.Text(
                                            session.venue!,
                                            style: pw.TextStyle(
                                              fontSize: 6,
                                              fontWeight:
                                                  pw.FontWeight.bold,
                                              color:
                                                  const PdfColor.fromInt(
                                                      0xFF555555),
                                            ),
                                            maxLines: 1,
                                          ),
                                        if (session.sessionType.isNotEmpty)
                                          pw.Text(
                                            session.sessionType
                                                .toUpperCase()
                                                .replaceAll('_', ' '),
                                            style: const pw.TextStyle(
                                              fontSize: 5,
                                              color: PdfColor.fromInt(
                                                  0xFF777777),
                                            ),
                                          ),
                                      ],
                                    ),
                                  );
                                }).toList(),
                              ),
                            );
                          }),
                        ],
                      );
                    }),
                  ],
                ),
              ),

              pw.SizedBox(height: 8),

              pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  pw.Text(
                    'Generated by Smart Timetable App',
                    style: const pw.TextStyle(
                      fontSize: 7,
                      color: PdfColor.fromInt(0xFF999999),
                    ),
                  ),
                  pw.Text(
                    'Printed: ${DateTime.now().day}/${DateTime.now().month}/${DateTime.now().year}',
                    style: const pw.TextStyle(
                      fontSize: 7,
                      color: PdfColor.fromInt(0xFF999999),
                    ),
                  ),
                ],
              ),
            ],
          );
        },
      ),
    );

    return pdf.save();
  }

  /// Build the Class Timetable PDF document bytes.
  static Future<Uint8List> _buildTimetablePDF(
    Student student,
    Map<String, Map<String, List<Session>>> timetableData,
  ) async {
    final pdf = pw.Document();

    // ── Collect all unique time slots across ALL days ──
    final allTimeSlots = <String>{};
    for (final dayData in timetableData.values) {
      allTimeSlots.addAll(dayData.keys);
    }
    // Sort chronologically
    final sortedSlots = allTimeSlots.toList()
      ..sort((a, b) => _timeToMinutes(a).compareTo(_timeToMinutes(b)));

    // Days in order (only include days that have data)
    const dayOrder = [
      'Monday',
      'Tuesday',
      'Wednesday',
      'Thursday',
      'Friday',
      'Saturday',
      'Sunday'
    ];
    final activeDays = dayOrder
        .where((d) =>
            timetableData.containsKey(d) && timetableData[d]!.isNotEmpty)
        .toList();

    if (activeDays.isEmpty || sortedSlots.isEmpty) {
      // If no data, create a simple message page
      pdf.addPage(pw.Page(
        pageFormat: PdfPageFormat.a4.landscape,
        build: (context) => pw.Center(
          child: pw.Text(
            'No timetable data available.',
            style: pw.TextStyle(fontSize: 20),
          ),
        ),
      ));
      return pdf.save();
    }

    // ── Time-slot rows: merge the actual start/end from sessions ──
    final slotRows = <_TimeSlotRow>[];
    for (final slot in sortedSlots) {
      // Find the latest end time for this slot across all days
      String endTime = _addMinutes(slot, 60); // default 1h
      for (final day in activeDays) {
        final sessions = timetableData[day]?[slot] ?? [];
        for (final s in sessions) {
          final sEnd = s.endTime ?? endTime;
          if (_timeToMinutes(sEnd) > _timeToMinutes(endTime)) {
            endTime = sEnd;
          }
        }
      }
      slotRows.add(_TimeSlotRow(
        startTime: _formatTimePretty(slot),
        endTime: _formatTimePretty(endTime),
      ));
    }

    // ── Day header colors (PDF-safe) ──
    final dayColors = <String, PdfColor>{
      'Monday': const PdfColor.fromInt(0xFFE74C3C),
      'Tuesday': const PdfColor.fromInt(0xFFE67E22),
      'Wednesday': const PdfColor.fromInt(0xFFF39C12),
      'Thursday': const PdfColor.fromInt(0xFF27AE60),
      'Friday': const PdfColor.fromInt(0xFF2E5BBA),
      'Saturday': const PdfColor.fromInt(0xFF8E44AD),
      'Sunday': const PdfColor.fromInt(0xFF95A5A6),
    };

    // Module type → cell colour
    PdfColor cellColor(String? type) {
      switch ((type ?? '').toLowerCase()) {
        case 'lecture':
          return const PdfColor.fromInt(0xFFD6E4FF); // light blue
        case 'tutorial':
          return const PdfColor.fromInt(0xFFFFE8CC); // light orange
        case 'practical':
        case 'lab':
        case 'laboratory':
          return const PdfColor.fromInt(0xFFD4EDDA); // light green
        case 'seminar':
          return const PdfColor.fromInt(0xFFFFD6D6); // light red
        case 'workshop':
          return const PdfColor.fromInt(0xFFFFF3CD); // light yellow
        default:
          return const PdfColor.fromInt(0xFFE8E8FF); // light purple
      }
    }

    // ── Build the table ──
    // Column widths: time column + one per active day
    final dayColumnCount = activeDays.length;
    final timeColumnWidth = 65.0;

    pdf.addPage(
      pw.Page(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.all(24),
        build: (context) {
          return pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              // ── Title row ──
              pw.Container(
                width: double.infinity,
                padding: const pw.EdgeInsets.symmetric(
                    vertical: 12, horizontal: 16),
                decoration: pw.BoxDecoration(
                  color: const PdfColor.fromInt(0xFF2E5BBA),
                  borderRadius: pw.BorderRadius.circular(8),
                ),
                child: pw.Row(
                  mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                  children: [
                    pw.Column(
                      crossAxisAlignment: pw.CrossAxisAlignment.start,
                      children: [
                        pw.Text(
                          'Smart Timetable',
                          style: pw.TextStyle(
                            fontSize: 20,
                            fontWeight: pw.FontWeight.bold,
                            color: PdfColors.white,
                          ),
                        ),
                        pw.SizedBox(height: 2),
                        pw.Text(
                          '${student.fullName}  •  ${student.studentNumber}',
                          style: const pw.TextStyle(
                            fontSize: 11,
                            color: PdfColors.white,
                          ),
                        ),
                      ],
                    ),
                    pw.Column(
                      crossAxisAlignment: pw.CrossAxisAlignment.end,
                      children: [
                        pw.Text(
                          'University of Mpumalanga',
                          style: pw.TextStyle(
                            fontSize: 10,
                            fontWeight: pw.FontWeight.bold,
                            color: PdfColors.white,
                          ),
                        ),
                        pw.Text(
                          'Semester 1 • 2026',
                          style: const pw.TextStyle(
                            fontSize: 9,
                            color: PdfColors.white,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              pw.SizedBox(height: 12),

              // ── Grid table ──
              pw.Expanded(
                child: pw.Table(
                  border: pw.TableBorder.all(
                    color: const PdfColor.fromInt(0xFFCCCCCC),
                    width: 0.5,
                  ),
                  columnWidths: {
                    0: pw.FixedColumnWidth(timeColumnWidth),
                    for (int i = 0; i < dayColumnCount; i++)
                      i + 1: const pw.FlexColumnWidth(1),
                  },
                  children: [
                    // ── Header row ──
                    pw.TableRow(
                      decoration: const pw.BoxDecoration(
                        color: PdfColor.fromInt(0xFFF0F0F0),
                      ),
                      children: [
                        // Time header
                        pw.Container(
                          height: 32,
                          alignment: pw.Alignment.center,
                          child: pw.Text(
                            'Time',
                            style: pw.TextStyle(
                              fontSize: 9,
                              fontWeight: pw.FontWeight.bold,
                            ),
                          ),
                        ),
                        // Day headers
                        ...activeDays.map((day) {
                          return pw.Container(
                            height: 32,
                            alignment: pw.Alignment.center,
                            decoration: pw.BoxDecoration(
                              color: dayColors[day] ?? PdfColors.blue,
                            ),
                            child: pw.Text(
                              day,
                              style: pw.TextStyle(
                                fontSize: 10,
                                fontWeight: pw.FontWeight.bold,
                                color: PdfColors.white,
                              ),
                            ),
                          );
                        }),
                      ],
                    ),

                    // ── Data rows ──
                    ...List.generate(sortedSlots.length, (rowIndex) {
                      final slot = sortedSlots[rowIndex];
                      final slotRow = slotRows[rowIndex];
                      final isEvenRow = rowIndex % 2 == 0;

                      return pw.TableRow(
                        decoration: pw.BoxDecoration(
                          color: isEvenRow
                              ? PdfColors.white
                              : const PdfColor.fromInt(0xFFFAFAFA),
                        ),
                        children: [
                          // Time cell
                          pw.Container(
                            padding: const pw.EdgeInsets.symmetric(
                                vertical: 6, horizontal: 4),
                            alignment: pw.Alignment.center,
                            child: pw.Column(
                              mainAxisAlignment:
                                  pw.MainAxisAlignment.center,
                              children: [
                                pw.Text(
                                  slotRow.startTime,
                                  style: pw.TextStyle(
                                    fontSize: 9,
                                    fontWeight: pw.FontWeight.bold,
                                  ),
                                ),
                                pw.Text(
                                  slotRow.endTime,
                                  style: const pw.TextStyle(
                                    fontSize: 7,
                                    color: PdfColor.fromInt(0xFF888888),
                                  ),
                                ),
                              ],
                            ),
                          ),

                          // Day cells
                          ...activeDays.map((day) {
                            final sessions =
                                timetableData[day]?[slot] ?? [];

                            if (sessions.isEmpty) {
                              return pw.Container(
                                padding: const pw.EdgeInsets.all(4),
                              );
                            }

                            // Build cell content for each session in this slot
                            return pw.Container(
                              padding: const pw.EdgeInsets.all(3),
                              child: pw.Column(
                                crossAxisAlignment:
                                    pw.CrossAxisAlignment.stretch,
                                children: sessions.map((session) {
                                  return pw.Container(
                                    margin: sessions.length > 1
                                        ? const pw.EdgeInsets.only(
                                            bottom: 2)
                                        : pw.EdgeInsets.zero,
                                    padding: const pw.EdgeInsets.symmetric(
                                        vertical: 4, horizontal: 5),
                                    decoration: pw.BoxDecoration(
                                      color:
                                          cellColor(session.sessionType),
                                      borderRadius:
                                          pw.BorderRadius.circular(4),
                                      border: pw.Border.all(
                                        color: const PdfColor.fromInt(
                                            0xFFDDDDDD),
                                        width: 0.5,
                                      ),
                                    ),
                                    child: pw.Column(
                                      crossAxisAlignment:
                                          pw.CrossAxisAlignment.start,
                                      children: [
                                        pw.Text(
                                          session.moduleCode.length > 20
                                              ? session.moduleCode
                                                  .substring(0, 20)
                                              : session.moduleCode,
                                          style: pw.TextStyle(
                                            fontSize: 7,
                                            fontWeight:
                                                pw.FontWeight.bold,
                                          ),
                                          maxLines: 1,
                                        ),
                                        pw.Text(
                                          session.moduleName.length > 28
                                              ? '${session.moduleName.substring(0, 25)}...'
                                              : session.moduleName,
                                          style: const pw.TextStyle(
                                            fontSize: 6,
                                          ),
                                          maxLines: 2,
                                        ),
                                        pw.SizedBox(height: 1),
                                        pw.Text(
                                          session.venueName,
                                          style: pw.TextStyle(
                                            fontSize: 6,
                                            fontWeight:
                                                pw.FontWeight.bold,
                                            color:
                                                const PdfColor.fromInt(
                                                    0xFF555555),
                                          ),
                                          maxLines: 1,
                                        ),
                                        if (session.sessionType != null &&
                                            session
                                                .sessionType!.isNotEmpty)
                                          pw.Text(
                                            session.sessionType!
                                                .toUpperCase(),
                                            style: const pw.TextStyle(
                                              fontSize: 5,
                                              color: PdfColor.fromInt(
                                                  0xFF777777),
                                            ),
                                          ),
                                      ],
                                    ),
                                  );
                                }).toList(),
                              ),
                            );
                          }),
                        ],
                      );
                    }),
                  ],
                ),
              ),

              pw.SizedBox(height: 8),

              // ── Footer ──
              pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  pw.Text(
                    'Generated by Smart Timetable App',
                    style: const pw.TextStyle(
                      fontSize: 7,
                      color: PdfColor.fromInt(0xFF999999),
                    ),
                  ),
                  pw.Text(
                    'Printed: ${DateTime.now().day}/${DateTime.now().month}/${DateTime.now().year}',
                    style: const pw.TextStyle(
                      fontSize: 7,
                      color: PdfColor.fromInt(0xFF999999),
                    ),
                  ),
                ],
              ),
            ],
          );
        },
      ),
    );

    return pdf.save();
  }

  // ─── Helpers ────────────────────────────────────────────────────────

  static int _timeToMinutes(String time) {
    try {
      final parts = time.split(':');
      return int.parse(parts[0]) * 60 + int.parse(parts[1]);
    } catch (_) {
      return 0;
    }
  }

  static String _addMinutes(String time, int minutes) {
    try {
      final total = _timeToMinutes(time) + minutes;
      final h = (total ~/ 60) % 24;
      final m = total % 60;
      return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
    } catch (_) {
      return time;
    }
  }

  /// Format "08:00:00" or "08:00" → "08:00"
  static String _formatTimePretty(String time) {
    if (time.length > 5) return time.substring(0, 5);
    return time;
  }

  // ─── Export Options Dialog ──────────────────────────────────────────

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

                  // PDF Timetable Grid
                  _buildExportOption(
                    context,
                    'Print Timetable (PDF)',
                    'Beautiful grid to print and put on your wall',
                    Icons.picture_as_pdf,
                    const Color(0xFFE74C3C),
                    () {
                      Navigator.pop(context);
                      exportTimetableAsPDF(
                        context: context,
                        student: student,
                        timetableData: timetableData,
                      );
                    },
                  ),

                  const SizedBox(height: 12),

                  // PDF Study Sessions Grid
                  _buildExportOption(
                    context,
                    'Print Study Plan (PDF)',
                    'Beautiful grid of your study sessions to print',
                    Icons.dashboard_customize,
                    const Color(0xFF8E44AD), // Purple
                    () {
                      Navigator.pop(context);
                      exportStudySessionsAsPDF(
                        context: context,
                        student: student,
                        studySessions: studySessions,
                      );
                    },
                  ),

                  const SizedBox(height: 12),

                  // CSV Study Sessions
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

/// Helper class for time slot row data.
class _TimeSlotRow {
  final String startTime;
  final String endTime;
  const _TimeSlotRow({required this.startTime, required this.endTime});
}