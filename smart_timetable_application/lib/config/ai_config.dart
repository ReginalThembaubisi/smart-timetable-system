import 'package:flutter_dotenv/flutter_dotenv.dart';

class AIConfig {
  /// Fetches the Gemini API key securely.
  /// 1. Tries to load from a local `.env` file (for local development).
  /// 2. Falls back to `--dart-define=GEMINI_API_KEY` (for CI/CD deployments like Railway).
  static String get geminiApiKey {
    return dotenv.env['GEMINI_API_KEY'] ?? 
           const String.fromEnvironment('GEMINI_API_KEY', defaultValue: '');
  }
}
