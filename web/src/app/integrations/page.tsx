'use client';

import { FormEvent, useEffect, useState } from 'react';
import { AppShell, ErrorScreen, LoadingScreen } from '@/components/app-shell';
import { apiRequest, currentUser, User } from '@/lib/api';

type Integration = { id: string; provider: string; connection_name: string; status: string; credentials_configured: boolean; connected_at: string | null };
type Adapter = { key: string; capabilities: string[] };

export default function IntegrationsPage() {
  const [user, setUser] = useState<User | null>(null);
  const [integrations, setIntegrations] = useState<Integration[]>([]);
  const [adapters, setAdapters] = useState<Adapter[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [connecting, setConnecting] = useState('');
  const [slackChannel, setSlackChannel] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  async function refresh() {
    const result = await apiRequest<{ data: Integration[]; adapters: Adapter[] }>('/integrations');
    setIntegrations(result.data);
    setAdapters(result.adapters);
  }

  useEffect(() => {
    const outcome = new URLSearchParams(window.location.search).get('oauth');
    if (outcome === 'connected') setNotice('Provider connected successfully.');
    else if (outcome && outcome !== 'denied') setError('Provider authorization did not complete. You can retry the connection.');
    else if (outcome === 'denied') setNotice('Provider authorization was cancelled.');
    currentUser().then(async (active) => { setUser(active); await refresh(); })
      .catch((caught: unknown) => setError(caught instanceof Error ? caught.message : 'Please sign in again.'))
      .finally(() => setLoading(false));
  }, []);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = new FormData(form);
    setBusy(true); setError(''); setNotice('');
    try {
      await apiRequest('/integrations', { method: 'POST', body: JSON.stringify({
        provider: 'webhook',
        connection_name: String(values.get('connection_name') || 'primary'),
        credentials: { endpoint: String(values.get('endpoint')), signing_secret: String(values.get('signing_secret')) },
      }) });
      form.reset(); await refresh(); setNotice('Signed webhook connection saved.');
    } catch (caught) { setError(caught instanceof Error ? caught.message : 'Integration could not be saved.'); }
    finally { setBusy(false); }
  }

  async function connectProvider(adapter: Adapter) {
    setConnecting(adapter.key); setError(''); setNotice('');
    try {
      const result = await apiRequest<{ data: { authorization_url: string } }>(`/integrations/oauth/${adapter.key}/authorize`, {
        method: 'POST', body: JSON.stringify({
          connection_name: 'primary',
          ...(adapter.key === 'slack' ? { configuration: { channel_id: slackChannel.trim() } } : {}),
        }),
      });
      window.location.assign(result.data.authorization_url);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Provider authorization could not start.');
      setConnecting('');
    }
  }

  async function test(integration: Integration) {
    setError(''); setNotice('');
    try { const result = await apiRequest<{ data: { message: string } }>(`/integrations/${integration.id}/test`, { method: 'POST', body: JSON.stringify({}) }); setNotice(result.data.message); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Connection test failed.'); }
  }

  async function disconnect(integration: Integration) {
    setError(''); setNotice('');
    try { const result = await apiRequest<{ data: { remote_token_revoked: boolean } }>(`/integrations/${integration.id}`, { method: 'DELETE' }); await refresh(); setNotice(result.data.remote_token_revoked ? 'Integration disconnected and credentials removed.' : 'Local credentials were removed. The provider did not confirm token revocation; revoke access in that provider account.'); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'Could not disconnect integration.'); }
  }

  if (loading) return <LoadingScreen />;
  if (!user) return <ErrorScreen message={error || 'Please sign in to continue.'} />;
  return <AppShell user={user}><div className="fade-in"><p className="mb-2 text-sm font-semibold text-[#4267d5]">WORKSPACE SETTINGS</p><h1 className="text-3xl font-semibold tracking-tight text-[#14243a]">Integrations</h1><p className="mt-2 text-sm text-[#738095]">Connect configured services using provider authorization or receive signed task events by webhook.</p>
    {error && <div role="alert" className="mt-6 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}{notice && <div role="status" className="mt-6 rounded-xl bg-[#edfaf3] px-4 py-3 text-sm text-[#16633f]">{notice}</div>}
    <div className="mt-7 grid items-start gap-5 xl:grid-cols-[1.2fr_.8fr]"><div className="space-y-5"><section className="overflow-hidden rounded-2xl border border-[#e8ecf2] bg-white"><div className="border-b px-5 py-4 font-semibold text-[#26364c]">Connections</div>{integrations.length ? <ul className="divide-y divide-[#edf0f4]">{integrations.map((integration) => <li key={integration.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"><div><div className="font-semibold capitalize text-[#34435a]">{integration.provider.replaceAll('_', ' ')} · {integration.connection_name}</div><p className="mt-1 text-xs text-[#8792a2]">{integration.status} · credentials {integration.credentials_configured ? 'stored' : 'missing'}</p></div><div className="flex gap-2"><button onClick={() => test(integration)} disabled={!integration.credentials_configured} className="rounded-lg border px-3 py-2 text-xs font-semibold text-[#4267d5] disabled:opacity-50">Test connection</button><button onClick={() => disconnect(integration)} className="rounded-lg border border-[#f3d5d2] px-3 py-2 text-xs font-semibold text-[#a32920]">Disconnect</button></div></li>)}</ul> : <p className="p-8 text-center text-sm text-[#8792a2]">No integrations configured.</p>}</section>
      {adapters.some((adapter) => adapter.key !== 'webhook') && <section className="rounded-2xl border border-[#e8ecf2] bg-white"><div className="border-b px-5 py-4 font-semibold text-[#26364c]">Available services</div><ul className="divide-y divide-[#edf0f4]">{adapters.filter((adapter) => adapter.key !== 'webhook').map((adapter) => <li key={adapter.key} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"><div className="min-w-52 flex-1"><div className="font-semibold capitalize text-[#34435a]">{adapter.key.replaceAll('_', ' ')}</div><p className="mt-1 text-xs text-[#8792a2]">{adapter.capabilities.filter((capability) => capability !== 'oauth2_authorization_code').join(' · ')}</p>{adapter.key === 'slack' && <input value={slackChannel} onChange={(event) => setSlackChannel(event.target.value)} aria-label="Slack channel ID" className="app-input mt-3 max-w-64" placeholder="Slack channel ID (C…)" />}</div><button onClick={() => connectProvider(adapter)} disabled={connecting !== '' || (adapter.key === 'slack' && slackChannel.trim() === '')} className="rounded-lg bg-[#4267d5] px-4 py-2 text-xs font-semibold text-white disabled:opacity-60">{connecting === adapter.key ? 'Connecting…' : 'Connect'}</button></li>)}</ul></section>}</div>
      {adapters.some((adapter) => adapter.key === 'webhook') && <section className="rounded-2xl border border-[#e8ecf2] bg-white p-5"><h2 className="font-semibold text-[#26364c]">Configure webhook</h2><p className="mt-1 text-xs leading-5 text-[#8792a2]">Only public HTTPS endpoints are accepted. Credentials are encrypted; secrets are signed with HMAC-SHA256.</p><form onSubmit={create} className="mt-4 space-y-4"><label className="block text-sm font-medium text-[#526176]">Connection name<input className="app-input mt-2" name="connection_name" maxLength={100} placeholder="primary" /></label><label className="block text-sm font-medium text-[#526176]">HTTPS endpoint<input className="app-input mt-2" name="endpoint" type="url" required placeholder="https://example.com/events" /></label><label className="block text-sm font-medium text-[#526176]">Signing secret<input className="app-input mt-2" name="signing_secret" type="password" minLength={24} autoComplete="new-password" required /></label><button disabled={busy} className="h-11 w-full rounded-lg bg-[#4267d5] px-4 text-sm font-semibold text-white disabled:opacity-60">{busy ? 'Saving…' : 'Save webhook'}</button></form><p className="mt-4 text-xs text-[#8792a2]">{adapters.find((adapter) => adapter.key === 'webhook')?.capabilities.join(' · ')}</p></section>}
    </div>
  </div></AppShell>;
}
