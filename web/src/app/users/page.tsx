'use client';

import { FormEvent, useEffect, useState } from 'react';
import { AppShell, ErrorScreen, LoadingScreen } from '@/components/app-shell';
import { createUser, currentUser, listUsers, User, UserInput } from '@/lib/api';

export default function UsersPage() {
  const [user, setUser] = useState<User | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [success, setSuccess] = useState('');

  async function refreshUsers(query = search) {
    const result = await listUsers(query);
    setUsers(result.data);
  }

  useEffect(() => {
    currentUser()
      .then(async (activeUser) => {
        setUser(activeUser);
        await refreshUsers('');
      })
      .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Please sign in again.'))
      .finally(() => setLoading(false));
  }, []);

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = new FormData(form);
    const input: UserInput & { password: string; password_confirmation: string } = {
      name: String(values.get('name') ?? ''),
      email: String(values.get('email') ?? ''),
      phone: String(values.get('phone') ?? '') || undefined,
      employee_number: String(values.get('employee_number') ?? '') || undefined,
      password: String(values.get('password') ?? ''),
      password_confirmation: String(values.get('password_confirmation') ?? ''),
    };

    setBusy(true);
    setError('');
    setSuccess('');
    try {
      await createUser(input);
      form.reset();
      await refreshUsers();
      setSuccess('The new team member has been added.');
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'The account could not be created.');
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <LoadingScreen />;
  if (!user) return <ErrorScreen message={error || 'Please sign in to continue.'} />;

  return (
    <AppShell user={user}>
      <div className="fade-in">
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="mb-2 text-sm font-semibold text-[#4267d5]">YOUR TEAM</p><h1 className="text-3xl font-semibold tracking-tight text-[#14243a]">People</h1><p className="mt-2 text-sm text-[#738095]">Manage the people who can access your workspace.</p></div><div className="rounded-xl border border-[#e8ecf2] bg-white px-4 py-3 text-sm text-[#738095] shadow-sm"><span className="font-semibold text-[#26364c]">{users.length}</span> people listed</div></div>
        {error && <div role="alert" className="mt-6 rounded-xl border border-[#f3d5d2] bg-[#fff7f6] px-4 py-3 text-sm text-[#a32920]">{error}</div>}
        {success && <div role="status" className="mt-6 rounded-xl border border-[#d6efe2] bg-[#f1fbf5] px-4 py-3 text-sm text-[#16633f]">{success}</div>}

        <div className="mt-6 grid items-start gap-5 xl:grid-cols-[1.35fr_.75fr]">
          <section className="overflow-hidden rounded-2xl border border-[#e8ecf2] bg-white shadow-[0_3px_12px_rgba(25,45,75,.025)]">
            <div className="flex flex-col justify-between gap-4 border-b border-[#edf0f4] p-5 sm:flex-row sm:items-center sm:px-6"><div><h2 className="font-semibold text-[#26364c]">Team members</h2><p className="mt-1 text-xs text-[#8792a2]">Only people in your organization are shown.</p></div><form onSubmit={(event) => { event.preventDefault(); refreshUsers(search).catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Search failed.')); }} className="flex gap-2"><input className="app-input min-w-0 sm:w-56" aria-label="Search people" placeholder="Search people" value={search} onChange={(event) => setSearch(event.target.value)} /><button className="rounded-lg border border-[#e3e8ef] px-3 text-sm font-semibold text-[#526176] hover:bg-[#f6f8fb]">Search</button></form></div>
            {users.length > 0 ? <div className="overflow-x-auto"><table className="w-full min-w-[580px] text-left text-sm"><thead className="bg-[#fafbfd] text-xs uppercase tracking-wide text-[#8a96a7]"><tr><th className="px-6 py-3 font-semibold">Name</th><th className="px-6 py-3 font-semibold">Email</th><th className="px-6 py-3 font-semibold">Employee ID</th><th className="px-6 py-3 font-semibold">Status</th></tr></thead><tbody className="divide-y divide-[#edf0f4]">{users.map((person) => <tr key={person.id}><td className="px-6 py-4 font-semibold text-[#34435a]">{person.name}</td><td className="px-6 py-4 text-[#718096]">{person.email}</td><td className="px-6 py-4 text-[#718096]">{person.employee_number || '—'}</td><td className="px-6 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${person.status === 'active' ? 'bg-[#eaf8f1] text-[#16835b]' : 'bg-[#f1f3f6] text-[#788497]'}`}>{person.status}</span></td></tr>)}</tbody></table></div> : <div className="px-6 py-14 text-center"><div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-[#f0f3fa] text-xl text-[#718096]">♧</div><div className="mt-4 font-semibold text-[#34435a]">No team members to show</div><p className="mx-auto mt-1 max-w-sm text-sm leading-6 text-[#8792a2]">If this is your first visit, add your organization before inviting people.</p></div>}
          </section>

          <section className="rounded-2xl border border-[#e8ecf2] bg-white p-5 shadow-[0_3px_12px_rgba(25,45,75,.025)] sm:p-6"><h2 className="font-semibold text-[#26364c]">Add a team member</h2><p className="mt-1 text-xs leading-5 text-[#8792a2]">Create an account in your organization. A strong password is required.</p><form className="mt-5 space-y-4" onSubmit={handleCreate}><label className="block text-sm font-medium text-[#526176]">Full name<input className="app-input mt-2" name="name" required maxLength={255} /></label><label className="block text-sm font-medium text-[#526176]">Email address<input className="app-input mt-2" name="email" type="email" required maxLength={255} /></label><label className="block text-sm font-medium text-[#526176]">Employee ID <span className="font-normal text-[#9aa3b1]">(optional)</span><input className="app-input mt-2" name="employee_number" maxLength={80} /></label><label className="block text-sm font-medium text-[#526176]">Phone <span className="font-normal text-[#9aa3b1]">(optional)</span><input className="app-input mt-2" name="phone" maxLength={40} /></label><label className="block text-sm font-medium text-[#526176]">Temporary password<input className="app-input mt-2" name="password" type="password" minLength={12} autoComplete="new-password" required /></label><label className="block text-sm font-medium text-[#526176]">Confirm password<input className="app-input mt-2" name="password_confirmation" type="password" minLength={12} autoComplete="new-password" required /></label><button type="submit" disabled={busy} className="h-11 w-full rounded-lg bg-[#4267d5] px-4 text-sm font-semibold text-white hover:bg-[#3458c4] disabled:opacity-60">{busy ? 'Adding member…' : 'Add team member'}</button></form></section>
        </div>
      </div>
    </AppShell>
  );
}
