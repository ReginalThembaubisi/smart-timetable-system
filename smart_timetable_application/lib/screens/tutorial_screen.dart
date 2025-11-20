import 'package:flutter/material.dart';
import '../widgets/glass_card.dart';
import '../config/app_colors.dart';

class TutorialScreen extends StatefulWidget {
  const TutorialScreen({super.key});

  @override
  State<TutorialScreen> createState() => _TutorialScreenState();
}

class _TutorialScreenState extends State<TutorialScreen> {
  final PageController _pageController = PageController();
  int _currentPage = 0;

  final List<TutorialPage> _pages = [
    TutorialPage(
      icon: Icons.home,
      title: "Welcome to Smart Timetable",
      description: "Your intelligent study companion that helps you manage your academic schedule and optimize your study time.",
      tips: [
        "View your class timetable and upcoming exams",
        "Get AI-powered study suggestions",
        "Track your study sessions with Pomodoro timer",
        "Create and manage custom study sessions"
      ],
    ),
    TutorialPage(
      icon: Icons.auto_awesome,
      title: "AI Study Suggestions",
      description: "Our smart AI analyzes your timetable and suggests optimal study times based on your preferences.",
      tips: [
        "AI finds free time slots in your schedule",
        "Suggests study times based on your preference (morning, afternoon, evening, night, or balanced)",
        "Click 'Add Weekly Plan' to automatically add 10 study sessions",
        "AI considers prep time before classes and rest time after"
      ],
    ),
    TutorialPage(
      icon: Icons.schedule,
      title: "Study Sessions",
      description: "Create, edit, and manage your study sessions with smart scheduling.",
      tips: [
        "Use 'Study Sessions' to view all your planned sessions",
        "Click '+' to create individual study sessions",
        "Edit existing sessions by tapping the edit button",
        "Sessions automatically avoid conflicts with your classes"
      ],
    ),
    TutorialPage(
      icon: Icons.timer,
      title: "Pomodoro Timer",
      description: "Stay focused with our built-in Pomodoro study timer.",
      tips: [
        "Click 'Start Focus Session' on any study session",
        "Choose focus duration (15, 25, 45, or 60 minutes)",
        "Take breaks between study sessions",
        "Track your study statistics and progress"
      ],
    ),
    TutorialPage(
      icon: Icons.settings,
      title: "Personalize Your Experience",
      description: "Customize the app to match your study preferences and habits.",
      tips: [
        "Go to Settings → AI Study Preferences",
        "Choose your preferred study time (Morning Person, Night Owl, etc.)",
        "AI will prioritize suggestions based on your preference",
        "Your study sessions persist even after logout"
      ],
    ),
    TutorialPage(
      icon: Icons.tips_and_updates,
      title: "Pro Tips",
      description: "Get the most out of Smart Timetable with these expert tips.",
      tips: [
        "Use 'Quick Study' to find and start your next session instantly",
        "Review the Weekly Study Plan for a complete overview",
        "Set up study sessions before exam periods",
        "Use the search bar to quickly find specific classes or venues"
      ],
    ),
  ];

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text(
          'How to Use Smart Timetable',
          style: TextStyle(
            color: Colors.white,
            fontSize: 20,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Column(
        children: [
          // Progress indicator
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
            child: Row(
              children: [
                Text(
                  '${_currentPage + 1} of ${_pages.length}',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: LinearProgressIndicator(
                    value: (_currentPage + 1) / _pages.length,
                    backgroundColor: Colors.white10,
                    valueColor: AlwaysStoppedAnimation<Color>(AppColors.primary),
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              ],
            ),
          ),
          
          // Tutorial content
          Expanded(
            child: PageView.builder(
              controller: _pageController,
              onPageChanged: (index) {
                setState(() {
                  _currentPage = index;
                });
              },
              itemCount: _pages.length,
              itemBuilder: (context, index) {
                return _buildTutorialPage(_pages[index]);
              },
            ),
          ),
          
          // Navigation buttons
          Container(
            padding: const EdgeInsets.all(20),
            child: Row(
              children: [
                if (_currentPage > 0)
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {
                        _pageController.previousPage(
                          duration: const Duration(milliseconds: 300),
                          curve: Curves.easeInOut,
                        );
                      },
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.white,
                        side: const BorderSide(color: Colors.white30),
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Previous'),
                    ),
                  ),
                if (_currentPage > 0) const SizedBox(width: 16),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      if (_currentPage < _pages.length - 1) {
                        _pageController.nextPage(
                          duration: const Duration(milliseconds: 300),
                          curve: Curves.easeInOut,
                        );
                      } else {
                        Navigator.pop(context);
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: Text(
                      _currentPage < _pages.length - 1 ? 'Next' : 'Get Started',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTutorialPage(TutorialPage page) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          // Icon
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.2),
              borderRadius: BorderRadius.circular(50),
              border: Border.all(
                color: AppColors.primary.withOpacity(0.3),
                width: 2,
              ),
            ),
            child: Icon(
              page.icon,
              size: 64,
              color: AppColors.primary,
            ),
          ),
          
          const SizedBox(height: 32),
          
          // Title
          Text(
            page.title,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 28,
              fontWeight: FontWeight.bold,
            ),
            textAlign: TextAlign.center,
          ),
          
          const SizedBox(height: 16),
          
          // Description
          Text(
            page.description,
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 16,
              height: 1.5,
            ),
            textAlign: TextAlign.center,
          ),
          
          const SizedBox(height: 32),
          
          // Tips
          GlassCard(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(
                      Icons.lightbulb,
                      color: Colors.amber,
                      size: 24,
                    ),
                    SizedBox(width: 12),
                    Text(
                      'Key Features',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                ...page.tips.map((tip) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        margin: const EdgeInsets.only(top: 6),
                        width: 6,
                        height: 6,
                        decoration: BoxDecoration(
                          color: AppColors.primary,
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          tip,
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 15,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ],
                  ),
                )),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class TutorialPage {
  final IconData icon;
  final String title;
  final String description;
  final List<String> tips;

  TutorialPage({
    required this.icon,
    required this.title,
    required this.description,
    required this.tips,
  });
}




