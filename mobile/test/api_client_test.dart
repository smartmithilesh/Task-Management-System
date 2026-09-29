import 'package:flutter_test/flutter_test.dart';
import 'package:task_management_mobile/core/api_client.dart';

void main() {
  test('API exceptions retain their status and display a safe message', () {
    const error = ApiException(401, 'Please sign in again.');

    expect(error.statusCode, 401);
    expect(error.toString(), 'Please sign in again.');
  });
}
