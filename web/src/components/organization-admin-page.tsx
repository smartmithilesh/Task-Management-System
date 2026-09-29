'use client';

import { FormEvent, useEffect, useState } from 'react';
import { AppShell, ErrorScreen, LoadingScreen } from '@/components/app-shell';
import { apiRequest, currentUser, User } from '@/lib/api';

type Entry = { id: string; name: string; slug?: string; description?: string; is_system?: boolean; users_count?: number; teams_count?: number; permissions?: Permission[] };
type Permission = { id: string; name: string; label: string; group: string };
type Mode = 'departments' | 'teams' | 'roles';
const copy = { departments: ['Departments', 'Organize people and teams by function.'], teams: ['Teams', 'Create working groups for your organization.'], roles: ['Roles & access', 'Define reusable roles using granular permissions.'] } as const;

export function OrganizationAdminPage({ mode }: { mode: Mode }) {
  const [user, setUser] = useState<User | null>(null);
  const [entries, setEntries] = useState<Entry[]>([]);
  const [permissions, setPermissions] = useState<Permission[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  async function refresh() {
    const path = mode === 'departments' ? '/departments' : mode === 'teams' ? '/teams' : '/roles';
    const result = await apiRequest<{ data: Entry[] }>(path);
    setEntries(result.data);
    if (mode === 'roles') setPermissions((await apiRequest<{ data: Permission[] }>('/permissions')).data);
  }

  useEffect(() => {
    currentUser().then(async (active) => { setUser(active); await refresh(); })
      .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Please sign in again.'))
      .finally(() => setLoading(false));
  }, []);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = new FormData(form);
    const payload: Record<string, unknown> = { name: String(values.get('name') ?? ''), description: String(values.get('description') ?? '') || undefined };
    if (mode === 'roles') payload.permission_ids = values.getAll('permission_ids').map(String);
    setBusy(true); setError(''); setNotice('');
    try {
      await apiRequest(mode === 'departments' ? '/departments' : mode === 'teams' ? '/teams' : '/roles', { method: 'POST', body: JSON.stringify(payload) });
      form.reset(); await refresh(); setNotice(`${mode === 'roles' ? 'Role' : mode === 'teams' ? 'Team' : 'Department'} created.`);
    } catch (caught) { setError(caught instanceof Error ? caught.message : 'The item could not be created.'); }
    finally { setBusy(false); }
  }

  if (loading) return <LoadingScreen />;
  if (!user) return <ErrorScreen message={error || 'Please sign in to continue.'} />;
  const [title, subtitle] = copy[mode];
  const groups = [...new Set(permissions.map((permission) => permission.group))];
  return <AppShell user={user}><div className="fade-in"><p className="mb-2 text-sm font-semibold text-[#4267d5]">WORKSPACE SETTINGS</p><h1 className="text-3xl font-semibold tracking-tight text-[#14243a]">{title}</h1><p className="mt-2 text-sm text-[#738095]">{subtitle}</p>
    {error && <div role="alert" className="mt-6 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}{notice && <div role="status" className="mt-6 rounded-xl bg-[#edfaf3] px-4 py-3 text-sm text-[#16633f]">{notice}</div>}
    <div className="mt-7 grid items-start gap-5 xl:grid-cols-[1.2fr_.8fr]">
      <section className="overflow-hidden rounded-2xl border border-[#e8ecf2] bg-white"><div className="border-b px-5 py-4 font-semibold text-[#26364c]">{title}</div>{entries.length ? <ul className="divide-y divide-[#edf0f4]">{entries.map((entry) => <li key={entry.id} className="flex items-center justify-between gap-4 px-5 py-4"><div><div className="font-semibold text-[#34435a]">{entry.name}{entry.is_system && <span className="ml-2 rounded-full bg-[#f1f4fd] px-2 py-1 text-[10px] font-semibold text-[#4267d5]">SYSTEM</span>}</div><div className="mt-1 text-xs text-[#8792a2]">{mode === 'roles' ? `${entry.permissions?.length ?? 0} permissions · ${entry.users_count ?? 0} people` : mode === 'departments' ? `${entry.users_count ?? 0} people · ${entry.teams_count ?? 0} teams` : `${entry.users_count ?? 0} people`}</div></div>{entry.description && <p className="max-w-xs text-xs text-[#8792a2]">{entry.description}</p>}</li>)}</ul> : <p className="p-8 text-center text-sm text-[#8792a2]">No {title.toLowerCase()} yet.</p>}</section>
      <section className="rounded-2xl border border-[#e8ecf2] bg-white p-5"><h2 className="font-semibold text-[#26364c]">Create {mode === 'roles' ? 'a role' : mode === 'teams' ? 'a team' : 'a department'}</h2><form onSubmit={create} className="mt-4 space-y-4"><label className="block text-sm font-medium text-[#526176]">Name<input className="app-input mt-2" name="name" required maxLength={120} /></label><label className="block text-sm font-medium text-[#526176]">Description <span className="font-normal text-[#9aa3b1]">(optional)</span><textarea className="app-input mt-2 min-h-20 py-2" name="description" maxLength={5000} /></label>{mode === 'roles' && <fieldset><legend className="mb-2 text-sm font-medium text-[#526176]">Permissions</legend><div className="max-h-64 space-y-3 overflow-auto rounded-lg border border-[#e8ecf2] p-3">{groups.map((group) => <div key={group}><div className="mb-1 text-xs font-semibold uppercase text-[#8792a2]">{group}</div>{permissions.filter((permission) => permission.group === group).map((permission) => <label key={permission.id} className="flex items-center gap-2 py-1 text-sm text-[#526176]"><input type="checkbox" name="permission_ids" value={permission.id} />{permission.label}</label>)}</div>)}</div></fieldset>}<button disabled={busy} className="h-11 w-full rounded-lg bg-[#4267d5] px-4 text-sm font-semibold text-white disabled:opacity-60">{busy ? 'Creating…' : 'Create'}</button></form></section>
    </div>
  </div></AppShell>;
}
