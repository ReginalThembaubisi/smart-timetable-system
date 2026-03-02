import '../screens/session.dart';
import '../models/study_session.dart';

class AISuggestionService {
  // Generate AI study suggestions based on timetable analysis
  static Map<String, dynamic> generateStudySuggestion(
    Map<String, Map<String, List<Session>>> timetableData,
    List<StudySession> existingStudySessions, {
    String studyPreference = 'balanced', // 'morning', 'afternoon', 'evening', 'night', 'balanced'
  }) {
    print('Debug AI: Starting suggestion generation...');
    
    // Get all unique modules from timetable
    final allModules = <String, Map<String, dynamic>>{};
    final now = DateTime.now();
    
    // Extract all modules and their next class times
    for (final dayName in timetableData.keys) {
      final dayIndex = _getDayIndex(dayName);
      final daysUntilClass = (dayIndex - now.weekday + 7) % 7;
      
      for (final timeSlot in timetableData[dayName]!.keys) {
        for (final session in timetableData[dayName]![timeSlot]!) {
          final moduleKey = session.moduleCode;
          if (!allModules.containsKey(moduleKey)) {
            allModules[moduleKey] = {
              'code': session.moduleCode,
              'name': session.moduleName,
              'nextClassDay': dayName,
              'nextClassTime': session.startTime,
              'daysUntil': daysUntilClass,
              'lecturer': session.lecturerName,
              'venue': session.venueName,
            };
          }
        }
      }
    }
    
    print('Debug AI: Found ${allModules.length} unique modules: ${allModules.keys.toList()}');
    
    // Generate intelligent suggestions
    return _generateIntelligentSuggestion(allModules, timetableData, existingStudySessions, studyPreference);
  }

  // Get day index (Monday = 1, Sunday = 7)
  static int _getDayIndex(String dayName) {
    switch (dayName.toLowerCase()) {
      case 'monday': return 1;
      case 'tuesday': return 2;
      case 'wednesday': return 3;
      case 'thursday': return 4;
      case 'friday': return 5;
      case 'saturday': return 6;
      case 'sunday': return 7;
      default: return 1;
    }
  }

  // Generate intelligent, varied suggestions
  static Map<String, dynamic> _generateIntelligentSuggestion(
    Map<String, Map<String, dynamic>> allModules,
    Map<String, Map<String, List<Session>>> timetableData,
    List<StudySession> existingStudySessions,
    String studyPreference,
  ) {
    final now = DateTime.now();
    
    // Create module recommendations for all modules
    final moduleRecommendations = <Map<String, dynamic>>[];
    
    for (final moduleData in allModules.values) {
      final moduleCode = moduleData['code'] as String;
      final moduleName = moduleData['name'] as String;
      final daysUntil = moduleData['daysUntil'] as int;
      
      // Generate specific study focus and methods for each module
      final recommendation = _generateModuleRecommendation(moduleCode, moduleName, daysUntil);
      moduleRecommendations.add(recommendation);
    }
    
    // Sort by urgency and pick primary focus
    moduleRecommendations.sort((a, b) => (a['daysUntil'] as int).compareTo(b['daysUntil'] as int));
    
    // Find free time slots from actual timetable based on study preference
    final freeSlots = _findFreeTimeSlots(timetableData, existingStudySessions, studyPreference);
    
    // Generate intelligent suggestions based on free time
    String mainSuggestion;
    String tip;
    String suggestedTime;
    String suggestedDay;
    int rotationIndex = 0;
    
    if (moduleRecommendations.isNotEmpty && freeSlots.isNotEmpty) {
      // Create rotation based on current time to ensure variety
      rotationIndex = (now.hour + now.minute ~/ 15) % freeSlots.length;
      
      // Use actual free time slots instead of random times
      final selectedFreeSlot = freeSlots[rotationIndex % freeSlots.length];
      suggestedTime = selectedFreeSlot['time'] as String;
      suggestedDay = selectedFreeSlot['day'] as String;
      
      // Rotate through different modules for variety
      final focusModule = moduleRecommendations[rotationIndex % moduleRecommendations.length];
      final moduleNames = moduleRecommendations.take(3).map((m) => m['moduleName'] as String).toList();
      
      // Generate varied suggestions based on rotation
      switch (rotationIndex % 3) {
        case 0:
          mainSuggestion = 'Perfect free time for ${focusModule['moduleName']}! ${focusModule['studyFocus']}';
          tip = 'This ${selectedFreeSlot['period']} slot is ideal for ${focusModule['moduleCode']} study.';
          break;
        case 1:
          mainSuggestion = 'Use your free ${selectedFreeSlot['period']} for ${focusModule['moduleName']} practice! ${focusModule['studyMethod']}';
          tip = 'Your schedule shows ${selectedFreeSlot['duration']}min available - perfect for focused work.';
          break;
        case 2:
          mainSuggestion = 'Free ${selectedFreeSlot['period']} review: ${moduleNames.join(' and ')}. Make the most of this ${selectedFreeSlot['duration']}min slot!';
          tip = 'Review sessions work great in your ${selectedFreeSlot['period']} free time.';
          break;
        default:
          mainSuggestion = 'Study time in your free ${selectedFreeSlot['period']}! Work on ${focusModule['moduleName']}.';
          tip = 'Consistent use of free time leads to better results.';
      }
      
    } else {
      mainSuggestion = 'No upcoming classes found. Great time for general review and preparation!';
      tip = 'Use free time to strengthen your foundational knowledge.';
      suggestedTime = '14:00';
      suggestedDay = 'Monday';
    }
    
    print('Debug AI: Generated suggestion for ${moduleRecommendations.length} modules');
    print('Debug AI: Rotation index: $rotationIndex (changes every 15 min)');
    print('Debug AI: Selected time: $suggestedTime, day: $suggestedDay');
    print('Debug AI: Main suggestion: $mainSuggestion');
    
    // Generate daily study plan
    final dailyPlan = _generateDailyStudyPlan(freeSlots, moduleRecommendations);
    
    return {
      'suggestion': mainSuggestion,
      'tip': tip,
      'suggestedDay': suggestedDay,
      'suggestedTime': suggestedTime,
      'duration': '60',
      'priority': moduleRecommendations.isNotEmpty && (moduleRecommendations.first['daysUntil'] as int) <= 1 ? 'High' : 'Medium',
      'reasoning': freeSlots.isNotEmpty 
        ? 'Based on your timetable, you have ${freeSlots.length} free time slots available this week. This ${freeSlots[rotationIndex % freeSlots.length]['period']} slot works perfectly.'
        : _generateVariedReasoning(moduleRecommendations, now.hour),
      'moduleRecommendations': moduleRecommendations,
      'dailyPlan': dailyPlan,
      'allModules': moduleRecommendations.map((m) => {
        'code': m['moduleCode'],
        'name': m['moduleName'],
        'daysUntil': m['daysUntil'],
        'difficulty': m['difficulty'],
        'preparationTime': m['recommendedTime'],
      }).toList(),
    };
  }

