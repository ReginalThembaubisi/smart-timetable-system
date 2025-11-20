import 'package:flutter/material.dart';
import 'app_colors.dart';

/// Specialized color utilities for timetable elements
/// Ensures maximum accessibility for users with color vision deficiencies
class TimetableColors {
  /// Get a distinct color for each module type that's accessible
  static Color getModuleTypeColor(String moduleType) {
    switch (moduleType.toLowerCase()) {
      case 'lecture':
      case 'lec':
        return AppColors.lecture;
      case 'tutorial':
      case 'tut':
        return AppColors.tutorial;
      case 'practical':
      case 'prac':
        return AppColors.practical;
      case 'lab':
      case 'laboratory':
        return AppColors.lab;
      case 'seminar':
      case 'sem':
        return AppColors.seminar;
      case 'workshop':
      case 'work':
        return AppColors.workshop;
      case 'exam':
      case 'examination':
      case 'test':
        return AppColors.exam;
      case 'assignment':
      case 'coursework':
      case 'assess':
        return AppColors.assignment;
      default:
        return AppColors.primary;
    }
  }

  /// Get a color for different days of the week
  static Color getDayColor(String day) {
    switch (day.toLowerCase()) {
      case 'monday':
      case 'mon':
        return AppColors.monday;
      case 'tuesday':
      case 'tue':
        return AppColors.tuesday;
      case 'wednesday':
      case 'wed':
        return AppColors.wednesday;
      case 'thursday':
      case 'thu':
        return AppColors.thursday;
      case 'friday':
      case 'fri':
        return AppColors.friday;
      case 'saturday':
      case 'sat':
        return AppColors.saturday;
      case 'sunday':
      case 'sun':
        return AppColors.sunday;
      default:
        return AppColors.primary;
    }
  }
}
