'use client';

import { FormEvent, useEffect, useState } from 'react';
import { AppShell, ErrorScreen, LoadingScreen } from '@/components/app-shell';
import { apiRequest, currentUser, User } from '@/lib/api';
import { TaskDetailPanel } from '@/components/task-detail-panel';

type DomainItem = { id: string; name?: string; title?: string; code?: string; task_number?: string; status?: { id: string; name: string; slug: string; color?: string; is_closed?: boolean } | string; priority?: { name: string }; project?: { name: string }; project_id?: string; due_at?: string; due_date?: string; progress?: number; tasks_count?: number; description?: string };
type TimeEntry = { id: string; is_running: boolean; task?: { id: string; title: string } };
type Props = { mode: 'projects' | 'tasks' | 'board' | 'calendar' | 'reports' };
const headings = { projects: ['Projects', 'Plan and track work across your organization.'], tasks: ['Tasks', 'Create and follow up on the work that matters.'], board: ['Task board', 'Move work forward by updating its status.'], calendar: ['Calendar', 'See upcoming task due dates.'], reports: ['Reports', 'Review task activity and export a CSV report.'] } as const;

export function DomainPage({ mode }: Props) {
  const [user, setUser] = useState<User | null>(null);
  const [items, setItems] = useState<DomainItem[]>([]);
  const [projects, setProjects] = useState<DomainItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [exportUrl, setExportUrl] = useState('');
  const [runningTask, setRunningTask] = useState('');
  const [selectedTaskId, setSelectedTaskId] = useState('');

  async function refresh() {
    const path = mode === 'projects' ? '/projects?per_page=100' : mode === 'reports' ? '/reports/tasks?per_page=100' : '/tasks?per_page=100';
    const result = await apiRequest<{ data: DomainItem[] }>(path);
    setItems(result.data);
    if (mode === 'tasks') {
      const projectResult = await apiRequest<{ data: DomainItem[] }>('/projects?per_page=100');
      setProjects(projectResult.data);
      const entries = await apiRequest<{ data: TimeEntry[] }>('/time-entries?per_page=100');
      setRunningTask(entries.data.find((entry) => entry.is_running)?.task?.id ?? '');
    }
  }

  useEffect(() => {
    currentUser().then(async (activeUser) => { setUser(activeUser); await refresh(); })
      .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Please sign in again.'))
      .finally(() => setLoading(false));
  }, []);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = new FormData(form);
    const payload = mode === 'projects'
      ? { code: String(values.get('code')), name: String(values.get('name')), description: String(values.get('description') || '') }
      : { project_id: String(values.get('project_id')), title: String(values.get('title')), due_at: String(values.get('due_at') || '') || null };
    setBusy(true); setError(''); setNotice('');
    try { await apiRequest(mode === 'projects' ? '/projects' : '/tasks', { method: 'POST', body: JSON.stringify(payload) }); form.reset(); await refresh(); setNotice('Saved successfully.'); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Could not save this item.'); }
    finally { setBusy(false); }
  }

  async function moveTask(task: DomainItem, statusId: string) {
    try { await apiRequest(`/tasks/${task.id}/status`, { method: 'PATCH', body: JSON.stringify({ status_id: statusId }) }); await refresh(); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Task status could not be updated.'); }
  }

  async function toggleTimer(task: DomainItem) {
    setError('');
    try {
      await apiRequest(`/tasks/${task.id}/timer/${runningTask === task.id ? 'stop' : 'start'}`, { method: 'POST', body: JSON.stringify({}) });
      await refresh();
      setNotice(runningTask === task.id ? 'Timer stopped.' : `Timer started for ${task.title}.`);
    } catch (caught) { setError(caught instanceof Error ? caught.message : 'Timer could not be updated.'); }
  }

  async function exportCsv() {
    try { const response = await fetch('/api/v1/reports/tasks?export=1', { credentials: 'include', headers: { Accept: 'text/csv' } }); if (!response.ok) throw new Error('Report export failed.'); const url = URL.createObjectURL(await response.blob()); setExportUrl(url); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Report export failed.'); }
  }

  if (loading) return <LoadingScreen />;
  if (!user) return <ErrorScreen message={error || 'Please sign in to continue.'} />;
  const [title, description] = headings[mode];
  const statusColumns = [...new Map(items.filter((item) => typeof item.status === 'object' && item.status).map((item) => [(item.status as { id: string }).id, item.status as { id: string; name: string; slug: string; color?: string; is_closed?: boolean }])).values()];
  const dueItems = [...items].filter((item) => item.due_at || item.due_date).sort((a, b) => String(a.due_at ?? a.due_date).localeCompare(String(b.due_at ?? b.due_date)));

  return <AppShell user={user}><div className="fade-in">
    <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="mb-2 text-sm font-semibold text-[#4267d5]">WORKSPACE</p><h1 className="text-3xl font-semibold tracking-tight text-[#14243a]">{title}</h1><p className="mt-2 text-sm text-[#738095]">{description}</p></div>{mode === 'reports' && <div className="flex gap-2"><button onClick={exportCsv} className="rounded-lg bg-[#4267d5] px-4 py-2.5 text-sm font-semibold text-white">Export CSV</button>{exportUrl && <a href={exportUrl} download="tasks-report.csv" className="rounded-lg border px-4 py-2.5 text-sm font-semibold">Download</a>}</div>}</div>
    {error && <div role="alert" className="mt-6 rounded-xl border border-[#f3d5d2] bg-[#fff7f6] px-4 py-3 text-sm text-[#a32920]">{error}</div>}{notice && <div role="status" className="mt-6 rounded-xl bg-[#edfaf3] px-4 py-3 text-sm text-[#16633f]">{notice}</div>}
    {(mode === 'projects' || mode === 'tasks') && <form onSubmit={create} className="mt-7 flex flex-wrap items-end gap-3 rounded-2xl border border-[#e8ecf2] bg-white p-5 shadow-sm">
      {mode === 'projects' ? <><label className="text-xs font-semibold text-[#526176]">Project code<input name="code" required maxLength={50} className="app-input mt-1.5" placeholder="WEB-01" /></label><label className="text-xs font-semibold text-[#526176]">Project name<input name="name" required maxLength={180} className="app-input mt-1.5" placeholder="Website refresh" /></label></> : <><label className="min-w-48 text-xs font-semibold text-[#526176]">Project<select name="project_id" required className="app-input mt-1.5"><option value="">Choose project</option>{projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</select></label><label className="min-w-56 text-xs font-semibold text-[#526176]">Task title<input name="title" required maxLength={220} className="app-input mt-1.5" placeholder="Prepare launch checklist" /></label><label className="text-xs font-semibold text-[#526176]">Due date<input name="due_at" type="date" className="app-input mt-1.5" /></label></>}
      <button disabled={busy} className="h-[42px] rounded-lg bg-[#4267d5] px-5 text-sm font-semibold text-white disabled:opacity-60">{busy ? 'Saving…' : mode === 'projects' ? 'Create project' : 'Create task'}</button>
    </form>}
    {mode === 'board' ? <div className="mt-7 grid gap-4 lg:grid-cols-3">{statusColumns.map((status) => <section key={status.id} className="min-h-48 rounded-2xl bg-[#eef1f6] p-4"><h2 className="mb-3 flex items-center justify-between font-semibold text-[#34435a]">{status.name}<span className="rounded-full bg-white px-2 py-0.5 text-xs">{items.filter((item) => typeof item.status === 'object' && item.status?.id === status.id).length}</span></h2>{items.filter((item) => typeof item.status === 'object' && item.status?.id === status.id).map((task) => <article key={task.id} className="mb-3 rounded-xl border border-[#e8ecf2] bg-white p-4 shadow-sm"><p className="text-[11px] font-semibold text-[#8a96a7]">{task.task_number} · {task.project?.name}</p><button onClick={() => setSelectedTaskId(task.id)} className="mt-1 text-left font-semibold text-[#26364c] hover:text-[#4267d5]">{task.title}</button><div className="mt-3 flex items-center justify-between text-xs text-[#738095]"><span>{task.priority?.name ?? 'No priority'}</span><select aria-label={`Change status for ${task.title}`} className="max-w-32 rounded-md border border-[#e3e8ef] bg-white px-2 py-1" value={typeof task.status === 'object' ? task.status.id : ''} onChange={(event) => moveTask(task, event.target.value)}>{statusColumns.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select></div></article>)}</section>)}</div> : mode === 'calendar' ? <section className="mt-7 overflow-hidden rounded-2xl border border-[#e8ecf2] bg-white"><div className="border-b p-5 font-semibold text-[#26364c]">Upcoming task dates</div>{dueItems.length ? dueItems.map((task) => <article key={task.id} className="flex flex-wrap items-center justify-between gap-3 border-b border-[#edf0f4] px-5 py-4 last:border-0"><button onClick={() => setSelectedTaskId(task.id)} className="text-left"><div className="font-semibold text-[#34435a]">{task.title}</div><div className="mt-1 text-xs text-[#8792a2]">{task.project?.name ?? task.task_number}</div></button><time className="rounded-lg bg-[#f1f4fd] px-3 py-2 text-sm font-semibold text-[#4267d5]">{new Date(String(task.due_at ?? task.due_date)).toLocaleDateString()}</time></article>) : <p className="p-8 text-center text-sm text-[#8792a2]">No scheduled tasks yet.</p>}</section> : <section className="mt-7 overflow-x-auto rounded-2xl border border-[#e8ecf2] bg-white"><table className="w-full min-w-[680px] text-left text-sm"><thead className="bg-[#fafbfd] text-xs uppercase tracking-wide text-[#8a96a7]"><tr><th className="px-5 py-3">{mode === 'projects' ? 'Project' : 'Task'}</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Priority</th><th className="px-5 py-3">Due</th><th className="px-5 py-3">Progress</th>{mode === 'tasks' && <th className="px-5 py-3">Timer</th>}</tr></thead><tbody className="divide-y divide-[#edf0f4]">{items.map((item) => <tr key={item.id}><td className="px-5 py-4">{mode === 'tasks' ? <button onClick={() => setSelectedTaskId(item.id)} className="text-left font-semibold text-[#34435a] hover:text-[#4267d5]">{item.title}</button> : <div className="font-semibold text-[#34435a]">{item.name}</div>}<div className="mt-1 text-xs text-[#8792a2]">{mode === 'projects' ? item.code : `${item.task_number ?? ''} · ${item.project?.name ?? ''}`}</div></td><td className="px-5 py-4 text-[#526176]">{mode === 'projects' ? String(item.status ?? '—') : typeof item.status === 'object' ? item.status?.name : item.status ?? '—'}</td><td className="px-5 py-4 text-[#718096]">{item.priority?.name ?? '—'}</td><td className="px-5 py-4 text-[#718096]">{item.due_at ? new Date(item.due_at).toLocaleDateString() : item.due_date ?? '—'}</td><td className="px-5 py-4 text-[#718096]">{item.progress ?? 0}%</td>{mode === 'tasks' && <td className="px-5 py-4"><button disabled={Boolean(runningTask && runningTask !== item.id)} onClick={() => toggleTimer(item)} className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${runningTask === item.id ? 'bg-[#fff1f0] text-[#b42318]' : 'bg-[#eef2ff] text-[#4267d5]'} disabled:opacity-50`}>{runningTask === item.id ? 'Stop timer' : 'Start timer'}</button></td>}</tr>)}</tbody></table>{items.length === 0 && <p className="p-8 text-center text-sm text-[#8792a2]">Nothing here yet. Add your first {mode === 'projects' ? 'project' : 'task'} above.</p>}</section>}
    {mode === 'tasks' && selectedTaskId && <TaskDetailPanel taskId={selectedTaskId} currentUserId={user.id} onClose={() => setSelectedTaskId('')} />}
  </div></AppShell>;
}
