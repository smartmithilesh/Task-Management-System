import 'dart:async';
import 'dart:math';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api_client.dart';

class PushRegistration {
  PushRegistration({required this.api, required this.firebaseReady})
      : _storage = const FlutterSecureStorage();

  final ApiClient api;
  final bool firebaseReady;
  final FlutterSecureStorage _storage;
  StreamSubscription<String>? _tokenSubscription;
  bool _enabled = false;

  Future<void> enable() async {
    if (!firebaseReady) return;
    try {
      final preferences = await api.request('/notification-preferences');
      final types = preferences['data'] as List<dynamic>? ?? [];
      final pushEnabled = types.any((type) => (type['channels'] as List<dynamic>? ?? [])
          .any((channel) => channel['channel'] == 'push' && channel['enabled'] == true));
      if (!pushEnabled) return;

      final permission = await FirebaseMessaging.instance.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );
      if (!{
        AuthorizationStatus.authorized,
        AuthorizationStatus.provisional,
      }.contains(permission.authorizationStatus)) {
        return;
      }
      if (defaultTargetPlatform == TargetPlatform.iOS) {
        for (var attempt = 0; attempt < 10; attempt++) {
          if (await FirebaseMessaging.instance.getAPNSToken() != null) break;
          await Future<void>.delayed(const Duration(milliseconds: 500));
        }
        if (await FirebaseMessaging.instance.getAPNSToken() == null) return;
      }

      _enabled = true;
      await _tokenSubscription?.cancel();
      _tokenSubscription = FirebaseMessaging.instance.onTokenRefresh.listen(_registerToken);
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _registerToken(token);
    } catch (_) {
      // Sign-in and task access remain available when push setup is incomplete.
    }
  }

  Future<void> disable() async {
    _enabled = false;
    await _tokenSubscription?.cancel();
    _tokenSubscription = null;
    try {
      final deviceId = await _deviceId();
      await api.request('/push-devices/$deviceId', method: 'DELETE');
    } catch (_) {
      // The backend also removes registrations when FCM reports an unregistered token.
    }
    if (firebaseReady) {
      try {
        await FirebaseMessaging.instance.deleteToken();
      } catch (_) {
        // Local sign-out must still complete if Firebase is unavailable.
      }
    }
  }

  Future<void> _registerToken(String token) async {
    if (!_enabled) return;
    final platform = defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
    try {
      await api.request('/push-devices', method: 'POST', body: {
        'device_id': await _deviceId(),
        'platform': platform,
        'token': token,
      });
    } catch (_) {
      // FCM registration is retried at the next sign-in or token refresh.
    }
  }

  Future<String> _deviceId() async {
    final current = await _storage.read(key: 'push_device_id');
    if (current != null) return current;
    final bytes = List<int>.generate(16, (_) => Random.secure().nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    final hex = bytes.map((value) => value.toRadixString(16).padLeft(2, '0')).join();
    final id = '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
    await _storage.write(key: 'push_device_id', value: id);
    return id;
  }
}