  // Generate specific recommendation for each module
  static Map<String, dynamic> _generateModuleRecommendation(String moduleCode, String moduleName, int daysUntil) {
    String studyFocus;
    String studyMethod;
    int recommendedTime;
    String difficulty;
    
    // Module-specific recommendations
    if (moduleCode.contains('DICT300')) {
      studyFocus = 'Project development, documentation, and presentation skills';
      studyMethod = 'Work on project milestones, review requirements, practice demos';
      recommendedTime = 90;
      difficulty = 'High';
    } else if (moduleCode.contains('DICT312')) {
      studyFocus = 'Programming concepts, application development, debugging techniques';
      studyMethod = 'Code practice, build mini-projects, review frameworks and APIs';
      recommendedTime = 75;
      difficulty = 'High';
    } else if (moduleCode.contains('DICT322')) {
      studyFocus = 'Database design, system analysis, information modeling';
      studyMethod = 'Practice SQL queries, study ER diagrams, review case studies';
      recommendedTime = 60;
      difficulty = 'Medium';
    } else {
      studyFocus = 'Core concepts and practical applications';
      studyMethod = 'Review notes, practice exercises, create study summaries';
      recommendedTime = 60;
      difficulty = 'Medium';
    }
    
    // Adjust based on urgency
    if (daysUntil <= 1) recommendedTime += 15;
    
    return {
      'moduleCode': moduleCode,
      'moduleName': moduleName,
      'studyFocus': studyFocus,
      'studyMethod': studyMethod,
      'recommendedTime': recommendedTime,
      'daysUntil': daysUntil,
      'difficulty': difficulty,
      'urgency': daysUntil <= 1 ? 'High' : daysUntil <= 3 ? 'Medium' : 'Low',
    };
  }

