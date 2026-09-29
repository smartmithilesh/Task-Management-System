import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class ApiClient {
  ApiClient({required this.baseUrl, FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final String baseUrl;
  final FlutterSecureStorage _storage;

  Future<Map<String, dynamic>> request(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? body,
  }) async {
    final token = await _storage.read(key: 'access_token');
    final normalizedMethod = method.toUpperCase();
    final isMutation = !{'GET', 'HEAD', 'OPTIONS'}.contains(normalizedMethod);
    String? csrf;
    if (isMutation && token == null && path != '/auth/csrf-token') {
      final csrfResponse = await request('/auth/csrf-token');
      csrf = (csrfResponse['data'] as Map<String, dynamic>)['csrf_token'] as String?;
    }
    final normalizedBaseUrl = baseUrl.replaceFirst(RegExp(r'/+$'), '');
    final normalizedPath = path.startsWith('/') ? path : '/$path';
    final uri = Uri.parse('$normalizedBaseUrl/api/v1$normalizedPath');
    final headers = <String, String>{'Accept': 'application/json'};
    if (token != null) headers['Authorization'] = 'Bearer $token';
    final cookie = await _storage.read(key: 'session_cookie');
    if (cookie != null) headers['Cookie'] = cookie;
    if (csrf != null) headers['X-CSRF-TOKEN'] = csrf;
    if (body != null) headers['Content-Type'] = 'application/json';
    final httpRequest = http.Request(method, uri)
      ..headers.addAll(headers)
      ..body = body == null ? '' : jsonEncode(body);
    final streamed = await httpRequest.send().timeout(const Duration(seconds: 20));
    final response = await http.Response.fromStream(streamed);
    final setCookie = response.headers['set-cookie'];
    if (setCookie != null) await _storage.write(key: 'session_cookie', value: setCookie.split(';').first);
    final decoded = response.body.isEmpty ? <String, dynamic>{} : jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(response.statusCode, decoded['message'] as String? ?? 'Request failed.');
    }
    return decoded;
  }

  Future<void> saveToken(String token) => _storage.write(key: 'access_token', value: token);

  Future<bool> hasValidToken() async {
    if (await _storage.read(key: 'access_token') == null) return false;
    try {
      await request('/auth/user');
      return true;
    } on ApiException catch (error) {
      if (error.statusCode == 401) await clearToken();
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<void> clearToken() async {
    await _storage.delete(key: 'access_token');
    await _storage.delete(key: 'session_cookie');
  }
}

class ApiException implements Exception {
  const ApiException(this.statusCode, this.message);
  final int statusCode;
  final String message;
  @override
  String toString() => message;
}
