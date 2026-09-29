'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { apiRequest, signOut, User } from '@/lib/api';

type InboxNotification = { id: string; data: { event?: string; title?: string; task_number?: string; project?: string }; read_at: string | null; created_at: string };

export function AppShell({ user, children }: { user: User; children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [signingOut, setSigningOut] = useState(false);
  const [notifications, setNotifications] = useState<InboxNotification[]>([]);
  const [inboxOpen, setInboxOpen] = useState(false);

  useEffect(() => {
    apiRequest<{ data: InboxNotification[] }>('/notifications?unread=1')
      .then((result) => setNotifications(result.data))
      .catch(() => undefined);
  }, []);

  async function readAllNotifications() {
    try {
      await apiRequest('/notifications/read-all', { method: 'PATCH' });
      setNotifications([]);
    } catch { /* The inbox can be refreshed next time the workspace opens. */ }
  }

  async function handleSignOut() {
    setSigningOut(true);
    try {
      await signOut();
      router.replace('/');
    } catch {
      setSigningOut(false);
    }
  }

  const initials = user.name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

  return (
    <div className="min-h-screen lg:flex">
      <aside className="hidden w-[252px] shrink-0 flex-col bg-[#14243a] px-5 py-6 text-white lg:flex">
        <Link href="/dashboard" className="mb-10 flex items-center gap-3 px-2">
          <span className="grid h-10 w-10 place-items-center rounded-xl bg-[#7792f0] text-lg font-black">T</span>
          <span className="font-semibold tracking-wide">Taskflow</span>
        </Link>

        <p className="mb-3 px-3 text-[10px] font-bold uppercase tracking-[.18em] text-[#7688a3]">Workspace</p>
        <nav className="space-y-1">
          {([['/dashboard', '⌂', 'Overview'], ['/projects', '▦', 'Projects'], ['/tasks', '☷', 'Tasks'], ['/board', '▤', 'Board'], ['/calendar', '▦', 'Calendar'], ['/reports', '◷', 'Reports'], ['/users', '♧', 'People'], ['/departments', '▥', 'Departments'], ['/teams', '♧', 'Teams'], ['/roles', '⚙', 'Roles & access'], ['/integrations', '⇄', 'Integrations'], ['/notification-preferences', '♢', 'Notifications']] as const).map(([href, icon, label]) => <Link key={href} href={href} className={`nav-link ${pathname === href ? 'active' : ''}`}><span className="w-5 text-center">{icon}</span>{label}</Link>)}
        </nav>

        <div className="mt-auto rounded-2xl border border-white/10 bg-white/[.04] p-4">
          <div className="mb-2 text-xs font-semibold text-white">Your workspace</div>
          <p className="text-xs leading-5 text-[#a9b6cc]">Keep your team, tasks, and updates together in one place.</p>
          <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-white/10"><div className="h-full w-1/3 rounded-full bg-[#7792f0]" /></div>
          <p className="mt-2 text-[10px] text-[#91a0b8]">Workspace setup</p>
        </div>
      </aside>

      <div className="min-w-0 flex-1">
        <header className="sticky top-0 z-20 flex h-[72px] items-center justify-between border-b border-[#e9edf3] bg-white/95 px-5 backdrop-blur sm:px-8">
          <div className="flex items-center gap-3 lg:hidden">
            <span className="grid h-9 w-9 place-items-center rounded-xl bg-[#4267d5] font-black text-white">T</span>
            <span className="font-semibold">Taskflow</span>
          </div>
          <div className="hidden text-sm text-[#8b96a6] lg:block">Workspace <span className="px-2 text-[#c7ced8]">/</span> <span className="font-semibold text-[#34435a]">{pathname === '/users' ? 'People' : 'Overview'}</span></div>
          <nav className="flex items-center gap-2 overflow-x-auto lg:hidden">
            {([['/dashboard', 'Overview'], ['/projects', 'Projects'], ['/tasks', 'Tasks'], ['/board', 'Board'], ['/calendar', 'Calendar'], ['/reports', 'Reports'], ['/users', 'People'], ['/departments', 'Departments'], ['/teams', 'Teams'], ['/roles', 'Roles'], ['/integrations', 'Integrations'], ['/notification-preferences', 'Notifications']] as const).map(([href, label]) => <Link key={href} className={`whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold ${pathname === href ? 'bg-[#eef2ff] text-[#4267d5]' : 'text-[#64748b]'}`} href={href}>{label}</Link>)}
          </nav>
          <div className="flex items-center gap-3 sm:gap-4">
            <div className="relative"><button type="button" onClick={() => setInboxOpen((open) => !open)} aria-label={`Notifications${notifications.length ? `, ${notifications.length} unread` : ''}`} className="relative grid h-10 w-10 place-items-center rounded-full border border-[#e3e8ef] text-lg text-[#526176] hover:bg-[#f6f8fb]">♧{notifications.length > 0 && <span className="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-[#e04f44] px-1 text-[10px] font-bold text-white">{notifications.length}</span>}</button>{inboxOpen && <section className="absolute right-0 top-12 z-30 w-[min(360px,calc(100vw-32px))] overflow-hidden rounded-xl border border-[#e3e8ef] bg-white shadow-xl"><div className="flex items-center justify-between border-b px-4 py-3"><h2 className="text-sm font-semibold text-[#26364c]">Notifications</h2>{notifications.length > 0 && <button onClick={readAllNotifications} className="text-xs font-semibold text-[#4267d5]">Mark all read</button>}</div>{notifications.length ? <ul className="max-h-80 overflow-y-auto">{notifications.map((notification) => <li key={notification.id} className="border-b border-[#edf0f4] px-4 py-3 last:border-0"><p className="text-sm font-medium text-[#34435a]">{notification.data.event === 'task.assigned' ? `You were assigned ${notification.data.task_number ?? 'a task'}` : notification.data.event === 'task.mentioned' ? `You were mentioned on ${notification.data.task_number ?? 'a task'}` : 'New workspace update'}</p><p className="mt-1 text-xs text-[#8792a2]">{notification.data.title ?? notification.data.project ?? 'Task activity'}</p></li>)}</ul> : <p className="p-6 text-center text-sm text-[#8792a2]">You’re all caught up.</p>}</section>}</div>
            <div className="hidden text-right sm:block"><div className="text-sm font-semibold text-[#26364c]">{user.name}</div><div className="text-xs text-[#8994a4]">{user.email}</div></div>
            <div className="grid h-10 w-10 place-items-center rounded-full bg-[#e9efff] text-sm font-bold text-[#4267d5]">{initials}</div>
            <button onClick={handleSignOut} disabled={signingOut} className="hidden rounded-lg border border-[#e3e8ef] px-3 py-2 text-xs font-semibold text-[#58677c] hover:bg-[#f6f8fb] disabled:opacity-60 sm:block">{signingOut ? 'Signing out…' : 'Sign out'}</button>
          </div>
        </header>
        <main className="mx-auto w-full max-w-[1320px] px-5 py-8 sm:px-8 sm:py-10">{children}</main>
      </div>
    </div>
  );
}

export function LoadingScreen() {
  return <main className="grid min-h-screen place-items-center bg-[#f5f7fb] text-sm text-[#738095]">Loading your workspace…</main>;
}

export function ErrorScreen({ message }: { message: string }) {
  const router = useRouter();
  return (
    <main className="grid min-h-screen place-items-center bg-[#f5f7fb] p-6">
      <div className="max-w-md rounded-2xl border border-[#e6eaf0] bg-white p-8 text-center shadow-sm">
        <div className="mx-auto mb-5 grid h-12 w-12 place-items-center rounded-full bg-[#fff1f0] text-xl text-[#b42318]">!</div>
        <h1 className="text-lg font-semibold text-[#14243a]">We couldn’t open your workspace</h1>
        <p className="mt-2 text-sm leading-6 text-[#738095]">{message}</p>
        <button onClick={() => router.replace('/')} className="mt-6 rounded-lg bg-[#4267d5] px-5 py-3 text-sm font-semibold text-white hover:bg-[#3458c4]">Return to sign in</button>
      </div>
    </main>
  );
}
