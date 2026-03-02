import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/app_colors.dart';
import '../services/local_storage_service.dart';
import '../services/study_session_service.dart';
import '../widgets/glass_card.dart';

/// Study preference identifiers
class StudyPreference {
  final String key;
  final String label;
  final String emoji;
  final String hours;
  final String description;
  final IconData icon;
  final List<Color> gradient;

  const StudyPreference({
    required this.key,
    required this.label,
    required this.emoji,
    required this.hours,
    required this.description,
    required this.icon,
    required this.gradient,
  });
}

const _preferences = [
  StudyPreference(
    key: 'morning',
    label: 'Early Bird',
    emoji: '🌅',
    hours: '06:00 – 11:00',
    description: 'Fresh mind, quiet house — best for deep work',
    icon: Icons.wb_sunny_outlined,
    gradient: [Color(0xFFFF9A56), Color(0xFFFFD86F)],
  ),
  StudyPreference(
    key: 'afternoon',
    label: 'Afternoon',
    emoji: '☀️',
    hours: '12:00 – 17:00',
    description: 'Post-lunch energy, perfect for practice and revision',
    icon: Icons.light_mode_outlined,
    gradient: [Color(0xFF4FC3F7), Color(0xFF0288D1)],
  ),
  StudyPreference(
    key: 'evening',
    label: 'Evening',
    emoji: '🌆',
    hours: '17:00 – 21:00',
    description: 'Wind-down pace great for reviewing the day\'s work',
    icon: Icons.nights_stay_outlined,
    gradient: [Color(0xFFAB47BC), Color(0xFF7B1FA2)],
  ),
  StudyPreference(
    key: 'night',
    label: 'Night Owl',
    emoji: '🌙',
    hours: '20:00 – 00:00',
    description: 'When the world sleeps, you focus best',
    icon: Icons.bedtime_outlined,
    gradient: [Color(0xFF1A237E), Color(0xFF4527A0)],
  ),
  StudyPreference(
    key: 'balanced',
    label: 'Flexible',
    emoji: '⚡',
    hours: 'Any time',
    description: 'Let the AI find the best gaps in your schedule',
    icon: Icons.auto_awesome_outlined,
    gradient: [Color(0xFF00897B), Color(0xFF26C6DA)],
  ),
];

const _allDays = [
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
  'Sunday',
];

class StudyPreferencesScreen extends StatefulWidget {
  const StudyPreferencesScreen({Key? key}) : super(key: key);

  @override
  State<StudyPreferencesScreen> createState() => _StudyPreferencesScreenState();
}

