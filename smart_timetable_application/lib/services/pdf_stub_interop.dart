/// A stub for non-web platforms (Android, iOS, Desktop).
/// Since we only use pdf.js on the web, this just throws an unsupported error
/// but allows the Dart compiler to succeed building the iOS/Android apps without `dart:js` errors.
Future<Map<String, dynamic>> pickAndExtractPdfText() async {
  throw UnsupportedError("Local PDF extraction using JS is only supported on Web.");
}