  // Find realistic free time slots considering student life and preferences
  static List<Map<String, dynamic>> _findFreeTimeSlots(
    Map<String, Map<String, List<Session>>> timetableData,
    List<StudySession> existingStudySessions,
    String studyPreference,
  ) {
    // Debug logging reduced for production
    final freeSlots = <Map<String, dynamic>>[];
    final days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    
    // Handle gracefully when no timetable data exists
    final hasLimitedData = timetableData.isEmpty || 
        timetableData.values.every((dayData) => dayData.isEmpty);
    
    if (hasLimitedData) {
      print('Debug AI: Limited timetable data - using flexible scheduling mode');
      // When no class schedule exists, provide more flexible study slots
      return _generateFlexibleStudySlots(studyPreference, existingStudySessions);
    }
    
    for (final day in days) {
      final daySchedule = timetableData[day] ?? {};
      final blockedTimes = <String>[];
      
      // Get class times and add realistic buffers
      for (final timeSlot in daySchedule.keys) {
        for (final session in daySchedule[timeSlot]!) {
          final startTime = session.startTime ?? '00:00';
          final endTime = session.endTime ?? '00:00';
          
          // Add preparation time (30 min before class)
          final prepTime = _subtractMinutes(startTime, 30);
          blockedTimes.add('$prepTime-$startTime');
          
          // Add actual class time
          blockedTimes.add('$startTime-$endTime');
          
          // Add rest/transition time (60 min after class)
          final restTime = _addMinutes(endTime, 60);
          blockedTimes.add('$endTime-$restTime');
        }
      }
      
      // Add existing study sessions
      for (final studySession in existingStudySessions) {
        if (studySession.dayOfWeek == day) {
          blockedTimes.add('${studySession.startTime}-${studySession.endTime}');
        }
      }
      
      // Add lunch break (12:00-13:00 - students need to eat!)
      blockedTimes.add('12:00-13:00');
      
      // Define personalized study time slots based on preference
      List<Map<String, dynamic>> potentialSlots;
      
      switch (studyPreference) {
        case 'morning':
          potentialSlots = [
            {'time': '06:00', 'end': '07:30', 'period': 'very early morning', 'duration': 90},
            {'time': '07:00', 'end': '08:30', 'period': 'early morning', 'duration': 90},
            {'time': '09:00', 'end': '10:30', 'period': 'morning', 'duration': 90},
            {'time': '10:30', 'end': '12:00', 'period': 'late morning', 'duration': 90},
            {'time': '13:30', 'end': '15:00', 'period': 'early afternoon', 'duration': 90},
          ];
          print('Debug AI: Generated MORNING slots for $day');
          break;
        case 'afternoon':
          potentialSlots = [
            {'time': '10:30', 'end': '12:00', 'period': 'late morning', 'duration': 90},
            {'time': '13:30', 'end': '15:00', 'period': 'early afternoon', 'duration': 90},
            {'time': '15:30', 'end': '17:00', 'period': 'afternoon', 'duration': 90},
            {'time': '17:30', 'end': '19:00', 'period': 'late afternoon', 'duration': 90},
          ];
          break;
        case 'evening':
          potentialSlots = [
            {'time': '15:30', 'end': '17:00', 'period': 'afternoon', 'duration': 90},
            {'time': '17:30', 'end': '19:00', 'period': 'evening', 'duration': 90},
            {'time': '19:30', 'end': '21:00', 'period': 'evening', 'duration': 90},
            {'time': '21:00', 'end': '22:30', 'period': 'late evening', 'duration': 90},
          ];
          break;
        case 'night':
          potentialSlots = [
            {'time': '19:30', 'end': '21:00', 'period': 'evening', 'duration': 90},
            {'time': '21:00', 'end': '22:30', 'period': 'late evening', 'duration': 90},
            {'time': '22:30', 'end': '00:00', 'period': 'night', 'duration': 90},
            {'time': '23:00', 'end': '00:30', 'period': 'late night', 'duration': 90},
            // Early morning for night owls who stay up late
            {'time': '00:30', 'end': '02:00', 'period': 'very late night', 'duration': 90},
          ];
          print('Debug AI: Generated NIGHT slots for $day');
          break;
        default: // 'balanced'
          potentialSlots = [
            {'time': '07:00', 'end': '08:30', 'period': 'early morning', 'duration': 90},
            {'time': '09:00', 'end': '10:30', 'period': 'morning', 'duration': 90},
            {'time': '10:30', 'end': '12:00', 'period': 'late morning', 'duration': 90},
            {'time': '13:30', 'end': '15:00', 'period': 'early afternoon', 'duration': 90},
            {'time': '15:30', 'end': '17:00', 'period': 'afternoon', 'duration': 90},
            {'time': '17:30', 'end': '19:00', 'period': 'evening', 'duration': 90},
            {'time': '19:30', 'end': '21:00', 'period': 'evening', 'duration': 90},
            {'time': '21:00', 'end': '22:30', 'period': 'late evening', 'duration': 90},
          ];
      }
      
      // Find realistic free slots
      for (final slot in potentialSlots) {
        final slotTime = '${slot['time']}-${slot['end']}';
        bool isBlocked = false;
        
        // Check if this slot conflicts with any blocked time
        for (final blocked in blockedTimes) {
          if (_timeSlotOverlaps(slotTime, blocked)) {
            isBlocked = true;
            break;
          }
        }
        
        // Additional realism checks
        if (!isBlocked) {
          // Don't suggest very early morning unless student is a morning person
          if (slot['time'] == '07:00' && _hasEarlyClasses(daySchedule)) {
            continue; // Skip if student already has early classes
          }
          
          // Don't suggest late evening on days with early morning classes next day
          if (slot['time'] == '21:00' && _hasEarlyClassNextDay(day, timetableData)) {
            continue;
          }
          
          freeSlots.add({
            'day': day,
            'time': slot['time'],
            'end': slot['end'],
            'period': slot['period'],
            'duration': slot['duration'],
            'quality': _assessSlotQuality(slot, daySchedule, studyPreference),
          });
        }
      }
    }
    
    // Sort by quality (best slots first)
    freeSlots.sort((a, b) => (b['quality'] as int).compareTo(a['quality'] as int));
    
    print('Debug AI: Found ${freeSlots.length} realistic free time slots');
    for (final slot in freeSlots.take(5)) {
      print('  ${slot['day']} ${slot['time']}-${slot['end']} (${slot['period']}) - Quality: ${slot['quality']}');
    }
    
    return freeSlots;
  }

  // Generate flexible study slots when timetable data is incomplete
  static List<Map<String, dynamic>> _generateFlexibleStudySlots(
    String studyPreference, 
    List<StudySession> existingStudySessions
  ) {
    final freeSlots = <Map<String, dynamic>>[];
    final days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    
    // Define flexible study slots based on preference (no class conflicts to worry about)
    List<Map<String, dynamic>> potentialSlots;
    
    switch (studyPreference) {
      case 'morning':
        potentialSlots = [
          {'time': '07:00', 'end': '08:30', 'period': 'early morning', 'duration': 90},
          {'time': '08:30', 'end': '10:00', 'period': 'morning', 'duration': 90},
          {'time': '10:00', 'end': '11:30', 'period': 'late morning', 'duration': 90},
        ];
        break;
      case 'afternoon':
        potentialSlots = [
          {'time': '13:00', 'end': '14:30', 'period': 'early afternoon', 'duration': 90},
          {'time': '14:30', 'end': '16:00', 'period': 'afternoon', 'duration': 90},
          {'time': '16:00', 'end': '17:30', 'period': 'late afternoon', 'duration': 90},
        ];
        break;
      case 'evening':
        potentialSlots = [
          {'time': '17:30', 'end': '19:00', 'period': 'early evening', 'duration': 90},
          {'time': '19:00', 'end': '20:30', 'period': 'evening', 'duration': 90},
          {'time': '20:30', 'end': '22:00', 'period': 'late evening', 'duration': 90},
        ];
        break;
      case 'night':
        potentialSlots = [
          {'time': '21:00', 'end': '22:30', 'period': 'evening', 'duration': 90},
          {'time': '22:30', 'end': '00:00', 'period': 'night', 'duration': 90},
          {'time': '00:30', 'end': '02:00', 'period': 'late night', 'duration': 90},
        ];
        break;
      default: // 'balanced'
        potentialSlots = [
          {'time': '09:00', 'end': '10:30', 'period': 'morning', 'duration': 90},
          {'time': '14:00', 'end': '15:30', 'period': 'afternoon', 'duration': 90},
          {'time': '19:00', 'end': '20:30', 'period': 'evening', 'duration': 90},
        ];
        break;
    }
    
    // Add all potential slots for each day (no class conflicts to check)
    for (final day in days) {
      for (final slot in potentialSlots) {
        // Check against existing study sessions only
        final hasConflict = existingStudySessions.any((session) =>
          session.dayOfWeek.toLowerCase() == day.toLowerCase() &&
          _timeSlotOverlaps('${session.startTime}-${session.endTime}', '${slot['time']}-${slot['end']}')
        );
        
        if (!hasConflict) {
          freeSlots.add({
            'day': day,
            'time': slot['time'],
            'endTime': slot['end'],
            'period': slot['period'],
            'duration': slot['duration'],
            'quality': 85, // Good quality since no class conflicts
            'reason': 'Flexible scheduling - no class schedule constraints',
          });
        }
      }
    }
    
    print('Debug AI: Generated ${freeSlots.length} flexible study slots (no timetable constraints)');
    return freeSlots;
  }
  
