'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { currentUser, requestPasswordReset, signIn } from '@/lib/api';

export default function SignInPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [checkingSession, setCheckingSession] = useState(true);
  const [forgotPassword, setForgotPassword] = useState(false);
  const [notice, setNotice] = useState('');

  useEffect(() => {
    if (new URLSearchParams(window.location.search).get('password-reset') === '1') {
      setNotice('Your password was updated. Sign in with your new password.');
    }
    currentUser()
      .then(() => router.replace('/dashboard'))
      .catch(() => setCheckingSession(false));
  }, [router]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError('');
    setLoading(true);

    try {
      await signIn(email, password);
      router.replace('/dashboard/');
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Sign in failed. Please try again.');
      setLoading(false);
    }
  }

  async function handleForgotPassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(''); setNotice(''); setLoading(true);
    try { await requestPasswordReset(email); setNotice('If an account matches that email, a reset link has been sent.'); }
    catch (caught) { setError(caught instanceof Error ? caught.message : 'The reset request could not be sent.'); }
    finally { setLoading(false); }
  }

  return (
    <main className="min-h-screen bg-white lg:grid lg:grid-cols-[1.02fr_.98fr]">
      <section className="relative hidden overflow-hidden bg-[#14243a] px-14 py-12 text-white lg:flex lg:flex-col lg:justify-between xl:px-20">
        <div className="absolute -right-36 -top-32 h-[520px] w-[520px] rounded-full border border-white/10" />
        <div className="absolute -right-12 -top-8 h-[270px] w-[270px] rounded-full border border-white/10" />
        <div className="absolute -bottom-48 -left-44 h-[530px] w-[530px] rounded-full bg-[#233958]" />
        <div className="relative z-10 flex items-center gap-3">
          <div className="grid h-10 w-10 place-items-center rounded-xl bg-[#7792f0] text-lg font-black">T</div>
          <span className="font-semibold tracking-wide">Taskflow</span>
        </div>
        <div className="relative z-10 max-w-xl pb-10">
          <p className="mb-5 text-sm font-semibold uppercase tracking-[.2em] text-[#94a9ff]">Work, in good flow</p>
          <h1 className="max-w-lg text-5xl font-semibold leading-[1.12] tracking-tight xl:text-[58px]">A little more clarity in every workday.</h1>
          <p className="mt-6 max-w-md text-base leading-7 text-[#bdc8d9]">Bring your people and priorities together. Sign in to pick up where your team left off.</p>
          <div className="mt-12 grid max-w-md grid-cols-3 gap-4 border-t border-white/15 pt-6">
            <div><div className="text-xl font-semibold">One place</div><div className="mt-1 text-xs text-[#bdc8d9]">for the work</div></div>
            <div><div className="text-xl font-semibold">Your team</div><div className="mt-1 text-xs text-[#bdc8d9]">in sync</div></div>
            <div><div className="text-xl font-semibold">Clear focus</div><div className="mt-1 text-xs text-[#bdc8d9]">every day</div></div>
          </div>
        </div>
        <div className="relative z-10 text-xs text-[#96a4bb]">A calmer way to move work forward.</div>
      </section>

      <section className="flex min-h-screen items-center justify-center px-6 py-12 sm:px-10">
        <div className="w-full max-w-[420px] fade-in">
          <div className="mb-11 flex items-center gap-3 lg:hidden">
            <div className="grid h-10 w-10 place-items-center rounded-xl bg-[#4267d5] text-lg font-black text-white">T</div>
            <span className="font-semibold tracking-wide">Taskflow</span>
          </div>
          <div className="mb-9">
            <p className="mb-3 text-sm font-semibold text-[#4267d5]">WELCOME BACK</p>
            <h2 className="text-[32px] font-semibold tracking-tight text-[#14243a]">Sign in to your workspace</h2>
            <p className="mt-3 text-sm leading-6 text-[#738095]">Use the administrator account created during installation.</p>
          </div>

          {error && <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
          {notice && <div role="status" className="mb-5 rounded-xl bg-[#edfaf3] px-4 py-3 text-sm text-[#16633f]">{notice}</div>}

          {checkingSession ? (
            <div className="flex h-48 items-center justify-center text-sm text-[#738095]">Checking your session…</div>
          ) : (
            <form className="space-y-5" onSubmit={forgotPassword ? handleForgotPassword : handleSubmit}>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-[#2d3a4e]">Email address</span>
                <input className="auth-input" type="email" autoComplete="username" placeholder="you@company.com" value={email} onChange={(event) => setEmail(event.target.value)} required />
              </label>
              {!forgotPassword && <label className="block">
                <span className="mb-2 block text-sm font-semibold text-[#2d3a4e]">Password</span>
                <input className="auth-input" type="password" autoComplete="current-password" placeholder="Enter your password" value={password} onChange={(event) => setPassword(event.target.value)} required />
              </label>}
              <button className="mt-2 flex h-[50px] w-full items-center justify-center rounded-[10px] bg-[#4267d5] px-5 font-semibold text-white shadow-[0_8px_18px_rgba(66,103,213,.2)] transition hover:bg-[#3458c4] disabled:cursor-wait disabled:opacity-65" type="submit" disabled={loading}>
                {loading ? 'Please wait…' : forgotPassword ? 'Send reset link' : 'Sign in'}
              </button>
              <button type="button" onClick={() => { setForgotPassword((value) => !value); setError(''); setNotice(''); }} className="w-full text-sm font-semibold text-[#4267d5]">{forgotPassword ? 'Back to sign in' : 'Forgot your password?'}</button>
              <p className="pt-2 text-center text-xs leading-5 text-[#8590a1]">Your sign-in is protected by a secure server session.</p>
            </form>
          )}
          <div className="mt-12 border-t border-[#edf0f4] pt-5 text-center text-xs text-[#9aa3b1]">Task Management System</div>
        </div>
      </section>
    </main>
  );
}
