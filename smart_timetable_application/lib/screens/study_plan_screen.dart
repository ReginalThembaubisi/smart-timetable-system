import 'package:flutter/material.dart';
import '../models/student.dart';
import '../models/module.dart';
import '../services/api_service.dart';

class StudyPlanScreen extends StatefulWidget {
  final Student student;

  const StudyPlanScreen({
    Key? key,
    required this.student,
  }) : super(key: key);

  @override
  State<StudyPlanScreen> createState() => _StudyPlanScreenState();
}

class _StudyPlanScreenState extends State<StudyPlanScreen> {
  List<Module> studentModules = [];
  bool isLoading = true;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    try {
      setState(() {
        isLoading = true;
        errorMessage = null;
      });

      // Load student's current modules
      final modulesResponse = await ApiService.getStudentModules(widget.student.studentId);
      if (modulesResponse['success'] == true) {
        final list = (modulesResponse['modules'] as List?) ??
            ((modulesResponse['data'] is Map) ? modulesResponse['data']['modules'] as List? : null) ??
            <dynamic>[];
        setState(() {
          studentModules = list.map((json) => Module.fromJson(json)).toList();
        });
      }

      // Skip fetching all available modules; show only student's modules

      setState(() {
        isLoading = false;
      });
    } catch (e) {
      setState(() {
        isLoading = false;
        errorMessage = 'Failed to load data: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Study Plan - ${widget.student.fullName}'),
        backgroundColor: Colors.green[600],
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadData,
          ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text(
              'Loading your study plan...',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey,
              ),
            ),
          ],
        ),
      );
    }

    if (errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.error_outline,
              size: 64,
              color: Colors.red,
            ),
            SizedBox(height: 16),
            const Text(
              'Error loading study plan',
              style: TextStyle(
                fontSize: 18,
                color: Colors.red,
              ),
            ),
            SizedBox(height: 8),
            Text(
              errorMessage!,
              style: const TextStyle(
                fontSize: 14,
                color: Colors.grey,
              ),
              textAlign: TextAlign.center,
            ),
            SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadData,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Student Info Card
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Student Information',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  SizedBox(height: 12),
                  _buildInfoRow('Student Number', widget.student.studentNumber),
                  _buildInfoRow('Full Name', widget.student.fullName ?? 'N/A'),
                  _buildInfoRow('Email', widget.student.email ?? 'N/A'),
                  if (widget.student.programme != null)
                    _buildInfoRow('Programme', widget.student.programme!),
                  if (widget.student.year != null)
                    _buildInfoRow('Year Level', widget.student.year!),
                ],
              ),
            ),
          ),
          
          SizedBox(height: 24),
          
          // Current Modules
          Text(
            'Your Current Modules (${studentModules.length})',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          SizedBox(height: 16),
          
          if (studentModules.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Center(
                  child: Text(
                    'No modules assigned yet. Contact your administrator.',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.grey,
                    ),
                  ),
                ),
              ),
            )
          else
            ...studentModules.map((module) => _buildModuleCard(module, true)),
          
          // Removed Available Modules section per request
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              '$label:',
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                color: Colors.grey,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 16,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildModuleCard(Module module, bool isAssigned) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      color: isAssigned ? Colors.green[50] : Colors.grey[50],
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        leading: Container(
          width: 50,
          height: 50,
          decoration: BoxDecoration(
            color: isAssigned ? Colors.green[100] : Colors.grey[200],
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(
            isAssigned ? Icons.check_circle : Icons.school,
            color: isAssigned ? Colors.green : Colors.grey,
            size: 24,
          ),
        ),
        title: Text(
          module.moduleName,
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: isAssigned ? Colors.black87 : Colors.grey[700],
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(height: 4),
            Text(
              'Code: ${module.moduleCode}',
              style: TextStyle(
                color: isAssigned ? Colors.black54 : Colors.grey[600],
              ),
            ),
            if (module.semester != null)
              Text(
                'Semester: ${module.semester}',
                style: TextStyle(
                  color: isAssigned ? Colors.black54 : Colors.grey[600],
                ),
              ),
            if (module.credits != null)
              Text(
                'Credits: ${module.credits}',
                style: TextStyle(
                  color: isAssigned ? Colors.black54 : Colors.grey[600],
                ),
              ),
          ],
        ),
        trailing: isAssigned
            ? const Chip(
                label: Text('Assigned'),
                backgroundColor: Colors.green,
                labelStyle: TextStyle(color: Colors.white),
              )
            : const Chip(
                label: Text('Available'),
                backgroundColor: Colors.grey,
                labelStyle: TextStyle(color: Colors.white),
              ),
      ),
    );
  }
}