  // Add minutes to time string
  static String _addMinutes(String time, int minutes) {
    try {
      final parts = time.split(':');
      final totalMinutes = int.parse(parts[0]) * 60 + int.parse(parts[1]) + minutes;
      final hours = (totalMinutes ~/ 60) % 24;
      final mins = totalMinutes % 60;
      return '${hours.toString().padLeft(2, '0')}:${mins.toString().padLeft(2, '0')}';
    } catch (e) {
      return time;
    }
  }
  
  // Subtract minutes from time string
  static String _subtractMinutes(String time, int minutes) {
    try {
      final parts = time.split(':');
      final totalMinutes = int.parse(parts[0]) * 60 + int.parse(parts[1]) - minutes;
      if (totalMinutes < 0) return '00:00';
      final hours = (totalMinutes ~/ 60) % 24;
      final mins = totalMinutes % 60;
      return '${hours.toString().padLeft(2, '0')}:${mins.toString().padLeft(2, '0')}';
    } catch (e) {
      return time;
    }
  }
  
  // Check if student has early classes (before 9 AM)
  static bool _hasEarlyClasses(Map<String, List<Session>> daySchedule) {
    for (final sessions in daySchedule.values) {
      for (final session in sessions) {
        final startHour = int.tryParse((session.startTime ?? '00:00').split(':')[0]) ?? 0;
        if (startHour <= 8) return true;
      }
    }
    return false;
  }
  
  // Check if student has early class next day
  static bool _hasEarlyClassNextDay(String currentDay, Map<String, Map<String, List<Session>>> timetableData) {
    final days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    final currentIndex = days.indexOf(currentDay);
    if (currentIndex == -1 || currentIndex == days.length - 1) return false;
    
    final nextDay = days[currentIndex + 1];
    final nextDaySchedule = timetableData[nextDay] ?? {};
    
    return _hasEarlyClasses(nextDaySchedule);
  }
  
  // Assess quality of study slot based on student preference (higher = better)
  static int _assessSlotQuality(Map<String, dynamic> slot, Map<String, List<Session>> daySchedule, String studyPreference) {
    int quality = 50; // Base quality
    
    final period = slot['period'] as String;
    final duration = slot['duration'] as int;
    
    // Prefer longer study sessions
    if (duration >= 90) quality += 20;
    
    // Personalized time preferences
    switch (studyPreference) {
      case 'morning':
        switch (period) {
          case 'very early morning':
          case 'early morning':
          case 'morning':
            quality += 25; // Perfect for morning people
            break;
          case 'late morning':
            quality += 20;
            break;
          case 'early afternoon':
            quality += 10;
            break;
          default:
            quality -= 10; // Not ideal for morning people
        }
        break;
        
      case 'afternoon':
        switch (period) {
          case 'late morning':
          case 'early afternoon':
          case 'afternoon':
            quality += 25; // Perfect for afternoon people
            break;
          case 'late afternoon':
            quality += 20;
            break;
          case 'morning':
            quality += 10;
            break;
          default:
            quality -= 5;
        }
        break;
        
      case 'evening':
        switch (period) {
          case 'afternoon':
          case 'late afternoon':
          case 'evening':
            quality += 25; // Perfect for evening people
            break;
          case 'late evening':
            quality += 20;
            break;
          case 'early afternoon':
            quality += 10;
            break;
          default:
            quality -= 5;
        }
        break;
        
      case 'night':
        switch (period) {
          case 'evening':
          case 'late evening':
          case 'night':
          case 'late night':
          case 'very late night':
            quality += 30; // Perfect for night owls
            break;
          case 'afternoon':
          case 'late afternoon':
            quality += 15;
            break;
          default:
            quality -= 15; // Morning times are terrible for night owls
        }
        break;
        
      default: // 'balanced'
        switch (period) {
          case 'morning':
          case 'late morning':
            quality += 15; // Generally good for most students
            break;
          case 'early afternoon':
          case 'afternoon':
            quality += 10;
            break;
          case 'evening':
            quality += 5;
            break;
          case 'very early morning':
            quality -= 10; // Too early for most
            break;
          case 'late night':
          case 'very late night':
            quality -= 15; // Too late for most
            break;
        }
    }
    
    // Bonus for days with fewer classes (less stressful)
    final classCount = daySchedule.values.expand((sessions) => sessions).length;
    if (classCount <= 1) quality += 10;
    if (classCount >= 3) quality -= 10;
    
    return quality;
  }
  
  // Check if two time slots overlap
  static bool _timeSlotOverlaps(String slot1, String slot2) {
    try {
      final slot1Parts = slot1.split('-');
      final slot2Parts = slot2.split('-');
      
      final slot1Start = _timeToMinutes(slot1Parts[0]);
      final slot1End = _timeToMinutes(slot1Parts[1]);
      final slot2Start = _timeToMinutes(slot2Parts[0]);
      final slot2End = _timeToMinutes(slot2Parts[1]);
      
      return (slot1Start < slot2End) && (slot2Start < slot1End);
    } catch (e) {
      return false;
    }
  }
  
  // Convert time string to minutes since midnight
  static int _timeToMinutes(String time) {
    final parts = time.split(':');
    return int.parse(parts[0]) * 60 + int.parse(parts[1]);
  }

