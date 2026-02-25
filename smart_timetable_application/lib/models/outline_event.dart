class OutlineEvent {
  final String title;
  final DateTime date;
  final String type; // 'Test', 'Assignment', 'Exam', 'Practical'
  final String moduleCode;
  final String? venue;
  final String? time;
  final bool isReminderSet;
  final int? reminderId;

  OutlineEvent({
    required this.title,
    required this.date,
    required this.type,
    required this.moduleCode,
    this.venue,
    this.time,
    this.isReminderSet = false,
    this.reminderId,
  });

  Map<String, dynamic> toJson() {
    return {
      'title': title,
      'date': date.toIso8601String(),
      'type': type,
      'moduleCode': moduleCode,
      'venue': venue,
      'time': time,
      'isReminderSet': isReminderSet,
      'reminderId': reminderId,
    };
  }

  factory OutlineEvent.fromJson(Map<String, dynamic> json) {
    return OutlineEvent(
      title: json['title'] ?? '',
      date: DateTime.parse(json['date']),
      type: json['type'] ?? 'Assignment',
      moduleCode: json['moduleCode'] ?? '',
      venue: json['venue'],
      time: json['time'],
      isReminderSet: json['isReminderSet'] ?? false,
      reminderId: json['reminderId'],
    );
  }

  OutlineEvent copyWith({
    String? title,
    DateTime? date,
    String? type,
    String? moduleCode,
    String? venue,
    String? time,
    bool? isReminderSet,
    int? reminderId,
  }) {
    return OutlineEvent(
      title: title ?? this.title,
      date: date ?? this.date,
      type: type ?? this.type,
      moduleCode: moduleCode ?? this.moduleCode,
      venue: venue ?? this.venue,
      time: time ?? this.time,
      isReminderSet: isReminderSet ?? this.isReminderSet,
      reminderId: reminderId ?? this.reminderId,
    );
  }
}
