import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';

import 'config/app_theme.dart';
import 'models/lecturer.dart';
import 'screens/lecturer_dashboard_screen.dart';
import 'services/local_storage_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await dotenv.load(fileName: '.env');
  } catch (_) {
    // Optional env for local builds.
  }

  runApp(const LecturerTimetableApp());
}

class LecturerTimetableApp extends StatelessWidget {
  const LecturerTimetableApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Smart Timetable Lecturer',
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: ThemeMode.system,
      home: const LecturerEntryScreen(),
    );
  }
}

class LecturerEntryScreen extends StatefulWidget {
  const LecturerEntryScreen({super.key});

  @override
  State<LecturerEntryScreen> createState() => _LecturerEntryScreenState();
}

class _LecturerEntryScreenState extends State<LecturerEntryScreen> {
  bool _loading = true;
  Lecturer? _lecturer;

  @override
  void initState() {
    super.initState();
    _restoreLecturerSession();
  }

  Future<void> _restoreLecturerSession() async {
    final storage = LocalStorageService();
    await storage.initialize();
    final lecturer = storage.getLecturer();
    if (!mounted) return;
    setState(() {
      _lecturer = lecturer;
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    // Temporary preview mode: bypass login and open dashboard directly.
    final previewLecturer = _lecturer ??
        Lecturer(
          lecturerId: 1,
          lecturerName: 'Lecturer Preview',
          email: null,
          lecturerCode: 'PREVIEW',
          loginIdentifier: 'preview',
        );

    return LecturerDashboardScreen(lecturer: previewLecturer);
  }
}
