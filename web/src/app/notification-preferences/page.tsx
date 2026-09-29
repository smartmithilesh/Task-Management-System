'use client';

import { useEffect, useState } from 'react';
import { AppShell, ErrorScreen, LoadingScreen } from '@/components/app-shell';
import { apiRequest, currentUser, User } from '@/lib/api';

type Channel = { channel: 'in_app' | 'email' | 'push'; enabled: boolean };
type Preference = { id: string; slug: string; name: string; category: string; channels: Channel[] };

export default function NotificationPreferencesPage() {
  const [user, setUser] = useState<User | null>(null);
  const [preferences, setPreferences] = useState<Preference[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    currentUser().then(async (active) => {
      setUser(active);
      const result = await apiRequest<{ data: Preference[] }>('/notification-preferences');
      setPreferences(result.data);
    }).catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Preferences could not be loaded.')).finally(() => setLoading(false));
  }, []);

  function toggle(typeId: string, channel: Channel['channel']) {
    setPreferences((items) => items.map((item) => item.id !== typeId ? item : {
      ...item,
      channels: item.channels.map((option) => option.channel === channel ? { ...option, enabled: !option.enabled } : option),
    }));
  }

  async function save() {
    setSaving(true); setError(''); setNotice('');
    try {
      await apiRequest('/notification-preferences', { method: 'PUT', body: JSON.stringify({ preferences: preferences.flatMap((item) => item.channels.map((channel) => ({ type_id: item.id, ...channel }))) }) });
      setNotice('Notification preferences saved.');
    } catch (caught) { setError(caught instanceof Error ? caught.message : 'Preferences could not be saved.'); }
    finally { setSaving(false); }
  }

  if (loading) return <LoadingScreen />;
  if (!user) return <ErrorScreen message={error || 'Please sign in to continue.'} />;
  const categories = [...new Set(preferences.map((preference) => preference.category))];
  return <AppShell user={user}><div className="fade-in"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="mb-2 text-sm font-semibold text-[#4267d5]">ACCOUNT SETTINGS</p><h1 className="text-3xl font-semibold tracking-tight text-[#14243a]">Notification preferences</h1><p className="mt-2 text-sm text-[#738095]">Choose where task and workspace updates should reach you.</p></div><button onClick={save} disabled={saving} className="h-11 rounded-lg bg-[#4267d5] px-5 text-sm font-semibold text-white disabled:opacity-60">{saving ? 'Saving…' : 'Save preferences'}</button></div>
    {error && <div role="alert" className="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}{notice && <div role="status" className="mt-5 rounded-xl bg-[#edfaf3] px-4 py-3 text-sm text-[#16633f]">{notice}</div>}
    <div className="mt-7 space-y-5">{categories.map((category) => <section key={category} className="overflow-hidden rounded-2xl border border-[#e8ecf2] bg-white"><div className="border-b bg-[#fafbfd] px-5 py-3 text-xs font-semibold uppercase tracking-wide text-[#8792a2]">{category}</div>{preferences.filter((item) => item.category === category).map((item) => <div key={item.id} className="flex flex-wrap items-center justify-between gap-4 border-b px-5 py-4 last:border-0"><div><div className="text-sm font-semibold text-[#34435a]">{item.name}</div><div className="mt-1 text-xs text-[#8792a2]">{item.slug}</div></div><div className="flex gap-5">{item.channels.map((channel) => <label key={channel.channel} className="flex items-center gap-2 text-xs font-medium capitalize text-[#526176]"><input type="checkbox" checked={channel.enabled} onChange={() => toggle(item.id, channel.channel)} />{channel.channel === 'in_app' ? 'In app' : channel.channel === 'push' ? 'Push' : channel.channel}</label>)}</div></div>)}</section>)}</div>
  </div></AppShell>;
}
