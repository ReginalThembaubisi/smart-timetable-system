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
      title: 'No Classes Today',
      subtitle: 'You have no classes scheduled for today. Enjoy your free time or catch up on your studies!',
      icon: Icons.schedule,
      actionText: 'Refresh',
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
      subtitle: 'You haven\'t created any study sessions yet. Start by creating your first session to track your study time.',
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
      title: 'No Exam Timetables',
      subtitle: 'No exam timetables are available yet. Check back later or contact your department for updates.',
      icon: Icons.quiz,
      actionText: 'Refresh',
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
      subtitle: 'No results found for "$searchTerm". Try adjusting your search terms or check your spelling.',
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
      subtitle: 'You\'re all caught up! No new notifications at the moment.',
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
