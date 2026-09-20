import '@hotwired/turbo'
import '../css/app.css'
import Alpine from 'alpinejs'
import SignaturePad from 'signature_pad'
window.SignaturePad = SignaturePad
import './sidebar-intro';
import { installToast } from './toast';
import './bootstrap';
import './repair/my-jobs';
import './repair/dashboard';

installToast(); // window.showToast, the `app:toast` event and the flashed session toast — once per session

// Initialize Alpine.js globally for Blade components using x-data/x-show
window.Alpine = Alpine

// Alpine + Turbo: start Alpine once, let it observe DOM mutations for Turbo swaps
document.addEventListener('turbo:load', () => {
    if (!window.__alpineStarted) {
        Alpine.start()
        window.__alpineStarted = true
    }
});


// Make plain selects searchable via keyboard
(() => {
	document.addEventListener('keydown', (e) => {
		if (!(e.target instanceof HTMLSelectElement)) return;
		const select = e.target;
		if (!select.multiple && (e.key.length === 1 || e.key === 'Backspace')) {
			if (!select.__searchTimeout) select.__searchText = '';
			clearTimeout(select.__searchTimeout);

			if (e.key === 'Backspace') {
				select.__searchText = select.__searchText.slice(0, -1);
			} else {
				select.__searchText += e.key.toLowerCase();
			}

			const normalize = (s) => (s||'').toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,'');
			const searchNorm = normalize(select.__searchText);

			for (let opt of select.options) {
				const optText = normalize(opt.text);
				if (optText.startsWith(searchNorm) && opt.value) {
					select.value = opt.value;
					select.dispatchEvent(new Event('change', { bubbles: true }));
					break;
				}
			}

			select.__searchTimeout = setTimeout(() => {
				select.__searchText = '';
			}, 500);
		}
	});
})();
