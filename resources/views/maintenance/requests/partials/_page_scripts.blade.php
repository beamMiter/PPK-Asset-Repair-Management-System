    <script>
        (function() {
            'use strict';

            // --- 1. ประกาศตัวแปร (Selectors) ---
            const modal = document.getElementById('assignModal');
            const open = document.getElementById('openAssignModalBtn');
            const close = document.getElementById('closeAssignModalBtn');
            const cancel = document.getElementById('cancelAssignModalBtn');

            if (modal && open) {
                const searchInput = document.getElementById('assignSearch');
                const roleFilter = document.getElementById('assignRoleFilter');
                const suggestRole = (document.getElementById('assignSuggestRole')?.value || '').trim().toLowerCase();
                const visibleCountEl = document.getElementById('assignVisibleCount');
                const hintEl = document.getElementById('assignSuggestHint');
                const selectAllBtn = document.getElementById('assignSelectAllBtn');
                const clearAllBtn = document.getElementById('assignClearAllBtn');
                const selectedMetaEl = document.getElementById('assignSelectedMeta');
                const selectedEmptyEl = document.getElementById('assignSelectedEmpty');
                const selectedListEl = document.getElementById('assignSelectedList');
                const assignForm = modal.querySelector('form');

                // --- 2. ฟังก์ชันช่วยงาน (Helper Functions) ---

                const getAllRows = () => Array.from(modal.querySelectorAll('.assign-user-row'));
                const getAllCheckboxes = () => Array.from(modal.querySelectorAll('.assign-user-checkbox'));

                // ดึงเฉพาะ Checkbox ของแถวที่กำลังแสดงอยู่ (ไม่โดน Filter ซ่อน)
                const getVisibleCheckboxes = () => getAllRows()
                    .filter(row => row.style.display !== 'none')
                    .map(row => row.querySelector('.assign-user-checkbox'))
                    .filter(Boolean);

                // ป้องกัน XSS
                const escapeHtml = (str) => String(str || '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

                // ทำอักษรย่อ (เช่น "สมชาย ดีใจ" -> "ส ด")
                const initials = (name) => {
                    const s = (name || '').trim();
                    if (!s) return '—';
                    const parts = s.split(/\s+/).filter(Boolean);
                    const a = (parts[0] || '').charAt(0);
                    const b = parts.length > 1 ? (parts[parts.length - 1] || '').charAt(0) : '';
                    return (a + b).toUpperCase();
                };

                // --- 3. ฟังก์ชันหลักของระบบ (Core Logic) ---

                // อัปเดตรายชื่อคนที่ถูกเลือกใน Sidebar ด้านซ้าย
                function updateSelectedList() {
                    if (!selectedListEl || !selectedEmptyEl || !selectedMetaEl) return;

                    const checked = getAllCheckboxes().filter(cb => cb.checked);
                    selectedMetaEl.textContent = checked.length + ' คน';
                    selectedListEl.innerHTML = '';

                    if (checked.length === 0) {
                        selectedEmptyEl.classList.remove('hidden');
                        selectedListEl.classList.add('hidden');
                        return;
                    }

                    selectedEmptyEl.classList.add('hidden');
                    selectedListEl.classList.remove('hidden');

                    checked.forEach(cb => {
                        const row = cb.closest('.assign-user-row');
                        const displayName = row?.getAttribute('data-display-name') || '';
                        const roleLabel = row?.getAttribute('data-role-label') || '';
                        const ini = initials(displayName);
                        const userId = cb.value;

                        const item = document.createElement('div');
                        item.className =
                            'flex items-center gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 animate-in fade-in slide-in-from-left-2 duration-200';
                        item.dataset.userId = userId;

                        item.innerHTML = `
                    <div class="h-8 w-8 rounded-full bg-slate-800 text-white grid place-items-center text-[11px] font-bold flex-shrink-0 ">
                        ${escapeHtml(ini)}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[12px] font-bold text-slate-900 leading-tight" title="${escapeHtml(displayName)}">${escapeHtml(displayName)}</div>
                        <div class="truncate text-[10px] text-slate-500 uppercase tracking-tighter" title="${escapeHtml(roleLabel)}">${escapeHtml(roleLabel)}</div>
                    </div>
                    <button type="button" class="assign-chip-remove flex-shrink-0 inline-flex items-center justify-center h-6 w-6 rounded-full text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition-colors" data-user-id="${escapeHtml(userId)}" title="ลบออก">
                        <svg class="h-3.5 w-3.5 pointer-events-none" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                `;
                        selectedListEl.appendChild(item);
                    });
                }

                function updateCounts() {
                    const visible = getAllRows().filter(r => r.style.display !== 'none').length;
                    if (visibleCountEl) visibleCountEl.textContent = `(${visible})`;
                    updateSelectedList();
                }

                // ระบบกรองชื่อและตำแหน่ง
                function applyFilter() {
                    const q = (searchInput?.value || '').trim().toLowerCase();
                    const r = (roleFilter?.value || '').trim().toLowerCase();

                    getAllRows().forEach(row => {
                        const name = (row.getAttribute('data-name') || '').toLowerCase().trim();
                        const role = (row.getAttribute('data-role') || '').toLowerCase().trim();
                        const okName = !q || name.includes(q);
                        const okRole = !r || role === r;
                        row.style.display = (okName && okRole) ? '' : 'none';
                    });

                    // อัปเดตการแสดงผล Hint
                    if (hintEl) {
                        if (r === suggestRole && r !== '') {
                            hintEl.classList.remove('hidden');
                        } else {
                            hintEl.classList.add('hidden');
                        }
                    }

                    modal.querySelectorAll('[data-role-group]').forEach(group => {
                        const inner = Array.from(group.querySelectorAll('.assign-user-row'));
                        group.style.display = inner.some(x => x.style.display !== 'none') ? '' : 'none';
                    });
                    updateCounts();
                }

                // --- 4. ควบคุมการแสดงผล Modal ---

                function showModal() {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.style.overflow = 'hidden';

                    // คืนค่า Checkbox ตามค่าเริ่มต้นใน HTML (ช่วยกรณีผู้ใช้กด "ล้าง" แต่ไม่ได้กดบันทึกแล้วปิดหน้าต่างไป)
                    getAllCheckboxes().forEach(cb => {
                        cb.checked = cb.defaultChecked;
                    });

                    // ล้างช่องค้นหา
                    if (searchInput) searchInput.value = '';

                    // รีเซ็ตตัวกรองตำแหน่งกลับไปที่ทีมที่แนะนำ (หรือ "ทั้งหมด" หากไม่มีคำแนะนำ)
                    if (roleFilter) {
                        if (suggestRole) {
                            const hasOption = Array.from(roleFilter.options)
                                .some(opt => (opt.value || '').toLowerCase().trim() === suggestRole);
                            roleFilter.value = hasOption ? suggestRole : '';
                        } else {
                            roleFilter.value = '';
                        }
                    }

                    applyFilter();
                }

                function hideModal() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.body.style.overflow = '';
                }

                // --- 5. Event Listeners ---

                open.addEventListener('click', showModal);
                close?.addEventListener('click', hideModal);
                cancel?.addEventListener('click', hideModal);

                // คลิกพื้นหลังเทาๆ ให้ปิด Modal
                modal.addEventListener('click', e => {
                    if (e.target === modal) hideModal();
                });

                // ปุ่มเลือกทั้งหมด (เฉพาะที่เห็นอยู่)
                selectAllBtn?.addEventListener('click', () => {
                    getVisibleCheckboxes().forEach(cb => cb.checked = true);
                    updateCounts();
                });

                // ล้างที่เลือกไว้ทั้งหมด
                clearAllBtn?.addEventListener('click', () => {
                    getAllCheckboxes().forEach(cb => cb.checked = false);
                    updateCounts();
                });

                searchInput?.addEventListener('input', applyFilter);
                searchInput?.addEventListener('keyup', applyFilter);
                roleFilter?.addEventListener('change', applyFilter);

                // เมื่อมีการติ๊ก Checkbox ในลิสต์
                modal.addEventListener('change', e => {
                    if (e.target?.classList?.contains('assign-user-checkbox')) updateCounts();
                });

                // ลบชิปจาก Sidebar
                selectedListEl?.addEventListener('click', e => {
                    const btn = e.target.closest('.assign-chip-remove');
                    if (!btn) return;
                    const userId = btn.dataset.userId;
                    const cb = modal.querySelector(`.assign-user-checkbox[value="${CSS.escape(userId)}"]`);
                    if (cb) cb.checked = false;
                    updateCounts();
                });

                // จัดการตอนกด Submit
                assignForm?.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML =
                            '<span class="animate-spin mr-2 text-[14px]">◌</span> กำลังบันทึก...';
                    }
                });

                // รันครั้งแรกเพื่อตั้งค่าเริ่มต้น
                updateCounts();
            }

            // --- 6. Reject Modal Logic (หน้าต่างปฏิเสธงาน) ---
            const rejectModal = document.getElementById('rejectModal');
            const openRejectBtn = document.getElementById('openRejectModalBtn');
            const closeRejectBtn = document.getElementById('closeRejectModalBtn');
            const cancelRejectBtn = document.getElementById('cancelRejectModalBtn');

            if (rejectModal && openRejectBtn) {
                const showReject = () => {
                    rejectModal.classList.remove('hidden');
                    rejectModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideReject = () => {
                    rejectModal.classList.add('hidden');
                    rejectModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openRejectBtn.addEventListener('click', showReject);
                closeRejectBtn?.addEventListener('click', hideReject);
                cancelRejectBtn?.addEventListener('click', hideReject);
                rejectModal.addEventListener('click', e => {
                    if (e.target === rejectModal) hideReject();
                });
            }

            // --- 6.1 Cancel Modal Logic (หน้าต่างยกเลิกงาน) ---
            const cancelModal = document.getElementById('cancelModal');
            const openCancelBtn = document.getElementById('openCancelModalBtn');
            const closeCancelBtn = document.getElementById('closeCancelModalBtn');
            const cancelCancelBtn = document.getElementById('cancelCancelModalBtn');

            if (cancelModal && openCancelBtn) {
                const showCancel = () => {
                    cancelModal.classList.remove('hidden');
                    cancelModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideCancel = () => {
                    cancelModal.classList.add('hidden');
                    cancelModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openCancelBtn.addEventListener('click', showCancel);
                closeCancelBtn?.addEventListener('click', hideCancel);
                cancelCancelBtn?.addEventListener('click', hideCancel);
                cancelModal.addEventListener('click', e => {
                    if (e.target === cancelModal) hideCancel();
                });
            }

            // --- 7. Hold Modal Logic ---
            const holdModal = document.getElementById('holdModal');
            const openHoldBtn = document.getElementById('openHoldModalBtn');
            const closeHoldBtn = document.getElementById('closeHoldModalBtn');
            const cancelHoldBtn = document.getElementById('cancelHoldModalBtn');

            if (holdModal && openHoldBtn) {
                const showHold = () => {
                    holdModal.classList.remove('hidden');
                    holdModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideHold = () => {
                    holdModal.classList.add('hidden');
                    holdModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openHoldBtn.addEventListener('click', showHold);
                closeHoldBtn?.addEventListener('click', hideHold);
                cancelHoldBtn?.addEventListener('click', hideHold);
                holdModal.addEventListener('click', e => {
                    if (e.target === holdModal) hideHold();
                });
            }

            // --- 8. Resolve Modal Logic ---
            const resolveModal = document.getElementById('resolveModal');
            const openResolveBtn = document.getElementById('openResolveModalBtn');
            const closeResolveBtn = document.getElementById('closeResolveModalBtn');
            const cancelResolveBtn = document.getElementById('cancelResolveModalBtn');

            if (resolveModal && openResolveBtn) {
                const showResolve = () => {
                    resolveModal.classList.remove('hidden');
                    resolveModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideResolve = () => {
                    resolveModal.classList.add('hidden');
                    resolveModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openResolveBtn.addEventListener('click', showResolve);
                closeResolveBtn?.addEventListener('click', hideResolve);
                cancelResolveBtn?.addEventListener('click', hideResolve);
                resolveModal.addEventListener('click', e => {
                    if (e.target === resolveModal) hideResolve();
                });
            }

            // --- 9. History Modal Logic ---
            const historyModal = document.getElementById('historyModal');
            const openHistoryBtn = document.getElementById('openHistoryModalBtn');
            const closeHistoryBtn = document.getElementById('closeHistoryModalBtn');

            if (historyModal && openHistoryBtn) {
                const showHistory = () => {
                    historyModal.classList.remove('hidden');
                    historyModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideHistory = () => {
                    historyModal.classList.add('hidden');
                    historyModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openHistoryBtn.addEventListener('click', showHistory);
                closeHistoryBtn?.addEventListener('click', hideHistory);
                historyModal.addEventListener('click', e => {
                    if (e.target === historyModal) hideHistory();
                });
            }
        })();
    </script>
