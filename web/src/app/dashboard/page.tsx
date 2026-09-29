'use client';

import { FormEvent, useEffect, useState } from 'react';
import { AppShell, ErrorScreen, LoadingScreen } from '@/components/app-shell';
import { createOrganization, currentUser, updateProfile, User } from '@/lib/api';
import { apiRequest } from '@/lib/api';

type DashboardSummary = { counts: { projects: number; open_tasks: number; overdue_tasks: number; completed_this_week: number } };

export default function DashboardPage() {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const [saving, setSaving] = useState(false);
  const [settingUpOrganization, setSettingUpOrganization] = useState(false);
  const [summary, setSummary] = useState<DashboardSummary['counts'] | null>(null);

  useEffect(() => {
    currentUser()
      .then(async (activeUser) => {
        setUser(activeUser);
        if (activeUser.organization_id) {
          try {
            const response = await apiRequest<{ data: DashboardSummary }>('/dashboard');
            setSummary(response.data.counts);
          } catch { /* The profile remains available if reporting permission is restricted. */ }
        }
      })
      .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Please sign in again.'))
      .finally(() => setLoading(false));
  }, []);

  async function handleProfileSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!user) return;
    const formData = new FormData(event.currentTarget);
    setSaving(true);
    setError('');
    setSaved(false);

    try {
      const updated = await updateProfile({
        name: String(formData.get('name') ?? ''),
        phone: String(formData.get('phone') ?? '') || undefined,
        timezone: String(formData.get('timezone') ?? 'UTC'),
        language: String(formData.get('language') ?? 'en'),
      });
      setUser(updated);
      setSaved(true);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Your profile could not be saved.');
    } finally {
      setSaving(false);
    }
  }

  async function handleOrganizationSetup(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = new FormData(event.currentTarget);
    setSettingUpOrganization(true);
    setError('');

    try {
      await createOrganization({
        name: String(values.get('organization_name') ?? ''),
        website: String(values.get('website') ?? '') || undefined,
        timezone: String(values.get('organization_timezone') ?? 'UTC'),
      });
      setUser(await currentUser());
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Organization setup could not be completed.');
    } finally {
      setSettingUpOrganization(false);
    }
  }

  if (loading) return <LoadingScreen />;
  if (!user) return <ErrorScreen message={error || 'Please sign in to continue.'} />;

  return (
    <AppShell user={user}>
      <div className="fade-in">
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
          <div><p className="mb-2 text-sm font-semibold text-[#4267d5]">YOUR WORKSPACE</p><h1 className="text-3xl font-semibold tracking-tight text-[#14243a]">Good to have you here, {user.name.split(' ')[0]}.</h1><p className="mt-2 text-sm text-[#738095]">Here’s your account overview and workspace setup.</p></div>
          <div className="rounded-xl border border-[#e8ecf2] bg-white px-4 py-3 text-sm text-[#738095] shadow-sm"><span className="mr-2 inline-block h-2 w-2 rounded-full bg-[#27a879]" />Your account is active</div>
        </div>

        <div className="mt-8 grid gap-5 md:grid-cols-3">
          <article className="rounded-2xl border border-[#e8ecf2] bg-white p-5 shadow-[0_3px_12px_rgba(25,45,75,.025)]"><div className="text-xs font-semibold uppercase tracking-wide text-[#8a96a7]">Active projects</div><div className="mt-4 text-3xl font-semibold text-[#26364c]">{summary?.projects ?? '—'}</div><div className="mt-1 text-xs text-[#8a96a7]">In your organization</div></article>
          <article className="rounded-2xl border border-[#e8ecf2] bg-white p-5 shadow-[0_3px_12px_rgba(25,45,75,.025)]"><div className="text-xs font-semibold uppercase tracking-wide text-[#8a96a7]">Open tasks</div><div className="mt-4 text-3xl font-semibold text-[#26364c]">{summary?.open_tasks ?? '—'}</div><div className="mt-1 text-xs text-[#8a96a7]">{summary?.overdue_tasks ?? 0} overdue</div></article>
          <article className="rounded-2xl border border-[#e8ecf2] bg-white p-5 shadow-[0_3px_12px_rgba(25,45,75,.025)]"><div className="text-xs font-semibold uppercase tracking-wide text-[#8a96a7]">Completed this week</div><div className="mt-4 text-3xl font-semibold text-[#26364c]">{summary?.completed_this_week ?? '—'}</div><div className="mt-1 text-xs text-[#8a96a7]">Tasks marked done</div></article>
          <article className="rounded-2xl border border-[#e8ecf2] bg-white p-5 shadow-[0_3px_12px_rgba(25,45,75,.025)]"><div className="text-xs font-semibold uppercase tracking-wide text-[#8a96a7]">Organization</div><div className="mt-4 font-semibold text-[#26364c]">{user.organization_id ? 'Workspace connected' : 'Not set up yet'}</div><div className="mt-1 text-xs text-[#8a96a7]">{user.organization_id ? `Reference ${user.organization_id.slice(0, 8)}…` : 'Add your organization details below.'}</div></article>
          <article className="rounded-2xl border border-[#e8ecf2] bg-white p-5 shadow-[0_3px_12px_rgba(25,45,75,.025)]"><div className="text-xs font-semibold uppercase tracking-wide text-[#8a96a7]">Email verification</div><div className="mt-4 font-semibold text-[#26364c]">{user.email_verified_at ? 'Verified' : 'Pending'}</div><div className="mt-1 text-xs text-[#8a96a7]">{user.email_verified_at ? 'Your email address is confirmed.' : 'Email delivery can be configured later.'}</div></article>
        </div>

        <div className="mt-8 grid gap-5 xl:grid-cols-[1.45fr_.75fr]">
          <section className="rounded-2xl border border-[#e8ecf2] bg-white p-6 shadow-[0_3px_12px_rgba(25,45,75,.025)] sm:p-7">
            <div className="flex items-start justify-between gap-4"><div><h2 className="text-lg font-semibold text-[#26364c]">Your profile</h2><p className="mt-1 text-sm text-[#8792a2]">Keep your account details up to date.</p></div><div className="rounded-lg bg-[#f1f4fd] px-3 py-1.5 text-xs font-semibold text-[#4267d5]">Administrator</div></div>
            {error && <div role="alert" className="mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
            {saved && <div role="status" className="mt-5 rounded-lg bg-[#edfaf3] px-4 py-3 text-sm text-[#16633f]">Your profile has been updated.</div>}
            <form onSubmit={handleProfileSave} className="mt-6 grid gap-x-5 gap-y-4 sm:grid-cols-2">
              <label className="text-sm font-medium text-[#526176]">Full name<input className="app-input mt-2" name="name" defaultValue={user.name} required maxLength={255} /></label>
              <label className="text-sm font-medium text-[#526176]">Email address<input className="app-input mt-2 bg-[#f7f8fa] text-[#8792a2]" value={user.email} readOnly /></label>
              <label className="text-sm font-medium text-[#526176]">Phone number<input className="app-input mt-2" name="phone" defaultValue={user.phone ?? ''} placeholder="Add a phone number" maxLength={40} /></label>
              <label className="text-sm font-medium text-[#526176]">Timezone<input className="app-input mt-2" name="timezone" defaultValue={user.timezone} required /></label>
              <label className="text-sm font-medium text-[#526176]">Language<input className="app-input mt-2" name="language" defaultValue={user.language} required maxLength={10} /></label>
              <div className="flex items-end justify-start sm:justify-end"><button type="submit" disabled={saving} className="h-[42px] rounded-lg bg-[#4267d5] px-5 text-sm font-semibold text-white hover:bg-[#3458c4] disabled:opacity-60">{saving ? 'Saving…' : 'Save changes'}</button></div>
            </form>
          </section>

          {!user.organization_id ? <section className="rounded-2xl border border-[#dfe6fb] bg-[#f9faff] p-6 shadow-[0_3px_12px_rgba(25,45,75,.025)] sm:p-7"><div className="grid h-11 w-11 place-items-center rounded-xl bg-[#e8edff] text-lg text-[#4267d5]">✦</div><h2 className="mt-5 text-lg font-semibold text-[#26364c]">Set up your organization</h2><p className="mt-2 text-sm leading-6 text-[#738095]">Create your workspace to unlock people, roles, and team settings.</p><form onSubmit={handleOrganizationSetup} className="mt-5 space-y-4"><label className="block text-sm font-medium text-[#526176]">Organization name<input className="app-input mt-2" name="organization_name" maxLength={160} placeholder="Acme Studio" required /></label><label className="block text-sm font-medium text-[#526176]">Website <span className="font-normal text-[#9aa3b1]">(optional)</span><input className="app-input mt-2" name="website" type="url" maxLength={255} placeholder="https://example.com" /></label><label className="block text-sm font-medium text-[#526176]">Timezone<input className="app-input mt-2" name="organization_timezone" defaultValue="UTC" required /></label><button type="submit" disabled={settingUpOrganization} className="h-11 w-full rounded-lg bg-[#4267d5] px-4 text-sm font-semibold text-white hover:bg-[#3458c4] disabled:opacity-60">{settingUpOrganization ? 'Creating workspace…' : 'Create organization'}</button></form></section> : <aside className="rounded-2xl bg-[#1c304a] p-6 text-white shadow-[0_8px_24px_rgba(25,45,75,.1)] sm:p-7">
            <div className="grid h-11 w-11 place-items-center rounded-xl bg-white/10 text-lg">✦</div>
            <h2 className="mt-5 text-lg font-semibold">A good place to start</h2>
            <p className="mt-2 text-sm leading-6 text-[#bdc8d9]">Your account is ready. Once your organization is set up, you can invite people and start coordinating work together.</p>
            <div className="mt-6 space-y-4">
              <div className="flex gap-3"><span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#344c6b] text-xs font-semibold">1</span><div><div className="text-sm font-semibold">Set up your organization</div><div className="mt-0.5 text-xs text-[#a9b6cc]">Add the company workspace details.</div></div></div>
              <div className="flex gap-3"><span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#344c6b] text-xs font-semibold">2</span><div><div className="text-sm font-semibold">Bring your team in</div><div className="mt-0.5 text-xs text-[#a9b6cc]">Create accounts and assign access.</div></div></div>
              <div className="flex gap-3"><span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#344c6b] text-xs font-semibold">3</span><div><div className="text-sm font-semibold">Plan the work</div><div className="mt-0.5 text-xs text-[#a9b6cc]">Create projects and track tasks.</div></div></div>
            </div>
          </aside>}
        </div>
      </div>
    </AppShell>
  );
}
