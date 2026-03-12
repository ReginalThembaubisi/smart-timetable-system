import 'package:flutter/material.dart';
import '../config/app_colors.dart';

class EmptyState extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final String? actionText;
  final VoidCallback? onAction;
  final Color? iconColor;
  final Color? backgroundColor;

  const EmptyState({
    Key? key,
    required this.title,
    required this.subtitle,
    required this.icon,
    this.actionText,
    this.onAction,
    this.iconColor,
    this.backgroundColor,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(32),
      decoration: BoxDecoration(
        color: backgroundColor ?? Colors.grey[50],
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Icon
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: (iconColor ?? AppColors.primary).withOpacity(0.1),
              borderRadius: BorderRadius.circular(50),
            ),
            child: Icon(
              icon,
              size: 48,
              color: iconColor ?? AppColors.primary,
            ),
          ),
          
          const SizedBox(height: 24),
          
          // Title
          Text(
            title,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Colors.black87,
            ),
            textAlign: TextAlign.center,
          ),
          
          const SizedBox(height: 8),
          
          // Subtitle
          Text(
            subtitle,
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey[600],
              height: 1.4,
            ),
            textAlign: TextAlign.center,
          ),
          
          if (actionText != null && onAction != null) ...[
            const SizedBox(height: 24),
            
            // Action Button
            ElevatedButton.icon(
              onPressed: onAction,
              icon: const Icon(Icons.add, size: 18),
              label: Text(actionText!),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(25),
                ),
                elevation: 2,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class EmptyTimetableState extends StatelessWidget {
  final VoidCallback? onRefresh;
  
  const EmptyTimetableState({
    Key? key,
    this.onRefresh,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return EmptyState(
      title: 'No Classes Scheduled Today',
      subtitle: 'You have no classes today. Use this time to revise, create a study session, or check tomorrow\'s timetable.',
      icon: Icons.schedule,
      actionText: 'Refresh Data',
      onAction: onRefresh,
      iconColor: Colors.blue,
    );
  }
}

class EmptyStudySessionsState extends StatelessWidget {
  final VoidCallback? onCreateSession;
  
  const EmptyStudySessionsState({
    Key? key,
    this.onCreateSession,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return EmptyState(
      title: 'No Study Sessions',
      subtitle: 'You have not created study sessions yet. Tap "Create Session" to plan your first focused study block.',
      icon: Icons.school,
      actionText: 'Create Session',
      onAction: onCreateSession,
      iconColor: Colors.green,
    );
  }
}

class EmptyExamState extends StatelessWidget {
  final VoidCallback? onRefresh;
  
  const EmptyExamState({
    Key? key,
    this.onRefresh,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return EmptyState(
      title: 'No Exam Timetable Yet',
      subtitle: 'Your exam timetable is not available yet. Refresh later or check with your department for release dates.',
      icon: Icons.quiz,
      actionText: 'Refresh Data',
      onAction: onRefresh,
      iconColor: Colors.orange,
    );
  }
}

class EmptySearchState extends StatelessWidget {
  final String searchTerm;
  
  const EmptySearchState({
    Key? key,
    required this.searchTerm,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return EmptyState(
      title: 'No Results Found',
      subtitle: 'No results found for "$searchTerm". Try another keyword, fewer words, or check spelling.',
      icon: Icons.search_off,
      iconColor: Colors.grey,
    );
  }
}

class EmptyNotificationsState extends StatelessWidget {
  const EmptyNotificationsState({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return EmptyState(
      title: 'No Notifications',
      subtitle: 'You are all caught up. New reminders and updates will appear here automatically.',
      icon: Icons.notifications_none,
      iconColor: Colors.blue,
    );
  }
}

class ErrorState extends StatelessWidget {
  final String title;
  final String message;
  final VoidCallback? onRetry;
  final String? retryText;

  const ErrorState({
    Key? key,
    required this.title,
    required this.message,
    this.onRetry,
    this.retryText,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(32),
      decoration: BoxDecoration(
        color: Colors.red[50],
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.red[200]!),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Error Icon
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.red[100],
              borderRadius: BorderRadius.circular(50),
            ),
            child: Icon(
              Icons.error_outline,
              size: 48,
              color: Colors.red[600],
            ),
          ),
          
          const SizedBox(height: 24),
          
          // Title
          Text(
            title,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Colors.red[800],
            ),
            textAlign: TextAlign.center,
          ),
          
          const SizedBox(height: 8),
          
          // Message
          Text(
            message,
            style: TextStyle(
              fontSize: 14,
              color: Colors.red[600],
              height: 1.4,
            ),
            textAlign: TextAlign.center,
          ),
          
          if (onRetry != null) ...[
            const SizedBox(height: 24),
            
            // Retry Button
            ElevatedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh, size: 18),
              label: Text(retryText ?? 'Try Again'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red[600],
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(25),
                ),
                elevation: 2,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
