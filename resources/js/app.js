import * as clinicData from './data';
import { describeSupabase } from './supabase';

/**
 * Vite entry point for the application's JavaScript bundle.
 *
 * Its only job is to initialise the Supabase data layer and publish it under
 * one namespace, because the pages are server-rendered Blade with inline
 * scripts and cannot `import` modules themselves. Views therefore call
 * window.clinicData.* rather than touching the Supabase SDK.
 *
 * When Supabase is not configured this bundle is inert: every helper reports
 * itself unavailable and the pages keep using their Laravel endpoints.
 */
window.clinicData = {
    ...clinicData,
    describeSupabase,
};

// One diagnostic line so a developer can tell at a glance whether the browser
// is talking to Supabase or to Laravel.
console.info(`[clinic] ${describeSupabase()}`);
