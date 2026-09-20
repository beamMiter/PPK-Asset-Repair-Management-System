// Entry point loaded by layouts/app.blade.php (`@vite`). Kept apart from app.js on purpose: app.js also serves the login /
// guest layouts, which must not get the app layout's link spinner or unsaved-changes guard. Vite loads this file once per
// browser session, so the layout's listeners are registered once however many Turbo visits follow.
import { installLayout } from './index.js';

installLayout();
