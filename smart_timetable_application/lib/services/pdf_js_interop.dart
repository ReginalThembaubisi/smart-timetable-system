@JS()
library pdf_js_interop;

import 'dart:js_interop';
import 'dart:convert';

@JS('pickAndExtractPdfText')
external JSPromise<JSString> _pickAndExtractPdfText();

/// Calls the native JS file picker and extractor defined in `web/pdf_js_extractor.js`.
/// Returns a map with 'name' (the filename) and 'text' (the extracted PDF content).
Future<Map<String, dynamic>> pickAndExtractPdfText() async {
  final jsString = await _pickAndExtractPdfText().toDart;
  final dartString = jsString.toDart;
  return jsonDecode(dartString);
}
