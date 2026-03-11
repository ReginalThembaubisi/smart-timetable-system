/// Stub for non-web platforms (Android, iOS, Desktop).
import 'dart:typed_data';

Future<({Uint8List bytes, String name})?> pickPdfFile() async {
  throw UnsupportedError('PDF file picking is only supported on the web.');
}
