import 'package:flutter/material.dart';

/// Color-blind friendly color scheme for the Smart Timetable App
/// These colors are designed to be distinguishable for users with various types of color vision deficiencies
/// including protanopia, deuteranopia, and tritanopia
class AppColors {
  // Modern Primary Colors - Vibrant Purple-Blue Gradient
  static const Color primary = Color(0xFF667EEA);      // Modern Purple-Blue
  static const Color primaryLight = Color(0xFF8B5CF6); // Light Purple
  static const Color primaryDark = Color(0xFF5B21B6);  // Dark Purple
  
  // Secondary Colors - Modern Pink
  static const Color secondary = Color(0xFFEC4899);    // Vibrant Pink
  static const Color secondaryLight = Color(0xFFF472B6); // Light Pink
  static const Color secondaryDark = Color(0xFFDB2777);  // Dark Pink
  
  // Accent Colors - Modern Cyan
  static const Color accent = Color(0xFF06B6D4);       // Modern Cyan
  static const Color accentLight = Color(0xFF22D3EE);  // Light Cyan
  static const Color accentDark = Color(0xFF0891B2);   // Dark Cyan
  
  // Modern Dark Theme Colors
  static const Color background = Color(0xFF0F0F23);   // Deep Dark Blue
  static const Color surface = Color(0xFF1A1A2E);      // Dark Surface
  static const Color card = Color(0xFF16213E);         // Dark Card
  
  // Modern Text Colors - High contrast for dark theme
  static const Color textPrimary = Color(0xFFFFFFFF);  // Pure White
  static const Color textSecondary = Color(0xFFB8BCC8); // Light Gray
  static const Color textLight = Color(0xFF8E8E93);    // Medium Gray
  static const Color textOnPrimary = Color(0xFFFFFFFF); // White text on primary colors
  
  // Modern Status Colors - Vibrant and distinct
  static const Color success = Color(0xFF10D876);      // Modern Green
  static const Color warning = Color(0xFFFFB800);      // Modern Orange
  static const Color error = Color(0xFFFF3B30);        // Modern Red
  static const Color info = Color(0xFF007AFF);         // Modern Blue
  
  // Timetable-specific Colors - Each module type gets a distinct, accessible color
  static const Color lecture = Color(0xFF2E5BBA);      // Blue - Primary color
  static const Color tutorial = Color(0xFFE67E22);     // Orange - Secondary color
  static const Color practical = Color(0xFF27AE60);    // Green - Accent color
  static const Color lab = Color(0xFF8E44AD);          // Purple - Distinct color
  static const Color seminar = Color(0xFFE74C3C);      // Red - High contrast
  static const Color workshop = Color(0xFFF39C12);     // Light Orange - Secondary light
  static const Color exam = Color(0xFFC0392B);         // Dark Red - Important events
  static const Color assignment = Color(0xFF16A085);   // Teal - Distinct from others
  
  // Time Slot Colors - For different time periods
  static const Color morning = Color(0xFFF7DC6F);      // Light Yellow - Morning sessions
  static const Color afternoon = Color(0xFFF39C12);    // Orange - Afternoon sessions
  static const Color evening = Color(0xFF8E44AD);      // Purple - Evening sessions
  
  // Day Colors - For different days of the week
  static const Color monday = Color(0xFFE74C3C);       // Red
  static const Color tuesday = Color(0xFFE67E22);      // Orange
  static const Color wednesday = Color(0xFFF39C12);    // Light Orange
  static const Color thursday = Color(0xFF27AE60);     // Green
  static const Color friday = Color(0xFF2E5BBA);       // Blue
  static const Color saturday = Color(0xFF8E44AD);     // Purple
  static const Color sunday = Color(0xFF95A5A6);       // Gray
  
  // Border and Divider Colors
  static const Color border = Color(0xFFE5E7EB);       // Light Gray Border
  static const Color divider = Color(0xFFD1D5DB);      // Medium Gray Divider
  
  // Shadow and Overlay Colors
  static const Color shadow = Color(0x1A000000);       // Black with 10% opacity
  static const Color overlay = Color(0x80000000);      // Black with 50% opacity
  
  // Modern Gradient Colors - Stunning combinations
  static const List<Color> primaryGradient = [
    Color(0xFF667EEA),  // Purple-Blue
    Color(0xFF764BA2),  // Deep Purple
  ];
  
  static const List<Color> secondaryGradient = [
    Color(0xFF8B5CF6),  // Purple
    Color(0xFFEC4899),  // Pink
  ];
  
  static const List<Color> accentGradient = [
    Color(0xFFEC4899),  // Pink
    Color(0xFFFF6B6B),  // Coral
  ];
  
  static const List<Color> backgroundGradient = [
    Color(0xFF0F0F23),  // Deep Dark Blue
    Color(0xFF1A1A2E),  // Dark Surface
    Color(0xFF16213E),  // Dark Card
  ];
  
  // Accessibility Helper Methods
  /// Get a color with adjusted opacity for better contrast
  static Color withOpacity(Color color, double opacity) {
    return color.withValues(alpha: opacity);
  }
  
  /// Get a lighter version of a color for better contrast
  static Color lighten(Color color, double amount) {
    assert(amount >= 0 && amount <= 1);
    return Color.lerp(color, Colors.white, amount)!;
  }
  
  /// Get a darker version of a color for better contrast
  static Color darken(Color color, double amount) {
    assert(amount >= 0 && amount <= 1);
    return Color.lerp(color, Colors.black, amount)!;
  }
  
  /// Get a color that provides good contrast against the background
  static Color getContrastColor(Color backgroundColor) {
    // Calculate luminance and return appropriate contrast color
    final luminance = backgroundColor.computeLuminance();
    return luminance > 0.5 ? Colors.black : Colors.white;
  }
  
  /// Get a list of colors that are distinguishable for color-blind users
  static List<Color> getAccessibleColorPalette() {
    return [
      primary,      // Blue
      secondary,    // Orange
      accent,       // Green
      lab,          // Purple
      seminar,      // Red
      workshop,     // Light Orange
      exam,         // Dark Red
      assignment,   // Teal
    ];
  }
  
  /// Get a color for a specific module type that's accessible
  static Color getModuleColor(String moduleType) {
    switch (moduleType.toLowerCase()) {
      case 'lecture':
        return lecture;
      case 'tutorial':
        return tutorial;
      case 'practical':
        return practical;
      case 'lab':
      case 'laboratory':
        return lab;
      case 'seminar':
        return seminar;
      case 'workshop':
        return workshop;
      case 'exam':
      case 'examination':
        return exam;
      case 'assignment':
      case 'coursework':
        return assignment;
      default:
        return primary; // Default to primary color
    }
  }
  
  /// Get a color for a specific day that's accessible
  static Color getDayColor(String day) {
    switch (day.toLowerCase()) {
      case 'monday':
        return monday;
      case 'tuesday':
        return tuesday;
      case 'wednesday':
        return wednesday;
      case 'thursday':
        return thursday;
      case 'friday':
        return friday;
      case 'saturday':
        return saturday;
      case 'sunday':
        return sunday;
      default:
        return primary; // Default to primary color
    }
  }
}
