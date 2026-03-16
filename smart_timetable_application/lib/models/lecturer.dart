class Lecturer {
  final int lecturerId;
  final String lecturerName;
  final String? email;
  final String? lecturerCode;
  final String? loginIdentifier;

  Lecturer({
    required this.lecturerId,
    required this.lecturerName,
    this.email,
    this.lecturerCode,
    this.loginIdentifier,
  });

  factory Lecturer.fromJson(Map<String, dynamic> json) {
    return Lecturer(
      lecturerId: json['lecturer_id'] is int
          ? json['lecturer_id'] as int
          : int.tryParse('${json['lecturer_id'] ?? 0}') ?? 0,
      lecturerName: '${json['lecturer_name'] ?? ''}',
      email: json['email']?.toString(),
      lecturerCode: json['lecturer_code']?.toString(),
      loginIdentifier: json['login_identifier']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'lecturer_id': lecturerId,
      'lecturer_name': lecturerName,
      'email': email,
      'lecturer_code': lecturerCode,
      'login_identifier': loginIdentifier,
    };
  }
}
