import 'package:flutter/material.dart';

import '../../core/api_client.dart';

class TaskDetailPage extends StatefulWidget {
  const TaskDetailPage({super.key, required this.api, required this.taskId});
  final ApiClient api;
  final String taskId;

  @override
  State<TaskDetailPage> createState() => _TaskDetailPageState();
}

class _TaskDetailPageState extends State<TaskDetailPage> {
  final _commentController = TextEditingController();
  late Future<void> _loading;
  Map<String, dynamic>? _task;
  List<dynamic> _comments = [];
  bool _timerRunning = false;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loading = _load();
  }

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final results = await Future.wait([
      widget.api.request('/tasks/${widget.taskId}'),
      widget.api.request('/tasks/${widget.taskId}/comments'),
      widget.api.request('/time-entries?task_id=${widget.taskId}'),
    ]);
    _task = results[0]['data'] as Map<String, dynamic>;
    _comments = results[1]['data'] as List<dynamic>? ?? [];
    final entries = results[2]['data'] as List<dynamic>? ?? [];
    _timerRunning = entries.any((entry) => entry['is_running'] == true);
  }

  Future<void> _reload() async {
    setState(() { _error = null; _loading = _load(); });
    try { await _loading; } catch (error) { if (mounted) setState(() => _error = error.toString()); }
    if (mounted) setState(() {});
  }

  Future<void> _toggleTimer() async {
    setState(() { _busy = true; _error = null; });
    try {
      await widget.api.request('/tasks/${widget.taskId}/timer/${_timerRunning ? 'stop' : 'start'}', method: 'POST', body: {});
      await _reload();
    } catch (error) { if (mounted) setState(() => _error = error.toString()); }
    finally { if (mounted) setState(() => _busy = false); }
  }

  Future<void> _postComment() async {
    final body = _commentController.text.trim();
    if (body.isEmpty) return;
    setState(() { _busy = true; _error = null; });
    try {
      await widget.api.request('/tasks/${widget.taskId}/comments', method: 'POST', body: {'body': body});
      _commentController.clear();
      await _reload();
    } catch (error) { if (mounted) setState(() => _error = error.toString()); }
    finally { if (mounted) setState(() => _busy = false); }
  }

  Future<void> _toggleChecklistItem(Map<String, dynamic> item) async {
    try {
      await widget.api.request('/checklist-items/${item['id']}', method: 'PATCH', body: {'is_completed': !(item['is_completed'] == true)});
      await _reload();
    } catch (error) { if (mounted) setState(() => _error = error.toString()); }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(_task?['task_number'] as String? ?? 'Task details')),
        body: FutureBuilder<void>(
          future: _loading,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(snapshot.error.toString())));
            final task = _task ?? {};
            final checklists = task['checklists'] as List<dynamic>? ?? [];
            return ListView(padding: const EdgeInsets.all(16), children: [
              if (_error != null) Padding(padding: const EdgeInsets.only(bottom: 12), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
              Text(task['title'] as String? ?? 'Task', style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 8),
              Text(task['description'] as String? ?? 'No description provided.'),
              const SizedBox(height: 12),
              FilledButton.icon(onPressed: _busy ? null : _toggleTimer, icon: Icon(_timerRunning ? Icons.stop : Icons.play_arrow), label: Text(_timerRunning ? 'Stop timer' : 'Start timer')),
              const SizedBox(height: 20),
              Text('Checklists', style: Theme.of(context).textTheme.titleMedium),
              for (final checklist in checklists)
                Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(checklist['title'] as String? ?? 'Checklist', style: Theme.of(context).textTheme.titleSmall),
                  for (final item in (checklist['items'] as List<dynamic>? ?? []))
                    CheckboxListTile(dense: true, contentPadding: EdgeInsets.zero, value: item['is_completed'] == true, title: Text(item['content'] as String? ?? ''), onChanged: (_) => _toggleChecklistItem(Map<String, dynamic>.from(item as Map))),
                ]))),
              const SizedBox(height: 12),
              Text('Comments', style: Theme.of(context).textTheme.titleMedium),
              TextField(controller: _commentController, minLines: 2, maxLines: 5, decoration: const InputDecoration(hintText: 'Write a comment')),
              Align(alignment: Alignment.centerRight, child: TextButton(onPressed: _busy ? null : _postComment, child: const Text('Post comment'))),
              for (final comment in _comments.reversed)
                ListTile(contentPadding: EdgeInsets.zero, title: Text(comment['author']?['name'] as String? ?? 'Teammate'), subtitle: Text(comment['body'] as String? ?? '')),
            ]);
          },
        ),
      );
}
