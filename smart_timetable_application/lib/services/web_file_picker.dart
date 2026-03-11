/// Web-only file picker using dart:html directly.
/// Avoids dart:js_interop Promise bridging issues present in file_picker v10+.
// ignore: avoid_web_libraries_in_flutter
import 'dart:async';
// ignore: avoid_web_libraries_in_flutter
import 'dart:html' as html;
import 'dart:typed_data';

/// Shows a native PDF file picker and returns the selected file's bytes and
/// name. Returns null if the user cancels.
Future<({Uint8List bytes, String name})?> pickPdfFile() {
  final completer = Completer<({Uint8List bytes, String name})?>();

  final input = html.FileUploadInputElement()
    ..accept = 'application/pdf,.pdf'
    ..style.display = 'none';

  html.document.body!.append(input);

  // Detect cancel: when window regains focus without onChange having fired,
  // the user dismissed the dialog.
  void onWindowFocus(_) {
    Future.delayed(const Duration(milliseconds: 300), () {
      if (!completer.isCompleted) {
        input.remove();
        completer.complete(null);
      }
    });
  }

  html.window.addEventListener('focus', onWindowFocus);

  input.onChange.listen((_) {
    html.window.removeEventListener('focus', onWindowFocus);

    final files = input.files;
    if (files == null || files.isEmpty) {
      input.remove();
      if (!completer.isCompleted) completer.complete(null);
      return;
    }

    final file = files[0];
    final reader = html.FileReader();

    reader.onLoad.listen((_) {
      input.remove();
      if (completer.isCompleted) return;
      try {
        final result = reader.result;
        Uint8List bytes;
        if (result is Uint8List) {
          bytes = result;
        } else if (result is ByteBuffer) {
          bytes = result.asUint8List();
        } else {
          completer.completeError(
            Exception('Unexpected FileReader result type: ${result.runtimeType}'),
          );
          return;
        }
        completer.complete((bytes: bytes, name: file.name));
      } catch (e) {
        completer.completeError(e);
      }
    });

    reader.onError.listen((_) {
      input.remove();
      if (!completer.isCompleted) {
        completer.completeError(Exception('Failed to read file: ${reader.error}'));
      }
    });

    reader.readAsArrayBuffer(file);
  });

  input.click();
  return completer.future;
}
