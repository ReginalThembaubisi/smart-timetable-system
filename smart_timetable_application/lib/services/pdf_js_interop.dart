// lib/services/pdf_js_interop.dart
// Bridges Dart to the JS function in web/pdf_js_extractor.js.
// The heavy lifting (PDF parsing + Gemini API call) all happens in JS.
// Only a small JSON payload (~2KB) ever enters WASM/Dart memory.

@JS()
library pdf_js_interop;

import 'dart:js_interop';
import 'dart:convert';

@JS('pickAndExtractAndAnalyzePdf')
external JSPromise<JSString> _pickAndExtractAndAnalyzePdf(
  JSString geminiApiKey,
  JSString moduleCode,
);

/// Picks a PDF, extracts text (in JS), calls Gemini (in JS),
/// and returns the result. Nothing large ever enters Dart memory.
///
/// Returns a map with:
///   - 'name': the filename (String)
///   - 'events': raw JSON string from Gemini (parse it yourself)
Future<Map<String, dynamic>> pickAndExtractAndAnalyzePdf({
  required String geminiApiKey,
  required String moduleCode,
}) async {
  final jsResult = await _pickAndExtractAndAnalyzePdf(
    geminiApiKey.toJS,
    moduleCode.toJS,
  ).toDart;

  final dartString = jsResult.toDart;
  return jsonDecode(dartString) as Map<String, dynamic>;
}

// Keep the old name as an alias so nothing else breaks if it's imported elsewhere
Future<Map<String, dynamic>> pickAndExtractPdfText() async {
  throw UnsupportedError(
    'Use pickAndExtractAndAnalyzePdf() instead. '
    'The old function is removed to fix memory crashes.',
  );
}
