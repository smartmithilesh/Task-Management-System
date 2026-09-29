'use client';

import { ChangeEvent, FormEvent, useEffect, useState } from 'react';
import { apiRequest, uploadTaskAttachment } from '@/lib/api';

type ChecklistItem = { id: string; content: string; is_completed: boolean };
type Checklist = { id: string; title: string; items: ChecklistItem[] };
type TaskDetail = { id: string; title: string; task_number: string; description: string | null; checklists: Checklist[]; status?: { name: string }; priority?: { name: string } };
type Comment = { id: string; body: string; edited_at: string | null; author?: { id: string; name: string }; created_at: string };
type Activity = { id: string; actor?: string; action: string; occurred_at: string; properties?: Record<string, unknown> };
type Attachment = { id: string; name: string; size_bytes: number; mime_type: string; created_at: string };

export function TaskDetailPanel({ taskId, currentUserId, onClose }: { taskId: string; currentUserId: string; onClose: () => void }) {
  const [task, setTask] = useState<TaskDetail | null>(null);
  const [comments, setComments] = useState<Comment[]>([]);
  const [activity, setActivity] = useState<Activity[]>([]);
  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function refresh() {
    const [taskResult, commentResult, activityResult, attachmentResult] = await Promise.all([
      apiRequest<{ data: TaskDetail }>(`/tasks/${taskId}`),
      apiRequest<{ data: Comment[] }>(`/tasks/${taskId}/comments`),
      apiRequest<{ data: Activity[] }>(`/tasks/${taskId}/activity?per_page=30`),
      apiRequest<{ data: Attachment[] }>(`/tasks/${taskId}/attachments`),
    ]);
    setTask(taskResult.data);
    setComments(commentResult.data);
    setActivity(activityResult.data);
    setAttachments(attachmentResult.data);
  }

  useEffect(() => { refresh().catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Task details could not be loaded.')).finally(() => setLoading(false)); }, [taskId]);

  async function addComment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const body = new FormData(form).get('body');
    setBusy(true); setError('');
    try { await apiRequest(`/tasks/${taskId}/comments`, { method: 'POST', body: JSON.stringify({ body }) }); form.reset(); await refresh(); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Comment could not be added.'); }
    finally { setBusy(false); }
  }

  async function upload(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) return;
    setBusy(true); setError('');
    try { await uploadTaskAttachment(taskId, file); await refresh(); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Attachment could not be uploaded.'); }
    finally { setBusy(false); event.target.value = ''; }
  }

  async function addChecklist(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const title = new FormData(form).get('checklist_title');
    setBusy(true); setError('');
    try { await apiRequest(`/tasks/${taskId}/checklists`, { method: 'POST', body: JSON.stringify({ title }) }); form.reset(); await refresh(); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Checklist could not be created.'); }
    finally { setBusy(false); }
  }

  async function addChecklistItem(checklistId: string, event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const content = new FormData(form).get('item_content');
    setBusy(true); setError('');
    try { await apiRequest(`/checklists/${checklistId}/items`, { method: 'POST', body: JSON.stringify({ content }) }); form.reset(); await refresh(); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Checklist item could not be added.'); }
    finally { setBusy(false); }
  }

  async function toggleItem(item: ChecklistItem) {
    try { await apiRequest(`/checklist-items/${item.id}`, { method: 'PATCH', body: JSON.stringify({ is_completed: !item.is_completed }) }); await refresh(); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Checklist item could not be updated.'); }
  }

  async function download(attachment: Attachment) {
    try {
      const response = await fetch(`/api/v1/attachments/${attachment.id}/download`, { credentials: 'include', headers: { Accept: '*/*' } });
      if (!response.ok) throw new Error('Attachment download failed.');
      const url = URL.createObjectURL(await response.blob());
      const link = document.createElement('a'); link.href = url; link.download = attachment.name; link.click(); URL.revokeObjectURL(url);
    } catch (caught) { setError(caught instanceof Error ? caught.message : 'Attachment download failed.'); }
  }

  async function deleteComment(comment: Comment) {
    try { await apiRequest(`/comments/${comment.id}`, { method: 'DELETE' }); await refresh(); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Comment could not be deleted.'); }
  }

  return <div className="fixed inset-0 z-40 flex justify-end bg-[#14243a]/40" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
    <section role="dialog" aria-modal="true" aria-label="Task details" className="h-full w-full max-w-3xl overflow-y-auto bg-[#f7f8fb] shadow-2xl">
      <header className="sticky top-0 z-10 flex items-center justify-between border-b border-[#e8ecf2] bg-white px-5 py-4"><div><p className="text-xs font-semibold text-[#4267d5]">{task?.task_number ?? 'TASK'}</p><h2 className="mt-1 font-semibold text-[#26364c]">Task details</h2></div><button onClick={onClose} className="grid h-9 w-9 place-items-center rounded-lg border text-[#526176]" aria-label="Close task details">×</button></header>
      <div className="space-y-5 p-5">{error && <div role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}{loading ? <p className="py-12 text-center text-sm text-[#8792a2]">Loading task details…</p> : task && <>
        <section className="rounded-xl border border-[#e8ecf2] bg-white p-5"><h3 className="text-xl font-semibold text-[#26364c]">{task.title}</h3><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-[#68778b]">{task.description || 'No description provided.'}</p><div className="mt-4 flex gap-2 text-xs"><span className="rounded-full bg-[#f1f4fd] px-3 py-1.5 font-semibold text-[#4267d5]">{task.status?.name ?? 'Open'}</span>{task.priority && <span className="rounded-full bg-[#f4f5f7] px-3 py-1.5 text-[#526176]">{task.priority.name}</span>}</div></section>
        <section className="rounded-xl border border-[#e8ecf2] bg-white p-5"><div className="mb-3 flex items-center justify-between"><h3 className="font-semibold text-[#26364c]">Checklists</h3><form onSubmit={addChecklist} className="flex gap-2"><input name="checklist_title" required maxLength={160} className="h-9 w-40 rounded-lg border px-3 text-sm" placeholder="New checklist"/><button disabled={busy} className="rounded-lg bg-[#4267d5] px-3 text-xs font-semibold text-white">Add</button></form></div>{task.checklists.map((checklist) => <div key={checklist.id} className="border-t py-3"><h4 className="mb-2 text-sm font-semibold text-[#34435a]">{checklist.title}</h4>{checklist.items.map((item) => <label key={item.id} className="flex items-center gap-2 py-1 text-sm text-[#526176]"><input type="checkbox" checked={item.is_completed} onChange={() => toggleItem(item)} />{item.content}</label>)}<form onSubmit={(event) => addChecklistItem(checklist.id, event)} className="mt-2 flex gap-2"><input name="item_content" required maxLength={500} className="h-8 flex-1 rounded-lg border px-3 text-xs" placeholder="Add checklist item"/><button disabled={busy} className="rounded-lg border px-3 text-xs font-semibold text-[#526176]">Add item</button></form></div>)}{!task.checklists.length && <p className="text-xs text-[#8792a2]">No checklists yet.</p>}</section>
        <section className="rounded-xl border border-[#e8ecf2] bg-white p-5"><div className="mb-3 flex items-center justify-between"><h3 className="font-semibold text-[#26364c]">Attachments</h3><label className="cursor-pointer rounded-lg border px-3 py-2 text-xs font-semibold text-[#4267d5]">{busy ? 'Working…' : 'Upload file'}<input type="file" className="sr-only" onChange={upload} disabled={busy} /></label></div>{attachments.length ? attachments.map((file) => <button key={file.id} onClick={() => download(file)} className="flex w-full items-center justify-between border-t py-3 text-left text-sm hover:text-[#4267d5]"><span>{file.name}</span><span className="text-xs text-[#8792a2]">{Math.ceil(file.size_bytes / 1024)} KB · Download</span></button>) : <p className="text-xs text-[#8792a2]">No attachments yet. Files stay private and require task access.</p>}</section>
        <section className="rounded-xl border border-[#e8ecf2] bg-white p-5"><h3 className="mb-3 font-semibold text-[#26364c]">Comments</h3><form onSubmit={addComment} className="mb-4 space-y-2"><textarea name="body" required maxLength={20000} className="app-input min-h-20 py-2" placeholder="Write a comment…"/><button disabled={busy} className="rounded-lg bg-[#4267d5] px-4 py-2 text-xs font-semibold text-white">Post comment</button></form>{comments.map((comment) => <article key={comment.id} className="border-t py-3"><div className="flex items-center justify-between"><span className="text-xs font-semibold text-[#34435a]">{comment.author?.name ?? 'Teammate'}</span><span className="text-[11px] text-[#8792a2]">{new Date(comment.created_at).toLocaleString()}</span></div><p className="mt-2 whitespace-pre-wrap text-sm leading-5 text-[#526176]">{comment.body}</p>{comment.author?.id === currentUserId && <button onClick={() => deleteComment(comment)} className="mt-2 text-[11px] font-semibold text-[#a32920]">Delete</button>}</article>)}{!comments.length && <p className="text-xs text-[#8792a2]">No comments yet.</p>}</section>
        <section className="rounded-xl border border-[#e8ecf2] bg-white p-5"><h3 className="mb-3 font-semibold text-[#26364c]">Activity</h3>{activity.map((event) => <article key={event.id} className="border-t py-3"><p className="text-sm text-[#526176]"><span className="font-semibold text-[#34435a]">{event.actor ?? 'System'}</span> {event.action.replaceAll('_', ' ')}</p><time className="mt-1 block text-[11px] text-[#8792a2]">{new Date(event.occurred_at).toLocaleString()}</time></article>)}{!activity.length && <p className="text-xs text-[#8792a2]">No activity recorded yet.</p>}</section>
      </>}</div>
    </section>
  </div>;
}
