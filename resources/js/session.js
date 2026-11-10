import { router } from '@inertiajs/vue3';
import { todayYmd } from './format.js';

// Start today's session (the server continues an open one for the same day).
// The date comes from the device so a late-evening session isn't filed under UTC's day.
export function startSession() {
    router.post('/sessions', { date: todayYmd() });
}
