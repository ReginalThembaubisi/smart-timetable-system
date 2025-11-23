import 'package:flutter/material.dart';
import 'screens/dashboard_screen.dart';
import 'services/api_service.dart';
import 'services/local_storage_service.dart';
import 'services/notification_service.dart';
import 'models/student.dart';
import 'config/app_theme.dart';
import 'config/app_colors.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Initialize notification service
  await NotificationService.initialize();
  
  runApp(const StudentTimetableApp());
}

class StudentTimetableApp extends StatelessWidget {
  const StudentTimetableApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Smart Timetable',
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: ThemeMode.system, // Automatically follow system theme
      builder: (context, child) {
        return MediaQuery(
          // Ensure text scales properly on mobile
          data: MediaQuery.of(context).copyWith(
            textScaler: MediaQuery.of(context).textScaler.clamp(
              minScaleFactor: 0.8,
              maxScaleFactor: 1.2,
            ),
          ),
          child: child!,
        );
      },
      home: const LoginScreen(),
    );
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _studentNumberController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;
  bool _obscurePassword = true;
  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _checkExistingLogin();
  }

  // Check if user is already logged in locally
  Future<void> _checkExistingLogin() async {
    try {
      final localStorage = LocalStorageService();
      await localStorage.initialize();
      
      if (localStorage.isStudentLoggedIn()) {
        final student = localStorage.getStudent();
        if (student != null) {
          debugPrint('User already logged in locally, redirecting...');
          if (mounted) {
            Navigator.of(context).pushReplacement(
              MaterialPageRoute(
                builder: (context) => DashboardScreen(
                  student: student,
                ),
              ),
            );
          }
        }
      }
    } catch (e) {
      debugPrint('Error checking existing login: $e');
    }
  }

  @override
  void dispose() {
    _studentNumberController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (_formKey.currentState!.validate()) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });

      final studentNumber = _studentNumberController.text.trim();
      final password = _passwordController.text.trim();

      try {
        // Real authentication with PHP backend
        final response = await ApiService.loginStudent(studentNumber, password);
        
        debugPrint('Login response received: $response');
        
        if (response['success'] == true) {
          // Support both {student:{...}} and {data:{student:{...}}} response shapes
          final dynamic directStudent = response['student'];
          final dynamic nestedStudent = (response['data'] is Map) ? response['data']['student'] : null;
          final Map<String, dynamic>? studentJson = (directStudent is Map<String, dynamic>)
              ? directStudent
              : (nestedStudent is Map<String, dynamic> ? nestedStudent : null);
          
          debugPrint('Direct student: $directStudent');
          debugPrint('Nested student: $nestedStudent');
          debugPrint('Student JSON: $studentJson');
          
          if (studentJson == null) {
            debugPrint('ERROR: Student object not found in response. Full response: $response');
            throw Exception('Unexpected response: missing student object. Response: $response');
          }
          
          final student = Student.fromJson(studentJson);
          debugPrint('Student created: ID=${student.studentId}, Number=${student.studentNumber}, Name=${student.fullName}');
          
          // Validate student ID
          if (student.studentId <= 0) {
            debugPrint('ERROR: Invalid student ID: ${student.studentId}');
            throw Exception('Invalid student ID received from server');
          }
          
          // Save student data locally for persistent login
          try {
            final localStorage = LocalStorageService();
            await localStorage.initialize();
            await localStorage.saveStudent(student);
            debugPrint('Login data saved locally');
          } catch (e) {
            debugPrint('Warning: Could not save login data locally: $e');
          }
          
          if (mounted) {
            debugPrint('Navigating to dashboard with student ID: ${student.studentId}');
            Navigator.of(context).pushReplacement(
              MaterialPageRoute(
                builder: (context) => DashboardScreen(
                  student: student,
                ),
              ),
            );
          }
        } else {
          debugPrint('Login failed: ${response['message']}');
          setState(() {
            _errorMessage = response['message'] ?? 'Login failed';
            _isLoading = false;
          });
        }
      } catch (e) {
        setState(() {
          _errorMessage = 'Login failed: $e';
          _isLoading = false;
        });
      } finally {
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenWidth = MediaQuery.of(context).size.width;
    final screenHeight = MediaQuery.of(context).size.height;
    final isMobile = screenWidth < 600;
    final isSmallMobile = screenWidth < 400;
    
    // Responsive values
    final horizontalPadding = isMobile ? (isSmallMobile ? 20.0 : 24.0) : 32.0;
    final iconSize = isMobile ? (isSmallMobile ? 50.0 : 55.0) : 60.0;
    final titleFontSize = isMobile ? (isSmallMobile ? 24.0 : 28.0) : 32.0;
    final subtitleFontSize = isMobile ? 14.0 : 16.0;
    final spacing = isMobile ? 24.0 : 40.0;
    final smallSpacing = isMobile ? 16.0 : 24.0;
    
    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              AppColors.primary,
              AppColors.primaryLight,
              AppColors.accent,
            ],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: EdgeInsets.symmetric(horizontal: horizontalPadding, vertical: 16),
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxWidth: 500,
                  minHeight: screenHeight * 0.8,
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    // Logo/Title
                    Container(
                      padding: EdgeInsets.all(isMobile ? 16 : 20),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.2),
                          width: 1,
                        ),
                      ),
                      child: Icon(
                        Icons.school,
                        size: iconSize,
                        color: Colors.white,
                      ),
                    ),
                    
                    SizedBox(height: spacing),
                    
                    // Title
                    Text(
                      'Student Timetable',
                      style: TextStyle(
                        fontSize: titleFontSize,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    
                    SizedBox(height: 8),
                    
                    Text(
                      'Sign in to view your schedule',
                      style: TextStyle(
                        fontSize: subtitleFontSize,
                        color: Colors.white70,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    
                    SizedBox(height: spacing),
                  
                    // Login Form
                    Container(
                      padding: EdgeInsets.all(isMobile ? 20 : 24),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.2),
                          width: 1,
                        ),
                      ),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          children: [
                            // Student Number
                            TextFormField(
                              controller: _studentNumberController,
                              keyboardType: TextInputType.text,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: 'Student Number',
                                labelStyle: const TextStyle(color: Colors.white70),
                                prefixIcon: const Icon(Icons.person, color: Colors.white70),
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(color: Colors.white30),
                                ),
                                enabledBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(color: Colors.white30),
                                ),
                                focusedBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(color: Colors.white),
                                ),
                                contentPadding: EdgeInsets.symmetric(
                                  horizontal: 16,
                                  vertical: isMobile ? 16 : 20,
                                ),
                              ),
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: isMobile ? 16 : 18,
                              ),
                              validator: (value) {
                                if (value == null || value.trim().isEmpty) {
                                  return 'Please enter your student number';
                                }
                                return null;
                              },
                            ),
                            
                            SizedBox(height: isMobile ? 16 : 20),
                            
                            // Password
                            TextFormField(
                              controller: _passwordController,
                              obscureText: _obscurePassword,
                              keyboardType: TextInputType.visiblePassword,
                              textInputAction: TextInputAction.done,
                              onFieldSubmitted: (_) => _login(),
                              decoration: InputDecoration(
                                labelText: 'Password',
                                labelStyle: const TextStyle(color: Colors.white70),
                                prefixIcon: const Icon(Icons.lock, color: Colors.white70),
                                suffixIcon: IconButton(
                                  icon: Icon(
                                    _obscurePassword ? Icons.visibility : Icons.visibility_off,
                                    color: Colors.white70,
                                  ),
                                  onPressed: () {
                                    setState(() {
                                      _obscurePassword = !_obscurePassword;
                                    });
                                  },
                                ),
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(color: Colors.white30),
                                ),
                                enabledBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(color: Colors.white30),
                                ),
                                focusedBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(color: Colors.white),
                                ),
                                contentPadding: EdgeInsets.symmetric(
                                  horizontal: 16,
                                  vertical: isMobile ? 16 : 20,
                                ),
                              ),
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: isMobile ? 16 : 18,
                              ),
                              validator: (value) {
                                if (value == null || value.trim().isEmpty) {
                                  return 'Please enter your password';
                                }
                                return null;
                              },
                            ),
                            
                            if (_errorMessage != null) ...[
                              SizedBox(height: isMobile ? 12 : 16),
                              Container(
                                padding: EdgeInsets.all(isMobile ? 10 : 12),
                                decoration: BoxDecoration(
                                  color: Colors.red.withValues(alpha: 0.2),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: Colors.red.withValues(alpha: 0.3)),
                                ),
                                child: Text(
                                  _errorMessage!,
                                  style: TextStyle(
                                    color: Colors.red,
                                    fontSize: isMobile ? 13 : 14,
                                  ),
                                  textAlign: TextAlign.center,
                                ),
                              ),
                            ],
                            
                            SizedBox(height: smallSpacing),
                            
                            // Login Button
                            SizedBox(
                              width: double.infinity,
                              height: isMobile ? 48 : 50,
                              child: ElevatedButton(
                                onPressed: _isLoading ? null : _login,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: AppColors.surface,
                                  foregroundColor: AppColors.primary,
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                ),
                                child: _isLoading
                                    ? SizedBox(
                                        height: isMobile ? 20 : 24,
                                        width: isMobile ? 20 : 24,
                                        child: const CircularProgressIndicator(strokeWidth: 2),
                                      )
                                    : Text(
                                        'Sign In',
                                        style: TextStyle(
                                          fontSize: isMobile ? 16 : 18,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    
                    SizedBox(height: isMobile ? 16 : 20),
                    
                    // Help Text
                    Container(
                      padding: EdgeInsets.all(isMobile ? 12 : 16),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.2),
                          width: 1,
                        ),
                      ),
                      child: Column(
                        children: [
                          Text(
                            'Student Login',
                            style: TextStyle(
                              color: Colors.white70,
                              fontSize: isMobile ? 13 : 14,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          SizedBox(height: isMobile ? 6 : 8),
                          Text(
                            'Enter your student number and password to access your timetable',
                            style: TextStyle(
                              color: Colors.white60,
                              fontSize: isMobile ? 11 : 12,
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    ),
                    
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
