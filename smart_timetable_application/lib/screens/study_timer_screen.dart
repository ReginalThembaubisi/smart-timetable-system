import 'package:flutter/material.dart';
import 'dart:async';
import '../models/study_session.dart';
import '../services/study_timer_service.dart';
import '../widgets/glass_card.dart';
import '../widgets/glass_button.dart';
import '../services/notification_service.dart';
import '../services/local_storage_service.dart';
import '../config/app_colors.dart';

class StudyTimerScreen extends StatefulWidget {
  final StudySession studySession;

  const StudyTimerScreen({
    Key? key,
    required this.studySession,
  }) : super(key: key);

  @override
  State<StudyTimerScreen> createState() => _StudyTimerScreenState();
}

class _StudyTimerScreenState extends State<StudyTimerScreen> with TickerProviderStateMixin {
  late StudyTimerService _timerService;
  late AnimationController _progressController;
  late AnimationController _ringColorController;
  late Animation<Color?> _ringColorAnimation;
  
  // Track the previous state to detect transitions
  TimerState _previousState = TimerState.idle;
  TimerState _currentState = TimerState.idle;
  int _remainingSeconds = 0;
  double _progress = 0.0;
  
  final LocalStorageService _storageService = LocalStorageService();
  int _completedSessions = 0;
  int _completedPomodoros = 0;
  int _totalFocusTime = 0;
  int _totalBreakTime = 0;
  
  // Timer duration options
  final List<int> focusDurations = [15, 25, 30, 45, 50];
  final List<int> breakDurations = [3, 5, 10, 15];
  
  int _selectedFocusDuration = 25;
  int _selectedBreakDuration = 5;

  @override
  void initState() {
    super.initState();
    _timerService = StudyTimerService();
    _initializeTimer();
    _loadStats();
    
    _progressController = AnimationController(
      duration: const Duration(milliseconds: 300),
      vsync: this,
    );

    _ringColorController = AnimationController(
      duration: const Duration(milliseconds: 600),
      vsync: this,
    );
    _ringColorAnimation = ColorTween(
      begin: AppColors.primary,
      end: AppColors.primary,
    ).animate(CurvedAnimation(
      parent: _ringColorController,
      curve: Curves.easeInOut,
    ));
    
    // Listen to timer updates
    _timerService.stateStream.listen((state) {
      if (mounted) {
        setState(() {
          _currentState = state;
        });
      }
    });
    
    _timerService.timeStream.listen((seconds) {
      if (mounted) {
        setState(() {
          _remainingSeconds = seconds;
          final totalSeconds = _timerService.totalSeconds;
          _progress = totalSeconds > 0 
              ? (totalSeconds - seconds) / totalSeconds 
              : 0.0;
        });
        
        // Animate progress
        _progressController.animateTo(_progress);
      }
    });
    
    _timerService.progressStream.listen((data) {
      if (mounted) {
        final newState = data['currentState'] as TimerState;

        // Animate ring colour when state changes
        if (newState != _previousState) {
          final fromColor = _ringColorForState(_previousState);
          final toColor = _ringColorForState(newState);
          _ringColorAnimation = ColorTween(begin: fromColor, end: toColor)
              .animate(CurvedAnimation(
                  parent: _ringColorController,
                  curve: Curves.easeInOut));
          _ringColorController.forward(from: 0);
          _previousState = newState;
        }

        setState(() {
          final remainingSeconds = data['remainingSeconds'] as int;
          final totalSeconds = data['totalSeconds'] as int;
          
          // Check if a session just completed naturally
          final isCompleted = data['isCompleted'] as bool? ?? false;
          
          if (isCompleted) {
            if (_timerService.sessionType == TimerState.focus) {
              _completedSessions++;
              _completedPomodoros++;
              _totalFocusTime += (_timerService.totalSeconds ~/ 60);
            } else if (_timerService.sessionType == TimerState.breakTime) {
              _totalBreakTime += (_timerService.totalSeconds ~/ 60);
            }
            _saveStats();
          }
          
          _currentState = newState;
          _remainingSeconds = remainingSeconds;
          _progress = totalSeconds > 0 ? (totalSeconds - remainingSeconds) / totalSeconds : 0.0;
        });
        
        _progressController.animateTo(_progress);
      }
    });
  }

