// Stub for non-web platforms (Android, iOS, Desktop).
import 'dart:typed_data';

Future<({Uint8List bytes, String name})?> pickOutlineFile() async {
  throw UnsupportedError('Document picking is only supported on the web.');
}
