import { supabase, supabaseSchema, isSupabaseConfigured, readsEnabled } from './supabase';

/**
 * The single data-access layer for the browser.
 *
 * Every Supabase query and every Realtime subscription in the frontend lives
 * here. Blade views never import @supabase/supabase-js and never name a table
 * themselves — they either call a function exported from this module or keep
 * using their Laravel endpoint through apiFetch().
 *
 * WHY READS ARE OPT-IN
 * --------------------
 * Laravel authenticates the user with a session cookie and scopes every query
 * by role (a Dentist only ever sees their own schedule, a Patient only their
 * own records). A browser Supabase client has no identity: it authenticates
 * with the shared public anon key. So serving a read from Supabase means
 *
 *   1. granting `anon` SELECT on the table (or a view) in Postgres, and
 *   2. losing per-user scoping in the database, because there is no identity
 *      for RLS policies to key off.
 *
 * Enabling reads for a table holding patient data is therefore a deliberate
 * decision with a real exposure cost, which is why VITE_SUPABASE_READS
 * defaults to 'off'. Everything below degrades to the Laravel path when it is
 * off, so the application behaves identically either way.
 *
 * Realtime change notifications are safe by contrast: they carry no row data
 * at all (see subscribeToAppointmentChanges), so they are always available
 * once Supabase is configured.
 */

/** Reminder of the contract every exported read must satisfy. */

export function isDataLayerEnabled() {
    return isSupabaseConfigured;
}

export function areReadsEnabled() {
    return readsEnabled;
}

// ---------------------------------------------------------------------------
// Presentation constants
// ---------------------------------------------------------------------------
// Appointment::SLOTS / STATUSES equivalent lives in the views that need it;
// these are only the calendar colours, mirroring AppointmentController::
// calendarEvents so an event looks identical whichever path served it.

const STATUS_COLORS = {
    Pending: { backgroundColor: '#D1987F', textColor: '#3D3428' },
    Confirmed: { backgroundColor: '#C89B27', textColor: '#3D3428' },
    Completed: { backgroundColor: '#B4B1B2', textColor: '#FFFFFF' },
};

const DEFAULT_COLOR = { backgroundColor: '#9A8F8E', textColor: '#FFFFFF' };

function colorFor(status) {
    return STATUS_COLORS[status] ?? DEFAULT_COLOR;
}

/** "09:00" -> "09:00 AM", matching Appointment::getFormattedSlotAttribute(). */
export function formatSlot(slot) {
    if (!slot) return '';
    const [h, m] = slot.split(':').map(Number);
    const suffix = h >= 12 ? 'PM' : 'AM';
    const hour = h % 12 || 12;

    return `${hour}:${String(m).padStart(2, '0')} ${suffix}`;
}

// ---------------------------------------------------------------------------
// Reads (opt-in; see the note at the top of this file)
// ---------------------------------------------------------------------------

/**
 * Calendar events for a date range, in the exact shape FullCalendar receives
 * from AppointmentController::calendarEvents.
 *
 * Reads the `appointment_feed` view rather than `appointments`: the view
 * exposes only the columns the calendar renders, so a leaked anon key cannot
 * reach users.password, medical history, prescriptions or SMS logs.
 *
 * @param {{ start?: string, end?: string, dentistId?: string|number|null }} params
 * @returns {Promise<Array>}
 */