  // Generate daily study plan for each day
  static Map<String, List<Map<String, dynamic>>> _generateDailyStudyPlan(
    List<Map<String, dynamic>> freeSlots,
    List<Map<String, dynamic>> moduleRecommendations,
  ) {
    final dailyPlan = <String, List<Map<String, dynamic>>>{};
    final days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    
    // Group free slots by day
    final slotsByDay = <String, List<Map<String, dynamic>>>{};
    for (final day in days) {
      slotsByDay[day] = freeSlots.where((slot) => slot['day'] == day).toList();
    }
    
    // Generate suggestions for each day
    for (final day in days) {
      final daySlots = slotsByDay[day] ?? [];
      final daySuggestions = <Map<String, dynamic>>[];
      
      if (daySlots.isNotEmpty && moduleRecommendations.isNotEmpty) {
        // Pick non-overlapping slots for this day
        final selectedSlots = <Map<String, dynamic>>[];
        
        for (final slot in daySlots) {
          // Check if this slot overlaps with any already selected slot
          bool hasOverlap = false;
          for (final selected in selectedSlots) {
            final slotTime = '${slot['time']}-${slot['end']}';
            final selectedTime = '${selected['time']}-${selected['end']}';
            if (_timeSlotOverlaps(slotTime, selectedTime)) {
              hasOverlap = true;
              break;
            }
          }
          
          // Only add if no overlap and we haven't reached the limit
          // Increased limit from 2 to 3 to allow more variety
          if (!hasOverlap && selectedSlots.length < 3) {
            selectedSlots.add(slot);
          }
        }
        
        print('Debug AI: $day - Selected ${selectedSlots.length} non-overlapping slots from ${daySlots.length} available');
        
        // Generate suggestions for non-overlapping slots
        for (int i = 0; i < selectedSlots.length; i++) {
          final slot = selectedSlots[i];
          // FIX: Use a day-based offset so we don't always start with the same modules every day
          final dayOffset = days.indexOf(day) * 2; 
          final module = moduleRecommendations[(dayOffset + i) % moduleRecommendations.length];
          
          String suggestion;
          String focus;
          String studyType;
          
          // Generate realistic day-specific suggestions
          if (slot['period'] == 'early morning') {
            suggestion = 'Quick ${module['moduleCode']} review';
            focus = 'Light prep before your day (avoid heavy study this early)';
            studyType = 'Light Review';
          } else if (slot['period'] == 'morning' || slot['period'] == 'late morning') {
            suggestion = '${module['moduleCode']} focused study';
            focus = module['studyFocus'] as String;
            studyType = 'Deep Work';
          } else if (slot['period'] == 'early afternoon' || slot['period'] == 'afternoon') {
            suggestion = '${module['moduleCode']} practice session';
            focus = module['studyMethod'] as String;
            studyType = 'Practice';
          } else if (slot['period'] == 'evening') {
            suggestion = '${module['moduleCode']} review & notes';
            focus = 'Consolidate today\'s learning and organize notes';
            studyType = 'Review';
          } else if (slot['period'] == 'night review') {
            suggestion = 'Light ${module['moduleCode']} reading';
            focus = 'Easy reading only - no heavy concentration needed';
            studyType = 'Light Reading';
          } else {
            suggestion = '${module['moduleCode']} study';
            focus = 'General study session';
            studyType = 'Study';
          }
          
          daySuggestions.add({
            'time': slot['time'],
            'endTime': slot['end'],
            'duration': slot['duration'],
            'period': slot['period'],
            'moduleCode': module['moduleCode'],
            'moduleName': module['moduleName'],
            'suggestion': suggestion,
            'focus': focus,
            'studyType': studyType,
            'difficulty': module['difficulty'],
            'urgency': module['urgency'],
            'quality': slot['quality'],
          });
        }
      }
      
      dailyPlan[day] = daySuggestions;
    }
    
    // Daily plan generated successfully
    
    return dailyPlan;
  }

  // Generate varied reasoning based on context
  static String _generateVariedReasoning(List<Map<String, dynamic>> moduleRecommendations, int currentHour) {
    if (moduleRecommendations.isEmpty) {
      return 'Perfect time for general study and skill development.';
    }
    
    final urgentModules = moduleRecommendations.where((m) => m['urgency'] == 'High').length;
    final totalModules = moduleRecommendations.length;
    
    if (urgentModules > 0) {
      return 'You have $urgentModules urgent module(s) that need attention. Focus on high-priority subjects first.';
    } else if (currentHour < 12) {
      return 'Morning is ideal for tackling challenging subjects like ${moduleRecommendations.first['moduleCode']}.';
    } else if (currentHour < 17) {
      return 'Afternoon study session focusing on your $totalModules active modules.';
    } else {
      return 'Evening review to consolidate learning from all your modules.';
    }
  }

