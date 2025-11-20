import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AccessibilityService {
  static const String _fontSizeKey = 'accessibility_font_size';
  static const String _voiceEnabledKey = 'accessibility_voice_enabled';
  static const String _highContrastKey = 'accessibility_high_contrast';
  
  // Font size options
  static const double smallFontSize = 0.8;
  static const double normalFontSize = 1.0;
  static const double largeFontSize = 1.2;
  static const double extraLargeFontSize = 1.4;
  
  // Current settings
  double _currentFontSize = normalFontSize;
  bool _voiceEnabled = false;
  bool _highContrast = false;
  
  // Getters
  double get currentFontSize => _currentFontSize;
  bool get voiceEnabled => _voiceEnabled;
  bool get highContrast => _highContrast;
  
  // Font size options
  List<FontSizeOption> get fontSizeOptions => [
    FontSizeOption('Small', smallFontSize, Icons.text_decrease),
    FontSizeOption('Normal', normalFontSize, Icons.text_fields),
    FontSizeOption('Large', largeFontSize, Icons.text_increase),
    FontSizeOption('Extra Large', extraLargeFontSize, Icons.format_size),
  ];
  
  // Initialize service
  Future<void> initialize() async {
    await _loadSettings();
  }
  
  // Load settings from storage
  Future<void> _loadSettings() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      
      _currentFontSize = prefs.getDouble(_fontSizeKey) ?? normalFontSize;
      _voiceEnabled = prefs.getBool(_voiceEnabledKey) ?? false;
      _highContrast = prefs.getBool(_highContrastKey) ?? false;
    } catch (e) {
      debugPrint('Error loading accessibility settings: $e');
    }
  }
  
  // Save settings to storage
  Future<void> _saveSettings() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      
      await prefs.setDouble(_fontSizeKey, _currentFontSize);
      await prefs.setBool(_voiceEnabledKey, _voiceEnabled);
      await prefs.setBool(_highContrastKey, _highContrast);
    } catch (e) {
      debugPrint('Error saving accessibility settings: $e');
    }
  }
  
  // Set font size
  Future<void> setFontSize(double fontSize) async {
    _currentFontSize = fontSize;
    await _saveSettings();
  }
  
  // Toggle voice support
  Future<void> toggleVoiceSupport() async {
    _voiceEnabled = !_voiceEnabled;
    await _saveSettings();
  }
  
  // Toggle high contrast
  Future<void> toggleHighContrast() async {
    _highContrast = !_highContrast;
    await _saveSettings();
  }
  
  // Get scaled text style
  TextStyle getScaledTextStyle(TextStyle baseStyle) {
    return baseStyle.copyWith(
      fontSize: baseStyle.fontSize != null 
          ? baseStyle.fontSize! * _currentFontSize 
          : null,
    );
  }
  
  // Get high contrast colors
  ColorScheme getHighContrastColorScheme(ColorScheme baseScheme) {
    if (!_highContrast) return baseScheme;
    
    return baseScheme.copyWith(
      primary: Colors.black,
      onPrimary: Colors.white,
      secondary: Colors.blue,
      onSecondary: Colors.white,
      surface: Colors.white,
      onSurface: Colors.black,
      error: Colors.red,
      onError: Colors.white,
    );
  }
  
  // Speak text (placeholder for text-to-speech)
  Future<void> speakText(String text) async {
    if (!_voiceEnabled) return;
    
    // TODO: Implement text-to-speech functionality
    // This would typically use flutter_tts package
    debugPrint('Speaking: $text');
  }
  
  // Get accessibility description for screen readers
  String getAccessibilityDescription(String text, {String? context}) {
    if (context != null) {
      return '$context: $text';
    }
    return text;
  }
}

class FontSizeOption {
  final String label;
  final double multiplier;
  final IconData icon;
  
  FontSizeOption(this.label, this.multiplier, this.icon);
}

