export async function fetchCalendarEvents({ start, end, dentistId = null } = {}) {
    if (!readsEnabled) {
        throw new Error('Supabase reads are disabled (set VITE_SUPABASE_READS=on).');
    }

    // FullCalendar sends full ISO timestamps; appointment_date is a plain date.
    const asDate = (value) => (value ? String(value).slice(0, 10) : null);

    let query = supabase
        .from('appointment_feed')
        .select('id, appointment_date, time_slot, service_type, status, dentist_id, patient_name, dentist_name');

    if (start) query = query.gte('appointment_date', asDate(start));
    if (end) query = query.lte('appointment_date', asDate(end));

    // Presentation scoping only — this is NOT an authorization boundary. A user
    // can change dentistId in the console. Real enforcement needs RLS policies
    // keyed to an identity (Supabase Auth, or JWTs minted by Laravel).
    if (dentistId) query = query.eq('dentist_id', dentistId);

    const { data, error } = await query;

    if (error) {
        throw new Error(error.message || 'Could not load schedule events.');
    }

    return (data ?? []).map((row) => {
        const color = colorFor(row.status);

        return {
            id: row.id,
            title: `${formatSlot(row.time_slot)} · ${row.patient_name ?? 'Patient'}`,
            start: `${row.appointment_date} ${row.time_slot}`,
            backgroundColor: color.backgroundColor,
            borderColor: '#FFFFFF',
            textColor: color.textColor,
            extendedProps: {
                patient: row.patient_name ?? '—',
                dentist: row.dentist_name ?? 'Unassigned',
                service_type: row.service_type,
                time_slot: formatSlot(row.time_slot),
                status: row.status,
            },
        };
    });
}

// ---------------------------------------------------------------------------
// Realtime
// ---------------------------------------------------------------------------

/** Channel the database trigger broadcasts appointment changes to. */
export const APPOINTMENT_CHANNEL = 'clinic-appointments';

/**
 * Subscribe to "an appointment changed" signals.
 *
 * Deliberately a *signal*, not a data feed: the database trigger broadcasts a
 * tiny payload with no row contents, so nothing sensitive crosses this channel
 * and no table read permission is needed. Whatever page is listening re-reads
 * its data through its normal (authenticated) path.
 *
 * @param {{ onChange?: () => void, onStatus?: (status: string) => void }} handlers
 * @returns {() => void} unsubscribe
 */
export function subscribeToAppointmentChanges({ onChange, onStatus } = {}) {
    if (!isSupabaseConfigured) {
        return () => {};
    }

    const channel = supabase
        .channel(APPOINTMENT_CHANNEL)
        .on('broadcast', { event: 'changed' }, () => onChange?.())
        .subscribe((status) => onStatus?.(status));

    return () => {
        supabase.removeChannel(channel);
    };
}

// ---------------------------------------------------------------------------
// apiFetch integration
// ---------------------------------------------------------------------------

/**
 * Laravel routes this layer can answer instead of the server.
 *
 * apiFetch() looks a request up here first. Because the map is deliberately
 * empty while VITE_SUPABASE_READS is off, every request keeps going to Laravel
 * and the two paths can never disagree. Add an entry only together with the
 * grant and policy that make it legitimate.
 */
const MIGRATED_GET_ROUTES = {
    // Staff schedule calendar. The blade view fetches through apiFetch() so this
    // entry — and nothing in the view — decides whether the data comes from
    // Supabase or from Laravel.
    '/appointments/calendar-events': (params) =>
        fetchCalendarEvents({
            start: params.get('start'),
            end: params.get('end'),
            dentistId: params.get('dentist_id') || null,
        }),
};

/**
 * Try to serve a GET request from Supabase.
 *
 * @param {string} url  the URL apiFetch was called with
 * @returns {Promise<any>|null}  parsed body, or null to fall back to Laravel
 */
export function serveGet(url) {
    if (!readsEnabled) {
        return null;
    }

    let parsed;
    try {
        parsed = new URL(url, window.location.origin);
    } catch {
        return null;
    }

    const handler = MIGRATED_GET_ROUTES[parsed.pathname];

    return handler ? handler(parsed.searchParams) : null;
}

/** Diagnostic used by the console helper; lists what the layer currently owns. */
export function describeDataLayer() {
    const migrated = Object.keys(MIGRATED_GET_ROUTES);

    return {
        configured: isSupabaseConfigured,
        readsEnabled,
        schema: supabaseSchema,
        migratedReadPaths: migrated,
        realtimeChannel: APPOINTMENT_CHANNEL,
    };
}
