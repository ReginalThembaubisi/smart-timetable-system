@JS()
library pdf_js_interop;

import 'dart:js_interop';
import 'dart:typed_data';

@JS('extractPdfTextFromBytes')
external JSPromise<JSString> _extractPdfTextFromBytes(JSUint8Array bytes);

/// Calls the native `extractPdfTextFromBytes` JS function defined in `web/pdf_js_extractor.js`
Future<String> extractPdfText(Uint8List bytes) async {
  final jsString = await _extractPdfTextFromBytes(bytes.toJS).toDart;
  return jsString.toDart;
}
