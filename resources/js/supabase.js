import { createClient } from '@supabase/supabase-js';

/**
 * The single owner of the Supabase client in this codebase.
 *
 * Nothing else may call createClient() or read import.meta.env for Supabase
 * values. Views and other modules go through resources/js/data.js, so the
 * credentials, the schema name and the "is it configured?" decision all live
 * in exactly one place.
 *
 * Config comes from .env (Vite only exposes VITE_-prefixed keys):
 *   VITE_SUPABASE_URL        https://<project-ref>.supabase.co
 *   VITE_SUPABASE_ANON_KEY   the anon/publishable key
 *   VITE_SUPABASE_SCHEMA     defaults to the DB_SCHEMA used by Laravel
 *   VITE_SUPABASE_READS      'on' to serve reads from Supabase (default 'off')
 *
 * The anon key is public by design — it ships in the browser bundle and is not
 * a secret. It is still read from the environment rather than hardcoded so that
 * no project reference is committed.
 */

const url = (import.meta.env.VITE_SUPABASE_URL ?? '').trim();
const anonKey = (import.meta.env.VITE_SUPABASE_ANON_KEY ?? '').trim();

/** The Postgres schema Laravel uses. Kept out of `public`, so it must be named. */
export const supabaseSchema = (import.meta.env.VITE_SUPABASE_SCHEMA ?? 'dental').trim();

/**
 * Placeholder values must never be treated as a working configuration — the
 * app would otherwise fire requests at a host that does not exist and every
 * data path would fail instead of quietly falling back to Laravel.
 */
const PLACEHOLDER = /^(|REPLACE_ME|your-.*|undefined|null)$/i;

function isReal(value) {
    return value !== '' && !PLACEHOLDER.test(value);
}

/**
 * True only when real credentials are present. Every consumer must check this
 * before using the client; when it is false the app runs entirely on Laravel.
 */
export const isSupabaseConfigured =
    /^https?:\/\/.+/.test(url) &&
    isReal(url) &&
    isReal(anonKey) &&
    !url.includes('REPLACE_ME') &&
    !anonKey.includes('REPLACE_ME');

/**
 * Whether reads may be served from Supabase instead of Laravel.
 *
 * Off by default on purpose. Serving reads from the browser means granting the
 * public `anon` role SELECT on the tables involved, which exposes clinic data
 * to anyone holding the anon key — see database/supabase/03_realtime.sql and
 * the README before switching this on.
 */
export const readsEnabled =
    isSupabaseConfigured && (import.meta.env.VITE_SUPABASE_READS ?? 'off').trim() === 'on';

export const supabase = isSupabaseConfigured
    ? createClient(url, anonKey, {
          db: { schema: supabaseSchema },
          auth: {
              // Authentication stays with Laravel sessions; the browser never
              // holds a Supabase session, so do not touch localStorage.
              persistSession: false,
              autoRefreshToken: false,
              detectSessionInUrl: false,
          },
          realtime: { params: { eventsPerSecond: 5 } },
      })
    : null;

/** Safe one-line diagnostic; never logs the key itself. */
export function describeSupabase() {
    if (!isSupabaseConfigured) {
        return 'Supabase: not configured (running fully on Laravel).';
    }

    return `Supabase: configured for schema "${supabaseSchema}" (reads ${readsEnabled ? 'on' : 'off'}).`;
}