  // Analyze timetable to find free time slots and patterns
  static Map<String, dynamic> _analyzeTimetable(
    Map<String, Map<String, List<Session>>> timetableData,
    List<StudySession> existingStudySessions,
  ) {
    final analysis = <String, dynamic>{
      'freeTimeSlots': <Map<String, dynamic>>[],
      'upcomingClasses': <Map<String, dynamic>>[],
      'studyGaps': <Map<String, dynamic>>[],
      'busyDays': <String>[],
      'lightDays': <String>[],
      'studyPatterns': <Map<String, dynamic>>{},
      'energyLevels': <Map<String, String>>{},
      'moduleDifficulty': <Map<String, String>>{},
      'optimalStudyTimes': <String>[],
      'studyStreak': 0,
      'productivityScore': 0.0,
    };

    // Define study hours (8 AM to 6 PM)
    final studyHours = <String>[
      '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
      '11:00', '11:30', '12:00', '12:30', '13:00', '13:30',
      '14:00', '14:30', '15:00', '15:30', '16:00', '16:30',
      '17:00', '17:30', '18:00'
    ];

    // Analyze study patterns and productivity
    _analyzeStudyPatterns(existingStudySessions, analysis);
    
    // Analyze each day with enhanced intelligence
    for (final day in ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']) {
      final daySessions = timetableData[day] ?? {};
      final scheduledTimes = <String>[];
      
      // Collect all scheduled times for this day
      for (final timeSlot in daySessions.keys) {
        scheduledTimes.add(timeSlot);
      }
      
      // Find free time slots with energy level analysis
      final freeSlots = <String>[];
      final energyLevels = <String, String>{};
      
      for (final hour in studyHours) {
        if (!scheduledTimes.contains(hour)) {
          freeSlots.add(hour);
          // Determine energy level for this time
          energyLevels[hour] = _getEnergyLevel(hour, day);
        }
      }
      
    // Categorize days based on class load and difficulty
    final dayDifficulty = _calculateDayDifficulty(daySessions);
    if (daySessions.length >= 3 || dayDifficulty > 0.7) {
      (analysis['busyDays'] as List<String>).add(day);
    } else if (daySessions.length <= 1 && dayDifficulty < 0.3) {
      (analysis['lightDays'] as List<String>).add(day);
    }
      
      // Add free time slots to analysis with energy levels
      if (freeSlots.isNotEmpty) {
        analysis['freeTimeSlots']!.add({
          'day': day,
          'freeSlots': freeSlots,
          'classCount': daySessions.length,
          'difficulty': dayDifficulty,
          'energyLevels': energyLevels,
          'optimalSlots': _findOptimalSlots(freeSlots, energyLevels),
        });
      }
      
      // Find upcoming classes (next 2 days) with priority scoring
      final today = DateTime.now().weekday;
      final dayIndex = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].indexOf(day);
      final daysUntil = (dayIndex - today + 7) % 7;
      
      if (daysUntil <= 2 && daySessions.isNotEmpty) {
        for (final timeSlot in daySessions.keys) {
          final sessions = daySessions[timeSlot]!;
          for (final session in sessions) {
            final priority = _calculateClassPriority(session, daysUntil);
            analysis['upcomingClasses']!.add({
              'day': day,
              'time': timeSlot,
              'module': session.moduleName,
              'code': session.moduleCode,
              'daysUntil': daysUntil,
              'priority': priority,
              'difficulty': _getModuleDifficulty(session.moduleCode),
              'preparationTime': _calculatePreparationTime(session, priority),
            });
          }
        }
      }
    }
    
    // Sort upcoming classes by priority
    (analysis['upcomingClasses'] as List).sort((a, b) => (b['priority'] as double).compareTo(a['priority'] as double));
    
