class Module {
  final int moduleId;
  final String moduleCode;
  final String moduleName;
  final String? semester;
  final int? credits;
  final String? programmeName;
  final String? yearName;

  Module({
    required this.moduleId,
    required this.moduleCode,
    required this.moduleName,
    this.semester,
    this.credits,
    this.programmeName,
    this.yearName,
  });

  factory Module.fromJson(Map<String, dynamic> json) {
    return Module(
      moduleId: json['module_id'] ?? 0,
      moduleCode: json['module_code'] ?? '',
      moduleName: json['module_name'] ?? '',
      semester: json['semester'],
      credits: json['credits'],
      programmeName: json['programme_name'],
      yearName: json['year_name'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'module_id': moduleId,
      'module_code': moduleCode,
      'module_name': moduleName,
      'semester': semester,
      'credits': credits,
      'programme_name': programmeName,
      'year_name': yearName,
    };
  }
}







