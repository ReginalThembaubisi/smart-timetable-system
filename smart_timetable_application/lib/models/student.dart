class Student {
  final int studentId;
  final String studentNumber;
  final String fullName;
  final String? email;
  final String? programme;
  final String? year;

  Student({
    required this.studentId,
    required this.studentNumber,
    required this.fullName,
    this.email,
    this.programme,
    this.year,
  });

  factory Student.fromJson(Map<String, dynamic> json) {
    return Student(
      studentId: json['student_id'] ?? 0,
      studentNumber: json['student_number'] ?? '',
      fullName: json['full_name'] ?? '',
      email: json['email'],
      programme: json['programme'],
      year: json['year'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'student_id': studentId,
      'student_number': studentNumber,
      'full_name': fullName,
      'email': email,
      'programme': programme,
      'year': year,
    };
  }
}