    return analysis;
  }

  // Generate personalized study suggestions
  static Map<String, dynamic> _generatePersonalizedSuggestions(Map<String, dynamic> analysis) {
    final freeTimeSlots = analysis['freeTimeSlots'] as List<Map<String, dynamic>>;
    final upcomingClasses = analysis['upcomingClasses'] as List<Map<String, dynamic>>;
    final busyDays = analysis['busyDays'] as List<String>;
    final lightDays = analysis['lightDays'] as List<String>;

    // Find the best study time slot
    String? bestDay;
    String? bestTime;
    String suggestion = '';
    String tip = '';

    if (freeTimeSlots.isNotEmpty) {
      // Prefer light days for study sessions
      final lightDaySlots = freeTimeSlots.where((slot) => lightDays.contains(slot['day'])).toList();
      final targetSlots = lightDaySlots.isNotEmpty ? lightDaySlots : freeTimeSlots;
      
      // Find the first available slot
      final bestSlot = targetSlots.first;
      bestDay = bestSlot['day'];
      bestTime = bestSlot['freeSlots'].first;
      
      // Generate contextual suggestions for all modules
      if (upcomingClasses.isNotEmpty) {
        // Get all unique modules from upcoming classes
        final allModules = <String, Map<String, dynamic>>{};
        for (final classInfo in upcomingClasses) {
          final moduleCode = classInfo['code'];
          if (!allModules.containsKey(moduleCode)) {
            allModules[moduleCode] = classInfo;
          }
        }
        
        // Create suggestions for multiple modules
        final moduleNames = allModules.values.map((m) => m['module'] as String).take(3).toList();
        final nextClass = upcomingClasses.first;
        final daysUntil = nextClass['daysUntil'];
        
        if (moduleNames.length > 1) {
          if (daysUntil == 0) {
            suggestion = 'Focus on ${moduleNames.join(", ")} - you have classes today!';
            tip = 'Review key concepts for all your modules to maximize today\'s learning.';
          } else if (daysUntil == 1) {
            suggestion = 'Prepare for tomorrow\'s classes: ${moduleNames.join(", ")}.';
            tip = 'Study each module for 20-30 minutes to stay on top of all subjects.';
          } else {
            suggestion = 'Study plan for your modules: ${moduleNames.join(", ")}.';
            tip = 'Rotate between subjects to keep your learning fresh and engaging.';
          }
        } else {
          final moduleName = nextClass['module'];
        if (daysUntil == 0) {
          suggestion = 'Review notes for $moduleName before your class today.';
          tip = 'Quick review sessions help reinforce learning and improve retention.';
        } else if (daysUntil == 1) {
          suggestion = 'Prepare for tomorrow\'s $moduleName class.';
          tip = 'Early preparation reduces stress and improves understanding.';
        } else {
          suggestion = 'Start preparing for $moduleName coming up in $daysUntil days.';
          tip = 'Spreading study time over multiple days is more effective than cramming.';
          }
        }
      } else {
        // General study suggestions
        final suggestions = [
          'Review your notes from recent classes.',
          'Work on assignments and projects.',
          'Practice problems and exercises.',
          'Read ahead for upcoming topics.',
          'Create study summaries and flashcards.',
        ];
        
        suggestion = suggestions[DateTime.now().day % suggestions.length];
        tip = 'Consistent daily study habits lead to better academic performance.';
      }
    } else {
      // No free time slots found
      suggestion = 'Your schedule is quite busy! Consider studying early morning or evening.';
      tip = 'Even 30 minutes of focused study can make a big difference.';
      bestDay = 'Monday';
      bestTime = '07:00';
    }

    // Generate module-specific study recommendations
    final moduleRecommendations = _generateModuleRecommendations(upcomingClasses);

    return {
      'suggestion': suggestion,
      'tip': tip,
      'suggestedDay': bestDay,
      'suggestedTime': bestTime,
      'duration': '60', // minutes
      'priority': upcomingClasses.isNotEmpty ? 'High' : 'Medium',
      'reasoning': _generateReasoning(analysis),
      'moduleRecommendations': moduleRecommendations,
      'allModules': upcomingClasses.map((c) => {
        'code': c['code'],
        'name': c['module'],
        'daysUntil': c['daysUntil'],
        'difficulty': c['difficulty'],
        'preparationTime': c['preparationTime'],
      }).toList(),
    };
  }

  // Generate module-specific recommendations
  static List<Map<String, dynamic>> _generateModuleRecommendations(List<Map<String, dynamic>> upcomingClasses) {
    print('Debug AI: Processing ${upcomingClasses.length} upcoming classes for module recommendations');
    final moduleRecommendations = <Map<String, dynamic>>[];
    final processedModules = <String>{};
    
    for (final classInfo in upcomingClasses) {
      final moduleCode = classInfo['code'] as String;
      print('Debug AI: Processing module: $moduleCode');
      if (processedModules.contains(moduleCode)) {
        print('Debug AI: Module $moduleCode already processed, skipping');
        continue;
      }
      
      processedModules.add(moduleCode);
      final moduleName = classInfo['module'] as String;
      final daysUntil = classInfo['daysUntil'] as int;
      final difficulty = classInfo['difficulty'] as String;
      
      // Generate specific study recommendations based on module type and difficulty
      String studyFocus = '';
      String studyMethod = '';
      int recommendedTime = 60;
      
      if (moduleCode.contains('DICT300')) {
        studyFocus = 'Project planning, implementation, and documentation';
        studyMethod = 'Work on project milestones, review requirements, practice presentations';
        recommendedTime = 90;
      } else if (moduleCode.contains('DICT312')) {
        studyFocus = 'Coding practice, application development, debugging';
        studyMethod = 'Practice coding exercises, build small apps, review frameworks';
        recommendedTime = 75;
      } else if (moduleCode.contains('DICT322')) {
        studyFocus = 'System analysis, database design, information modeling';
        studyMethod = 'Study diagrams, practice SQL, review case studies';
        recommendedTime = 60;
      } else {
        studyFocus = 'Core concepts, practical applications, exam preparation';
        studyMethod = 'Review notes, practice exercises, create summaries';
        recommendedTime = 60;
      }
      
      // Adjust time based on difficulty and days until class
      if (difficulty == 'High') recommendedTime += 15;
      if (daysUntil <= 1) recommendedTime += 15;
      
      moduleRecommendations.add({
        'moduleCode': moduleCode,
        'moduleName': moduleName,
        'studyFocus': studyFocus,
        'studyMethod': studyMethod,
        'recommendedTime': recommendedTime,
        'daysUntil': daysUntil,
        'difficulty': difficulty,
        'urgency': daysUntil <= 1 ? 'High' : daysUntil <= 3 ? 'Medium' : 'Low',
      });
    }
    
    // Sort by urgency and difficulty
    moduleRecommendations.sort((a, b) {
      final urgencyOrder = {'High': 3, 'Medium': 2, 'Low': 1};
      final aUrgency = urgencyOrder[a['urgency']] ?? 1;
      final bUrgency = urgencyOrder[b['urgency']] ?? 1;
      return bUrgency.compareTo(aUrgency);
    });
    
    // Module recommendations generated
    
    return moduleRecommendations;
  }

  // Generate reasoning for the suggestion
  static String _generateReasoning(Map<String, dynamic> analysis) {
    final busyDays = analysis['busyDays'] as List<String>;
    final lightDays = analysis['lightDays'] as List<String>;
    final upcomingClasses = analysis['upcomingClasses'] as List<Map<String, dynamic>>;
    
    if (upcomingClasses.isNotEmpty) {
      return 'Based on your upcoming classes, this time slot will help you prepare effectively.';
    } else if (lightDays.isNotEmpty) {
      return 'This day has fewer classes, giving you more time to focus on studying.';
    } else if (busyDays.length >= 3) {
      return 'Your schedule is quite packed, so we\'ve found an optimal study window.';
    } else {
      return 'This time slot fits well with your current schedule.';
    }
  }

  // Create a study session from the suggestion
  static StudySession createStudySessionFromSuggestion(Map<String, dynamic> suggestion) {
    final now = DateTime.now();
    final suggestedDay = suggestion['suggestedDay'] as String;
    final suggestedTime = suggestion['suggestedTime'] as String;
    final duration = int.parse(suggestion['duration'] as String);
    
    // Calculate end time
    final timeParts = suggestedTime.split(':');
    final startHour = int.parse(timeParts[0]);
    final startMinute = int.parse(timeParts[1]);
    final totalMinutes = startHour * 60 + startMinute + duration;
    final endHour = (totalMinutes ~/ 60) % 24;
    final endMinute = totalMinutes % 60;
    final endTime = '${endHour.toString().padLeft(2, '0')}:${endMinute.toString().padLeft(2, '0')}';
    
    return StudySession(
      sessionId: DateTime.now().millisecondsSinceEpoch,
      title: suggestion['title'] ?? 'AI Suggested Study Session',
      moduleCode: suggestion['moduleCode'] ?? 'STUDY',
      moduleName: suggestion['moduleName'] ?? 'Study Session',
      dayOfWeek: suggestedDay,
      startTime: suggestedTime,
      endTime: endTime,
      venue: suggestion['location'] ?? 'Study Area',
      sessionType: 'study',
      notes: suggestion['suggestion'] as String,
      duration: duration,
      createdAt: now,
      isAutoGenerated: true,
    );
  }

  // Enhanced helper methods for intelligent analysis

  // Analyze study patterns from existing sessions
  static void _analyzeStudyPatterns(List<StudySession> studySessions, Map<String, dynamic> analysis) {
    if (studySessions.isEmpty) return;
    
    final now = DateTime.now();
    final recentSessions = studySessions.where((session) => 
      now.difference(session.createdAt).inDays <= 7
    ).toList();
    
    // Calculate study streak
    int streak = 0;
    DateTime currentDate = now;
    for (int i = 0; i < 7; i++) {
      final hasStudyOnDate = recentSessions.any((session) => 
        session.createdAt.day == currentDate.day && 
        session.createdAt.month == currentDate.month
      );
      if (hasStudyOnDate) {
        streak++;
        currentDate = currentDate.subtract(Duration(days: 1));
      } else {
        break;
      }
    }
    
    analysis['studyStreak'] = streak;
    
    // Calculate productivity score
    final totalStudyTime = recentSessions.fold(0, (sum, session) => 
      sum + (session.duration ?? 60) // Use duration field or default to 60 minutes
    );
    analysis['productivityScore'] = (totalStudyTime / (7 * 60)).clamp(0.0, 1.0);
    
    // Find optimal study times
    final timeFrequency = <String, int>{};
    for (final session in recentSessions) {
      // Parse time string to get hour and minute
      final timeParts = session.startTime.split(':');
      if (timeParts.length >= 2) {
        final hour = timeParts[0].padLeft(2, '0');
        final minute = timeParts[1].padLeft(2, '0');
        final timeKey = '$hour:$minute';
        timeFrequency[timeKey] = (timeFrequency[timeKey] ?? 0) + 1;
      }
    }
    
    final sortedTimes = timeFrequency.entries.toList()
      ..sort((a, b) => b.value.compareTo(a.value));
    
    analysis['optimalStudyTimes'] = sortedTimes.take(3).map((e) => e.key).toList();
  }

  // Get energy level for a specific time
  static String _getEnergyLevel(String time, String day) {
    final hour = int.parse(time.split(':')[0]);
    
    // Morning energy (8-11 AM)
    if (hour >= 8 && hour < 11) return 'High';
    // Afternoon energy (2-4 PM)
    if (hour >= 14 && hour < 16) return 'Medium';
    // Evening energy (5-7 PM)
    if (hour >= 17 && hour < 19) return 'Low';
    // Default
    return 'Medium';
  }

  // Calculate day difficulty based on classes
  static double _calculateDayDifficulty(Map<String, List<Session>> daySessions) {
    if (daySessions.isEmpty) return 0.0;
    
    double totalDifficulty = 0.0;
    int classCount = 0;
    
    for (final sessions in daySessions.values) {
      for (final session in sessions) {
        totalDifficulty += _getModuleDifficulty(session.moduleCode);
        classCount++;
      }
    }
    
    return classCount > 0 ? totalDifficulty / classCount : 0.0;
  }

  // Get module difficulty based on code patterns
  static double _getModuleDifficulty(String moduleCode) {
    final code = moduleCode.toUpperCase();
    
    // Advanced modules (300+ level)
    if (code.contains('300') || code.contains('400') || code.contains('500')) {
      return 0.8;
    }
    // Intermediate modules (200 level)
    if (code.contains('200')) {
      return 0.6;
    }
    // Basic modules (100 level)
    if (code.contains('100')) {
      return 0.4;
    }
    // Default difficulty
    return 0.5;
  }

  // Find optimal time slots based on energy levels
  static List<String> _findOptimalSlots(List<String> freeSlots, Map<String, String> energyLevels) {
    final optimalSlots = <String>[];
    
    for (final slot in freeSlots) {
      final energy = energyLevels[slot] ?? 'Medium';
      if (energy == 'High') {
        optimalSlots.add(slot);
      }
    }
    
    // If no high energy slots, add medium energy slots
    if (optimalSlots.isEmpty) {
      for (final slot in freeSlots) {
        final energy = energyLevels[slot] ?? 'Medium';
        if (energy == 'Medium') {
          optimalSlots.add(slot);
        }
      }
    }
    
    return optimalSlots.take(3).toList();
  }

  // Calculate class priority
  static double _calculateClassPriority(Session session, int daysUntil) {
    double priority = 0.5;
    
    // Higher priority for closer classes
    if (daysUntil == 0) {
      priority += 0.4;
    } else if (daysUntil == 1) {
      priority += 0.3;
    } else if (daysUntil == 2) {
      priority += 0.2;
    }
    
    // Higher priority for difficult modules
    priority += _getModuleDifficulty(session.moduleCode) * 0.3;
    
    return priority.clamp(0.0, 1.0);
  }

  // Calculate preparation time needed
  static int _calculatePreparationTime(Session session, double priority) {
    final baseTime = 60; // 1 hour base
    final difficultyMultiplier = _getModuleDifficulty(session.moduleCode);
    final priorityMultiplier = priority;
    
    return (baseTime * (1 + difficultyMultiplier + priorityMultiplier)).round();
  }

  // Add smart features to suggestions
  static Map<String, dynamic> _addSmartFeatures(
    Map<String, dynamic> analysis,
    List<StudySession> existingStudySessions,
  ) {
    final features = <String, dynamic>{};
    
    // Add motivational messages based on study streak
    final streak = analysis['studyStreak'] as int;
    if (streak >= 3) {
      features['motivation'] = '🔥 Amazing! You\'re on a $streak-day study streak! Keep it up!';
    } else if (streak >= 1) {
      features['motivation'] = '💪 Great start! You\'ve studied $streak day(s) in a row.';
    } else {
      features['motivation'] = '🚀 Ready to start your study journey? Let\'s build a streak!';
    }
    
    // Add productivity insights
    final productivityScore = analysis['productivityScore'] as double;
    if (productivityScore > 0.7) {
      features['insight'] = '📈 You\'re being very productive! Your study habits are excellent.';
    } else if (productivityScore > 0.4) {
      features['insight'] = '📊 Good progress! Consider adding more study sessions for better results.';
    } else {
      features['insight'] = '💡 You have room to increase your study time. Every bit helps!';
    }
    
    // Add personalized study tips
    final optimalTimes = analysis['optimalStudyTimes'] as List<String>;
    if (optimalTimes.isNotEmpty) {
      features['personalTip'] = '⏰ Your most productive study times are: ${optimalTimes.join(', ')}';
    }
    
    return features;
  }
}



