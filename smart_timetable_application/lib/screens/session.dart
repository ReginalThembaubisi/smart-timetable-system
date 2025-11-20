class Session {
  final String moduleName;
  final String moduleCode;
  final String sessionName;
  final String lecturerName;
  final String venueName;
  final String? startTime;
  final String? endTime;
  final String? sessionType;
  final String? dayOfWeek;

  Session({
    required this.moduleName,
    required this.moduleCode,
    required this.sessionName,
    required this.lecturerName,
    required this.venueName,
    this.startTime,
    this.endTime,
    this.sessionType,
    this.dayOfWeek,
  });

  factory Session.fromJson(Map<String, dynamic> json) {
    return Session(
      moduleName: json['module_name'] ?? '',
      moduleCode: json['module_code'] ?? '',
      // Prefer explicit session title if provided; otherwise fall back to module code/name
      sessionName: json['groups'] ?? json['session_name'] ?? json['title'] ?? (json['module_code'] ?? ''),
      // Backend provides lecturer_name/venue_name; keep legacy keys as fallback
      lecturerName: json['lecturer_name'] ?? json['lecturer'] ?? '',
      venueName: json['venue_name'] ?? json['venue'] ?? '',
      startTime: json['start_time'],
      endTime: json['end_time'],
      sessionType: json['session_type'] ?? (json['type'] ?? 'Lecture'),
      dayOfWeek: json['day'] ?? json['day_of_week'], // Backend returns 'day', fallback to 'day_of_week'
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'module_name': moduleName,
      'module_code': moduleCode,
      'groups': sessionName,
      'lecturer': lecturerName,
      'venue': venueName,
      'start_time': startTime,
      'end_time': endTime,
      'session_type': sessionType,
      'day_of_week': dayOfWeek,
    };
  }
}
