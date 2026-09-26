// Entry point loaded by chat/index.blade.php (`@vite`). Vite loads it once per browser session, so the page's document
// listeners are registered once however many Turbo visits follow; each visit mounts (and tears down) the thread on screen.
import { installChatPage } from './page.js';

installChatPage();