class _StudyPreferencesScreenState extends State<StudyPreferencesScreen>
    with TickerProviderStateMixin {
  late final LocalStorageService _storage;
  String _selectedPreference = 'balanced';
  late List<String> _selectedDays;
  int _selectedLeadMinutes = 15; // reminder lead time
  bool _isSaving = false;
  bool _isLoaded = false;

  late final AnimationController _fadeController;
  late final Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 500),
    );
    _fadeAnimation =
        CurvedAnimation(parent: _fadeController, curve: Curves.easeOut);
    _loadPreferences();
  }

  Future<void> _loadPreferences() async {
    _storage = LocalStorageService();
    await _storage.initialize();
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _selectedPreference = _storage.getStudyPreference();
      _selectedDays = _storage.getStudyDays();
      _selectedLeadMinutes = prefs.getInt('reminder_lead_minutes') ?? 15;
      _isLoaded = true;
    });
    _fadeController.forward();
  }

  @override
  void dispose() {
    _fadeController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _isSaving = true);
    await _storage.saveStudyPreference(_selectedPreference);
    await _storage.saveStudyDays(_selectedDays);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt('reminder_lead_minutes', _selectedLeadMinutes);
    
    // Reschedule all notifications with the new lead time
    final studentId = await _storage.getStudentId();
    if (studentId != null) {
      await StudySessionService.rescheduleAllNotifications(studentId);
    }
    
    setState(() => _isSaving = false);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.check_circle, color: Colors.white),
              const SizedBox(width: 10),
              Text('Preferences saved! AI will now suggest $_selectedPreference times.'),
            ],
          ),
          backgroundColor: Colors.green[700],
          duration: const Duration(seconds: 3),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
      Navigator.pop(context, true); // return true so callers can refresh
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: AppColors.backgroundGradient,
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              _buildHeader(),
              Expanded(
                child: _isLoaded
                    ? FadeTransition(
                        opacity: _fadeAnimation,
                        child: _buildContent(),
                      )
                    : const Center(
                        child: CircularProgressIndicator(color: Colors.white)),
              ),
              _buildSaveButton(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
                border:
                    Border.all(color: Colors.white.withValues(alpha: 0.2)),
              ),
              child:
                  const Icon(Icons.arrow_back, color: Colors.white, size: 20),
            ),
          ),
          const SizedBox(width: 16),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Study Preferences',
                  style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      color: Colors.white),
                ),
                Text(
                  'Tell us when you study best',
                  style: TextStyle(fontSize: 14, color: Colors.white60),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContent() {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Section: Time of day ──────────────────────────────────────
          _buildSectionLabel(
              '⏰', 'Preferred Study Time', 'When do you focus best?'),
          const SizedBox(height: 12),
          ..._preferences.map(_buildPreferenceCard),

          const SizedBox(height: 28),

          // ── Section: Study days ───────────────────────────────────────
          _buildSectionLabel('📅', 'Study Days', 'Which days do you study?'),
          const SizedBox(height: 12),
          _buildDaySelector(),

          const SizedBox(height: 28),

          // ── Section: Reminder timing ──────────────────────────────────
          _buildSectionLabel('🔔', 'Reminder Timing', 'How early should we remind you?'),
          const SizedBox(height: 12),
          _buildLeadTimePicker(),

          const SizedBox(height: 8),
        ],
      ),
    );
  }

  Widget _buildSectionLabel(String emoji, String title, String subtitle) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(emoji, style: const TextStyle(fontSize: 18)),
            const SizedBox(width: 8),
            Text(
              title,
              style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: Colors.white),
            ),
          ],
        ),
        const SizedBox(height: 2),
        Padding(
          padding: const EdgeInsets.only(left: 26),
          child: Text(
            subtitle,
            style: const TextStyle(fontSize: 13, color: Colors.white54),
          ),
        ),
      ],
    );
  }

  Widget _buildPreferenceCard(StudyPreference pref) {
    final isSelected = _selectedPreference == pref.key;
    return GestureDetector(
      onTap: () => setState(() => _selectedPreference = pref.key),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          gradient: isSelected
              ? LinearGradient(
                  colors: pref.gradient,
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
              : null,
          color: isSelected ? null : Colors.white.withValues(alpha: 0.07),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: isSelected
                ? Colors.white.withValues(alpha: 0.5)
                : Colors.white.withValues(alpha: 0.12),
            width: isSelected ? 2 : 1,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: pref.gradient.first.withValues(alpha: 0.45),
                    blurRadius: 20,
                    offset: const Offset(0, 6),
                  )
                ]
              : [],
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              // Emoji + Icon bubble
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: isSelected
                      ? Colors.white.withValues(alpha: 0.2)
                      : Colors.white.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(pref.emoji,
                        style: const TextStyle(fontSize: 20)),
                  ],
                ),
              ),
              const SizedBox(width: 16),
              // Text
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      pref.label,
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: isSelected ? Colors.white : Colors.white70,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      pref.hours,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: isSelected
                            ? Colors.white70
                            : Colors.white38,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      pref.description,
                      style: TextStyle(
                        fontSize: 12,
                        color:
                            isSelected ? Colors.white60 : Colors.white30,
                      ),
                    ),
                  ],
                ),
              ),
              // Check mark
              if (isSelected)
                const Icon(Icons.check_circle,
                    color: Colors.white, size: 22),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildLeadTimePicker() {
    const options = [5, 10, 15, 30, 60];
    const labels = {5: '5 min', 10: '10 min', 15: '15 min', 30: '30 min', 60: '1 hour'};

    return GlassCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: options.map((minutes) {
              final selected = _selectedLeadMinutes == minutes;
              return GestureDetector(
                onTap: () => setState(() => _selectedLeadMinutes = minutes),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    gradient: selected
                        ? const LinearGradient(
                            colors: AppColors.primaryGradient,
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          )
                        : null,
                    color: selected ? null : Colors.white.withValues(alpha: 0.06),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: selected
                          ? Colors.transparent
                          : Colors.white.withValues(alpha: 0.15),
                    ),
                    boxShadow: selected
                        ? [
                            BoxShadow(
                              color: const Color(0xFF6C63FF).withValues(alpha: 0.35),
                              blurRadius: 12,
                              offset: const Offset(0, 4),
                            ),
                          ]
                        : [],
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (selected) ...[
                        const Icon(Icons.notifications_active,
                            color: Colors.white, size: 14),
                        const SizedBox(width: 4),
                      ],
                      Text(
                        labels[minutes]!,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: selected ? Colors.white : Colors.white54,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
          const SizedBox(height: 12),
          Text(
            'You\'ll get a reminder ${labels[_selectedLeadMinutes]} before each study session',
            style: const TextStyle(color: Colors.white54, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Widget _buildDaySelector() {
    // Short day labels
    final short = {
      'Monday': 'Mon',
      'Tuesday': 'Tue',
      'Wednesday': 'Wed',
      'Thursday': 'Thu',
      'Friday': 'Fri',
      'Saturday': 'Sat',
      'Sunday': 'Sun',
    };

    return GlassCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: _allDays.map((day) {
              final selected = _selectedDays.contains(day);
              final isWeekend =
                  day == 'Saturday' || day == 'Sunday';
              return GestureDetector(
                onTap: () {
                  setState(() {
                    if (selected) {
                      if (_selectedDays.length > 1) {
                        _selectedDays.remove(day);
                      }
                    } else {
                      _selectedDays.add(day);
                    }
                  });
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 60,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  decoration: BoxDecoration(
                    gradient: selected
                        ? const LinearGradient(
                            colors: AppColors.primaryGradient,
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          )
                        : null,
                    color: selected
                        ? null
                        : Colors.white.withValues(alpha: 0.06),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: selected
                          ? Colors.transparent
                          : isWeekend
                              ? Colors.orange.withValues(alpha: 0.3)
                              : Colors.white.withValues(alpha: 0.15),
                    ),
                  ),
                  child: Column(
                    children: [
                      Text(
                        short[day]!,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: selected
                              ? Colors.white
                              : isWeekend
                                  ? Colors.orange[200]
                                  : Colors.white54,
                        ),
                      ),
                      if (selected) ...[
                        const SizedBox(height: 4),
                        Container(
                          width: 6,
                          height: 6,
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
          const SizedBox(height: 12),
          Text(
            '${_selectedDays.length} day${_selectedDays.length == 1 ? '' : 's'} selected',
            style: const TextStyle(color: Colors.white54, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Widget _buildSaveButton() {
    final pref = _preferences.firstWhere((p) => p.key == _selectedPreference);
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
      child: GestureDetector(
        onTap: _isSaving ? null : _save,
        child: Container(
          height: 56,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: pref.gradient,
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: pref.gradient.first.withValues(alpha: 0.4),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Center(
            child: _isSaving
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                        color: Colors.white, strokeWidth: 2),
                  )
                : Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(pref.emoji,
                          style: const TextStyle(fontSize: 18)),
                      const SizedBox(width: 10),
                      const Text(
                        'Save Preferences',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}
