// Basic smoke test for Smart Timetable Application
import 'package:flutter_test/flutter_test.dart';
import 'package:smart_timetable_application/main.dart';

void main() {
  testWidgets('App launches without crashing', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(const StudentTimetableApp());

    // Verify the login screen renders
    expect(find.text('Smart Timetable'), findsOneWidget);
    expect(find.text('Sign In'), findsOneWidget);
  });
}