  Future<void> _loadStats() async {
    await _storageService.initialize();
    final stats = _storageService.getPomodoroStats();
    setState(() {
      _completedSessions = stats['sessions'] ?? 0;
      _completedPomodoros = stats['pomodoros'] ?? 0;
      _totalFocusTime = stats['focusTime'] ?? 0;
      _totalBreakTime = stats['breakTime'] ?? 0;
    });
  }

  Future<void> _saveStats() async {
    await _storageService.savePomodoroStats({
      'sessions': _completedSessions,
      'pomodoros': _completedPomodoros,
      'focusTime': _totalFocusTime,
      'breakTime': _totalBreakTime,
    });
  }

  Future<void> _initializeTimer() async {
    await _timerService.initialize();
  }

  @override
  void dispose() {
    _timerService.dispose();
    _progressController.dispose();
    _ringColorController.dispose();
    super.dispose();
  }

  /// Map a [TimerState] to its corresponding ring colour.
  Color _ringColorForState(TimerState state) {
    switch (state) {
      case TimerState.focus:
        return AppColors.primary; // indigo-blue
      case TimerState.breakTime:
        return Colors.green;      // calming green
      case TimerState.paused:
        return Colors.orange;     // warm pause indicator
      default:
        return Colors.white38;    // idle / grey
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: AppColors.backgroundGradient,
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              _buildHeader(),
              Expanded(
                child: _buildTimerContent(),
              ),
              _buildControls(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          GlassButton(
            onPressed: () => Navigator.pop(context),
            padding: const EdgeInsets.all(10),
            borderRadius: 20,
            child: const Icon(
              Icons.arrow_back,
              color: Colors.white,
              size: 20,
            ),
          ),
          
          SizedBox(width: 12),
          
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.studySession.title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  widget.studySession.moduleCode,
                  style: const TextStyle(
                    fontSize: 12,
                    color: Colors.white70,
                  ),
                ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: Colors.white.withValues(alpha: 0.2), width: 1),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.menu_book, color: Colors.white, size: 14),
                              const SizedBox(width: 6),
                              Text(
                                widget.studySession.moduleName ?? widget.studySession.moduleCode,
                                style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(
                            color: _getStateColor().withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: _getStateColor().withValues(alpha: 0.35), width: 1),
                          ),
                          child: Row(
                            children: [
                              Icon(_getStateIcon(), color: _getStateColor(), size: 14),
                              const SizedBox(width: 6),
                              Text(
                                _getStateText(),
                                style: TextStyle(color: _getStateColor(), fontSize: 12, fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTimerContent() {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Timer display
            _buildTimerDisplay(),
            
            SizedBox(height: 24),
            
            // Progress indicator
            _buildProgressIndicator(),
            
            SizedBox(height: 24),
            
            // Session info
            _buildSessionInfo(),
          ],
        ),
      ),
    );
  }

  Widget _buildTimerDisplay() {
    final minutes = _remainingSeconds ~/ 60;
    final seconds = _remainingSeconds % 60;
    
    return Column(
      children: [
        // Timer state indicator
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          decoration: BoxDecoration(
            color: _getStateColor().withValues(alpha: 0.2),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: _getStateColor().withValues(alpha: 0.5)),
          ),
          child: Text(
            _getStateText(),
            style: TextStyle(
              color: _getStateColor(),
              fontSize: 14,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
        
        SizedBox(height: 16),
        
        // Countdown timer - responsive font size
        LayoutBuilder(
          builder: (context, constraints) {
            final fontSize = constraints.maxWidth < 400 ? 48.0 : 64.0;
            return FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}',
                style: TextStyle(
                  fontSize: fontSize,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                  fontFamily: 'monospace',
                ),
              ),
            );
          },
        ),
        
        // Session type
        Text(
          _timerService.sessionType == TimerState.focus ? 'Focus Time' : 'Break Time',
          style: const TextStyle(
            fontSize: 16,
            color: Colors.white70,
          ),
        ),
      ],
    );
  }

  Widget _buildProgressIndicator() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final size = constraints.maxWidth < 400 ? 150.0 : 180.0;
        return SizedBox(
          width: size,
          height: size,
          child: Stack(
            alignment: Alignment.center,
            children: [
              // Background circle
              Container(
                width: size,
                height: size,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.1),
                ),
              ),
              
              // Progress circle – colour is driven by _ringColorAnimation
              AnimatedBuilder(
                animation: Listenable.merge(
                    [_progressController, _ringColorController]),
                builder: (context, child) {
                  final ringColor =
                      _ringColorAnimation.value ?? _getStateColor();
                  return SizedBox(
                    width: size,
                    height: size,
                    child: CircularProgressIndicator(
                      value: _progressController.value,
                      strokeWidth: 6,
                      backgroundColor: ringColor.withValues(alpha: 0.15),
                      valueColor: AlwaysStoppedAnimation<Color>(ringColor),
                    ),
                  );
                },
              ),
              
              // Center content
              Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    _getStateIcon(),
                    size: 40,
                    color: _getStateColor(),
                  ),
                  SizedBox(height: 6),
                  Text(
                    '${(_progress * 100).toInt()}%',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: _getStateColor(),
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildSessionInfo() {
    return GlassCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildInfoItem(
                Icons.timer,
                'Sessions',
                _completedSessions.toString(),
                Colors.blue,
              ),
              _buildInfoItem(
                Icons.auto_awesome,
                'Pomodoros',
                _completedPomodoros.toString(),
                Colors.orange,
              ),
            ],
          ),
          
          SizedBox(height: 12),
          
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildInfoItem(
                Icons.schedule,
                'Focus Time',
                '${_totalFocusTime}min',
                Colors.green,
              ),
              _buildInfoItem(
                Icons.coffee,
                'Break Time',
                '${_totalBreakTime}min',
                Colors.purple,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildInfoItem(IconData icon, String label, String value, Color color) {
    return Column(
      children: [
        Icon(icon, color: color, size: 20),
        SizedBox(height: 6),
        Text(
          value,
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        Text(
          label,
          style: const TextStyle(
            fontSize: 10,
            color: Colors.white70,
          ),
        ),
      ],
    );
  }

  Widget _buildControls() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          // Main control buttons
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              if (_currentState == TimerState.idle) ...[
                // Start focus session button
                Expanded(
                  child: GlassButton(
                    onPressed: () => _startFocusSession(),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    borderRadius: 10,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.play_arrow, color: Colors.white, size: 20),
                        SizedBox(width: 6),
                        const Text(
                          'Start Focus',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                
                SizedBox(width: 12),
                
                // Start break button
                Expanded(
                  child: GlassButton(
                    onPressed: () => _startBreak(),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    borderRadius: 10,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.coffee, color: Colors.white, size: 20),
                        SizedBox(width: 6),
                        const Text(
                          'Start Break',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ] else ...[
                // Timer control buttons
                Expanded(
                  child: GlassButton(
                    onPressed: _currentState == TimerState.paused ? _resumeTimer : _pauseTimer,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    borderRadius: 10,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          _currentState == TimerState.paused ? Icons.play_arrow : Icons.pause,
                          color: Colors.white,
                          size: 20,
                        ),
                        SizedBox(width: 6),
                        Text(
                          _currentState == TimerState.paused ? 'Resume' : 'Pause',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                
                SizedBox(width: 12),
                
                // Stop button
                Expanded(
                  child: GlassButton(
                    onPressed: _stopTimer,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    borderRadius: 10,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.stop, color: Colors.white, size: 20),
                        SizedBox(width: 6),
                        const Text(
                          'Stop',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ],
          ),
          
          SizedBox(height: 16),
          
          // Duration selection (only when idle) - horizontally scrollable
          if (_currentState == TimerState.idle) ...[
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  SizedBox(
                    width: 140,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Focus Duration',
                          style: TextStyle(
                            color: Colors.white70,
                            fontSize: 12,
                          ),
                        ),
                        SizedBox(height: 6),
                        DropdownButton<int>(
                          isExpanded: true,
                          value: _selectedFocusDuration,
                          dropdownColor: Colors.blue[800],
                          style: const TextStyle(color: Colors.white, fontSize: 12),
                          items: focusDurations.map((duration) {
                            return DropdownMenuItem(
                              value: duration,
                              child: Text('${duration} min', style: const TextStyle(fontSize: 12)),
                            );
                          }).toList(),
                          onChanged: (value) {
                            setState(() {
                              _selectedFocusDuration = value ?? 25;
                            });
                          },
                        ),
                      ],
                    ),
                  ),
                  
                  SizedBox(width: 16),
                  
                  SizedBox(
                    width: 140,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Break Duration',
                          style: TextStyle(
                            color: Colors.white70,
                            fontSize: 12,
                          ),
                        ),
                        SizedBox(height: 6),
                        DropdownButton<int>(
                          isExpanded: true,
                          value: _selectedBreakDuration,
                          dropdownColor: Colors.blue[800],
                          style: const TextStyle(color: Colors.white, fontSize: 12),
                          items: breakDurations.map((duration) {
                            return DropdownMenuItem(
                              value: duration,
                              child: Text('${duration} min', style: const TextStyle(fontSize: 12)),
                            );
                          }).toList(),
                          onChanged: (value) {
                            setState(() {
                              _selectedBreakDuration = value ?? 5;
                            });
                          },
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  // Timer control methods
  void _startFocusSession() {
    _timerService.startFocusSession(_selectedFocusDuration);
    
    // Show notification that study session has started
    NotificationService.showStudySessionStartNotification(widget.studySession.title);
  }

  void _startBreak() {
    _timerService.startBreak(_selectedBreakDuration);
  }

  void _pauseTimer() {
    _timerService.pauseTimer();
  }

  void _resumeTimer() {
    _timerService.resumeTimer();
  }

  void _stopTimer() {
    _timerService.stopTimer();
  }

  // Helper methods
  List<Color> _getBackgroundColors() {
    // Determine visuals based on actual underlying session type, not just paused state
    final visualState = _currentState == TimerState.paused ? _timerService.sessionType : _currentState;
    
    switch (visualState) {
      case TimerState.focus:
        return [const Color(0xFF4A90E2), const Color(0xFF3B82F6)];
      case TimerState.breakTime:
        return [const Color(0xFF50E3C2), const Color(0xFF4CAF50)];
      default:
        return [const Color(0xFF4A90E2), const Color(0xFF3B82F6)];
    }
  }

  Color _getStateColor() {
    if (_currentState == TimerState.paused) return Colors.orange;
    
    switch (_timerService.sessionType) {
      case TimerState.focus:
        return Colors.blue;
      case TimerState.breakTime:
        return Colors.green;
      default:
        return Colors.white70;
    }
  }

  String _getStateText() {
    if (_currentState == TimerState.paused) return 'PAUSED';
    if (_currentState == TimerState.idle) return 'READY';
    
    switch (_timerService.sessionType) {
      case TimerState.focus:
        return 'FOCUS';
      case TimerState.breakTime:
        return 'BREAK';
      default:
        return 'READY';
    }
  }

  IconData _getStateIcon() {
    if (_currentState == TimerState.paused) return Icons.pause_circle;
    if (_currentState == TimerState.idle) return Icons.timer;
    
    switch (_timerService.sessionType) {
      case TimerState.focus:
        return Icons.school;
      case TimerState.breakTime:
        return Icons.coffee;
      default:
        return Icons.timer;
    }
  }
}






