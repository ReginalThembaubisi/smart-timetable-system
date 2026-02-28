import 'package:flutter/material.dart';
import '../models/student.dart';
import '../config/app_colors.dart';
import '../services/local_storage_service.dart';
import 'change_password_screen.dart';
import 'study_preferences_screen.dart';

class SettingsScreen extends StatefulWidget {
  final Student student;

  const SettingsScreen({
    Key? key,
    required this.student,
  }) : super(key: key);

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  String _studyPrefLabel = 'Flexible';

  @override
  void initState() {
    super.initState();
    _loadPreferenceLabel();
  }

  Future<void> _loadPreferenceLabel() async {
    final storage = LocalStorageService();
    await storage.initialize();
    final pref = storage.getStudyPreference();
    final labels = {
      'morning': 'Early Bird 🌅',
      'afternoon': 'Afternoon ☀️',
      'evening': 'Evening 🌆',
      'night': 'Night Owl 🌙',
      'balanced': 'Flexible ⚡',
    };
    if (mounted) setState(() => _studyPrefLabel = labels[pref] ?? 'Flexible ⚡');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Logout',
            onPressed: _showLogoutDialog,
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: const Icon(Icons.person),
              title: const Text('Profile'),
              subtitle: const Text('Manage your profile information'),
              trailing: const Icon(Icons.arrow_forward_ios),
              onTap: () {
                // Navigate to profile screen
              },
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.lock),
              title: const Text('Change Password'),
              subtitle: const Text('Update your account password'),
              trailing: const Icon(Icons.arrow_forward_ios),
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => ChangePasswordScreen(
                      student: widget.student,
                    ),
                  ),
                );
              },
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.schedule, color: Colors.purple),
              title: const Text('Study Preferences'),
              subtitle: Text('When you study best · $_studyPrefLabel'),
              trailing: const Icon(Icons.arrow_forward_ios),
              onTap: () async {
                final changed = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const StudyPreferencesScreen(),
                  ),
                );
                if (changed == true) _loadPreferenceLabel();
              },
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.notifications),
              title: const Text('Notifications'),
              subtitle: const Text('Manage notification preferences'),
              trailing: const Icon(Icons.arrow_forward_ios),
              onTap: () {
                // Navigate to notifications settings
              },
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.dark_mode),
              title: const Text('Theme'),
              subtitle: const Text('Change app appearance'),
              trailing: const Icon(Icons.arrow_forward_ios),
              onTap: () {
                // Navigate to theme settings
              },
            ),
          ),
          Card(
            child: ListTile(
              leading: const Icon(Icons.logout),
              title: const Text('Logout'),
              subtitle: const Text('Sign out of your account'),
              trailing: const Icon(Icons.arrow_forward_ios),
              onTap: () {
                _showLogoutDialog();
              },
            ),
          ),
        ],
      ),
    );
  }

  void _showLogoutDialog() {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Logout'),
          content: const Text('Are you sure you want to logout?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () async {
                Navigator.of(context).pop();
                // Clear only login data; keep study sessions and other user data
                try {
                  final storage = LocalStorageService();
                  await storage.initialize();
                  await storage.clearLoginOnly();
                } catch (_) {}
                // Navigate back to login screen
                if (mounted) {
                  Navigator.of(context).pushReplacementNamed('/');
                }
              },
              child: const Text('Logout'),
            ),
          ],
        );
      },
    );
  }
}