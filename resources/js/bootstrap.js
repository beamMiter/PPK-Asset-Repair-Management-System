// axios used to be imported here for `window.axios`. Nothing called it (every request is fetch(), and no broadcast uses
// toOthers(), which is what Echo reads it for), and it was 50 kB of every page.

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
