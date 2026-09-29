'use client';

import { FormEvent, Suspense, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { resetPassword } from '@/lib/api';

function ResetPasswordForm() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = new FormData(event.currentTarget);
    setBusy(true); setError('');
    try {
      await resetPassword({ token, email, password: String(values.get('password')), password_confirmation: String(values.get('password_confirmation')) });
      router.replace('/?password-reset=1');
    } catch (caught) { setError(caught instanceof Error ? caught.message : 'The password could not be reset.'); }
    finally { setBusy(false); }
  }

  return <main className="grid min-h-screen place-items-center bg-[#f5f7fb] p-5"><section className="w-full max-w-md rounded-2xl border border-[#e8ecf2] bg-white p-7 shadow-sm"><p className="text-sm font-semibold text-[#4267d5]">TASKFLOW</p><h1 className="mt-3 text-2xl font-semibold text-[#14243a]">Choose a new password</h1><p className="mt-2 text-sm text-[#738095]">Use at least 12 characters with uppercase, lowercase, and a number.</p>{error && <p role="alert" className="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}{!token || !email ? <p className="mt-5 text-sm text-[#a32920]">This reset link is incomplete. Request another link from sign in.</p> : <form onSubmit={submit} className="mt-5 space-y-4"><label className="block text-sm font-medium text-[#526176]">New password<input name="password" type="password" autoComplete="new-password" minLength={12} required className="app-input mt-2" /></label><label className="block text-sm font-medium text-[#526176]">Confirm password<input name="password_confirmation" type="password" autoComplete="new-password" minLength={12} required className="app-input mt-2" /></label><button disabled={busy} className="h-11 w-full rounded-lg bg-[#4267d5] px-4 text-sm font-semibold text-white disabled:opacity-60">{busy ? 'Updating…' : 'Update password'}</button></form>}<Link href="/" className="mt-5 inline-block text-sm font-semibold text-[#4267d5]">Return to sign in</Link></section></main>;
}

export default function ResetPasswordPage() {
  return <Suspense fallback={<main className="grid min-h-screen place-items-center text-sm text-[#738095]">Loading reset link…</main>}><ResetPasswordForm /></Suspense>;
}
