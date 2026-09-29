import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import 'core/api_client.dart';
import 'core/push_registration.dart';
import 'features/tasks/task_detail_page.dart';

const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000',
);

final appScaffoldMessengerKey = GlobalKey<ScaffoldMessengerState>();

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  var firebaseReady = false;
  try {
    await Firebase.initializeApp();
    firebaseReady = true;
  } catch (_) {
    // Firebase is optional until the app has platform configuration.
  }
  final api = ApiClient(baseUrl: apiBaseUrl);
  runApp(TaskManagementApp(api: api, pushRegistration: PushRegistration(api: api, firebaseReady: firebaseReady)));
}

class TaskManagementApp extends StatelessWidget {
  const TaskManagementApp({super.key, required this.api, required this.pushRegistration});
  final ApiClient api;
  final PushRegistration pushRegistration;

  @override
  Widget build(BuildContext context) => MaterialApp(
        title: 'Taskflow',
        theme: ThemeData(colorSchemeSeed: const Color(0xff4267d5), useMaterial3: true),
        scaffoldMessengerKey: appScaffoldMessengerKey,
        home: AuthGate(api: api, pushRegistration: pushRegistration),
      );
}

class AuthGate extends StatefulWidget {
  const AuthGate({super.key, required this.api, required this.pushRegistration});
  final ApiClient api;
  final PushRegistration pushRegistration;

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  late final Future<bool> _authentication = _checkAuthentication();

  Future<bool> _checkAuthentication() async {
    final authenticated = await widget.api.hasValidToken();
    if (authenticated) unawaited(widget.pushRegistration.enable());
    return authenticated;
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<bool>(
        future: _authentication,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Scaffold(body: Center(child: CircularProgressIndicator()));
          }
          return snapshot.data == true
              ? TaskListPage(api: widget.api, pushRegistration: widget.pushRegistration)
              : SignInPage(api: widget.api, pushRegistration: widget.pushRegistration);
        },
      );
}

class SignInPage extends StatefulWidget {
  const SignInPage({super.key, required this.api, required this.pushRegistration});
  final ApiClient api;
  final PushRegistration pushRegistration;
  @override
  State<SignInPage> createState() => _SignInPageState();
}

class _SignInPageState extends State<SignInPage> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  Future<void> _signIn() async {
    setState(() { _busy = true; _error = null; });
    try {
      final result = await widget.api.request('/auth/login', method: 'POST', body: {
        'email': _email.text.trim(),
        'password': _password.text,
        'device_name': 'Flutter mobile',
      });
      final data = result['data'] as Map<String, dynamic>;
      await widget.api.saveToken(data['token'] as String);
      unawaited(widget.pushRegistration.enable());
      if (mounted) Navigator.of(context).pushReplacement(MaterialPageRoute<void>(builder: (_) => TaskListPage(api: widget.api, pushRegistration: widget.pushRegistration)));
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        body: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Icon(Icons.task_alt, size: 48),
                const SizedBox(height: 16),
                Text('Sign in to Taskflow', style: Theme.of(context).textTheme.headlineSmall),
                const SizedBox(height: 24),
                TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email')),
                TextField(controller: _password, obscureText: true, decoration: const InputDecoration(labelText: 'Password')),
                if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
                const SizedBox(height: 20),
                FilledButton(onPressed: _busy ? null : _signIn, child: Text(_busy ? 'Signing in…' : 'Sign in')),
              ]),
            ),
          ),
        ),
      );
}

class TaskListPage extends StatefulWidget {
  const TaskListPage({super.key, required this.api, required this.pushRegistration});
  final ApiClient api;
  final PushRegistration pushRegistration;
  @override
  State<TaskListPage> createState() => _TaskListPageState();
}

class _TaskListPageState extends State<TaskListPage> {
  late Future<Map<String, dynamic>> _tasks;
  StreamSubscription<RemoteMessage>? _openedMessageSubscription;
  StreamSubscription<RemoteMessage>? _foregroundMessageSubscription;

  @override
  void initState() {
    super.initState();
    _tasks = widget.api.request('/tasks?per_page=50');
    if (widget.pushRegistration.firebaseReady) {
      _openedMessageSubscription = FirebaseMessaging.onMessageOpenedApp.listen(_openPushTask);
      _foregroundMessageSubscription = FirebaseMessaging.onMessage.listen((message) {
        final title = message.notification?.title ?? 'Task update';
        final body = message.notification?.body ?? '';
        appScaffoldMessengerKey.currentState?.showSnackBar(SnackBar(content: Text(body.isEmpty ? title : '$title: $body')));
      });
      FirebaseMessaging.instance.getInitialMessage().then((message) {
        if (message != null && mounted) _openPushTask(message);
      }).catchError((Object _) {});
    }
  }

  @override
  void dispose() {
    _openedMessageSubscription?.cancel();
    _foregroundMessageSubscription?.cancel();
    super.dispose();
  }

  void _openPushTask(RemoteMessage message) {
    final taskId = message.data['task_id'];
    if (!mounted || taskId is! String || taskId.isEmpty) return;
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => TaskDetailPage(api: widget.api, taskId: taskId)));
  }

  Future<void> _signOut() async {
    await widget.pushRegistration.disable();
    try { await widget.api.request('/auth/logout', method: 'POST'); } catch (_) { /* Local credentials are cleared even when the device is offline. */ }
    await widget.api.clearToken();
    if (mounted) Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute<void>(builder: (_) => SignInPage(api: widget.api, pushRegistration: widget.pushRegistration)), (_) => false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('My tasks'), actions: [IconButton(onPressed: _signOut, icon: const Icon(Icons.logout), tooltip: 'Sign out')]),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _tasks,
          builder: (context, snapshot) {
            if (snapshot.hasError) return Center(child: Text(snapshot.error.toString()));
            if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
            final rows = snapshot.data!['data'] as List<dynamic>? ?? [];
            if (rows.isEmpty) return const Center(child: Text('No tasks yet.'));
            return RefreshIndicator(onRefresh: () async => setState(() => _tasks = widget.api.request('/tasks?per_page=50')), child: ListView.builder(itemCount: rows.length, itemBuilder: (context, index) {
              final task = rows[index] as Map<String, dynamic>;
              final status = task['status'] as Map<String, dynamic>?;
              return ListTile(onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => TaskDetailPage(api: widget.api, taskId: task['id'] as String))), title: Text(task['title'] as String? ?? 'Task'), subtitle: Text('${task['task_number'] ?? ''} · ${status?['name'] ?? 'Open'}'), trailing: const Icon(Icons.chevron_right));
            }));
          },
        ),
      );
}
