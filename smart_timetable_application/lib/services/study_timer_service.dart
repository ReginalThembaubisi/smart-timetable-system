import 'package:flutter/foundation.dart';
import 'notification_service.dart';
import 'dart:async';

enum TimerState { idle, focus, breakTime, paused }

class StudyTimerService {
  Timer? _timer;
  int _remainingSeconds = 0;
  TimerState _currentState = TimerState.idle;
  int _focusDuration = 25 * 60; // 25 minutes
  int _breakDuration = 5 * 60; // 5 minutes
  
  // Stream controllers for real-time updates
  final StreamController<TimerState> _stateController = StreamController<TimerState>.broadcast();
  final StreamController<int> _timeController = StreamController<int>.broadcast();
  final StreamController<Map<String, dynamic>> _progressController = StreamController<Map<String, dynamic>>.broadcast();
  
  TimerState get currentState => _currentState;
  int get remainingSeconds => _remainingSeconds;
  String get remainingTime {
    final minutes = _remainingSeconds ~/ 60;
    final seconds = _remainingSeconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  void startFocusTimer() {
    _remainingSeconds = _focusDuration;
    _currentState = TimerState.focus;
    debugPrint('Starting focus timer: $_remainingSeconds seconds ($_focusDuration total)');
    _startTimer();
  }

  void startBreakTimer() {
    _remainingSeconds = _breakDuration;
    _currentState = TimerState.breakTime;
    debugPrint('Starting break timer: $_remainingSeconds seconds ($_breakDuration total)');
    _startTimer();
  }

  void pauseTimer() {
    if (_currentState == TimerState.focus || _currentState == TimerState.breakTime) {
      final previousState = _currentState;
      _currentState = TimerState.paused;
      _timer?.cancel();
      _updateStreams();
    }
  }

  void resumeTimer() {
    if (_currentState == TimerState.paused) {
      // Resume to the appropriate state based on what was running before pause
      // We'll determine this based on the remaining time and duration
      if (_remainingSeconds > 0) {
        // If we have significant time left, it's likely a focus session
        _currentState = _remainingSeconds > _breakDuration ? TimerState.focus : TimerState.breakTime;
        _startTimer();
      }
    }
  }

  void stopTimer() {
    _timer?.cancel();
    _currentState = TimerState.idle;
    _remainingSeconds = 0;
    _updateStreams();
  }

  void _startTimer() {
    _timer?.cancel();
    debugPrint('Starting timer with $_remainingSeconds seconds remaining');
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_remainingSeconds > 0) {
        _remainingSeconds--;
        if (_remainingSeconds % 10 == 0) {
          debugPrint('Timer tick: $_remainingSeconds seconds remaining');
        }
        _updateStreams();
      } else {
        _timer?.cancel();
        final wasBreak = _currentState == TimerState.breakTime;
        _currentState = TimerState.idle;
        debugPrint('Timer completed - session finished!');
        
        // Show notification when break completes
        if (wasBreak) {
          NotificationService.showPomodoroBreakNotification();
        }
        
        _updateStreams();
      }
    });
    _updateStreams();
  }
  
  void _updateStreams() {
    _stateController.add(_currentState);
    _timeController.add(_remainingSeconds);
    _progressController.add({
      'currentState': _currentState,
      'remainingSeconds': _remainingSeconds,
      'totalSeconds': _currentState == TimerState.focus ? _focusDuration : _breakDuration,
    });
  }

  void dispose() {
    _timer?.cancel();
    _stateController.close();
    _timeController.close();
    _progressController.close();
  }

  // Streams for UI updates
  Stream<TimerState> get stateStream => _stateController.stream;
  Stream<int> get timeStream => _timeController.stream;
  Stream<Map<String, dynamic>> get progressStream => _progressController.stream;

  Future<void> initialize() async {
    // Initialize the timer service
    debugPrint('StudyTimerService initialized');
  }

  void startFocusSession(int durationMinutes) {
    _focusDuration = durationMinutes * 60; // Convert minutes to seconds
    startFocusTimer();
  }

  void startBreak(int durationMinutes) {
    _breakDuration = durationMinutes * 60; // Convert minutes to seconds
    startBreakTimer();
  }
  
  int get totalSeconds => _currentState == TimerState.focus ? _focusDuration : _breakDuration;
}
