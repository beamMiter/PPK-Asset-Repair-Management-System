(function () {
  function $(id) { return document.getElementById(id); }

  function showLoader() { $("loaderOverlay")?.classList.add("show"); }
  function hideLoader() { $("loaderOverlay")?.classList.remove("show"); }

  async function submitAcknowledge(id, ticketNo) {
    const ok = await window.Confirm.show({
      title: 'ยืนยันรับทราบงาน',
      message: `ยืนยัน “รับทราบ” งาน #${ticketNo} หรือไม่?`,
      variant: 'primary'
    });
    if (!ok) return;
    showLoader();
    const form = $(`ackForm-${id}`);
    if (form) form.submit();
  }

  async function submitAccept(id, ticketNo) {
    const ok = await window.Confirm.show({
      title: 'ยืนยันรับเรื่อง',
      message: `ยืนยัน “รับเรื่อง” งาน #${ticketNo} เข้าดำเนินการหรือไม่?`,
      variant: 'success'
    });
    if (!ok) return;
    showLoader();
    const form = $(`acceptForm-${id}`);
    if (form) form.submit();
  }

  async function submitReject(id, ticketNo) {
    const remark = prompt(`ระบุเหตุผล “ไม่รับเรื่อง” งาน #${ticketNo} (ไม่กรอกก็ได้)`, "");
    if (remark === null) return;

    const ok = await window.Confirm.show({
      title: 'ยืนยันไม่รับเรื่อง',
      message: `คุณแน่ใจหรือไม่ที่จะ “ไม่รับเรื่อง” งาน #${ticketNo}?`,
      variant: 'danger',
      confirmText: 'ยืนยันไม่รับเรื่อง'
    });
    if (!ok) return;

    const input = $(`rejectRemark-${id}`);
    if (input) input.value = (remark || "").trim();

    showLoader();
    const form = $(`rejectForm-${id}`);
    if (form) form.submit();
  }

  function renderDonut() {
    const getVal = (id) => parseInt(($(id)?.textContent || "0").trim(), 10) || 0;

    const pending = getVal("stat-pending");
    const ack     = getVal("stat-acknowledged");
    const accept  = getVal("stat-accepted");
    const inprog  = getVal("stat-in-progress");
    const onhold  = getVal("stat-on-hold");
    const resolved = getVal("stat-resolved");
    const closed   = getVal("stat-closed");

    const total = pending + ack + accept + inprog + onhold + resolved + closed;
    const donut = $("donut");
    const pctEl = $("donutPct");
    if (!donut || !pctEl) return;

    // Pct still total completed (Resolved + Closed)
    const completedPct = total > 0 ? Math.round(((resolved + closed) / total) * 100) : 0;
    pctEl.textContent = `${completedPct}%`;

    if (total === 0) {
      donut.style.background = "#e2e8f0";
      return;
    }

    const dPending = (pending / total) * 360;
    const dAck     = (ack / total) * 360;
    const dAccept  = (accept / total) * 360;
    const dInprog  = (inprog / total) * 360;
    const dOnhold  = (onhold / total) * 360;
    const dResolved = (resolved / total) * 360;
    const dClosed  = (closed / total) * 360;

    const a0 = 0;
    const a1 = a0 + dPending;
    const a2 = a1 + dAck;
    const a3 = a2 + dAccept;
    const a4 = a3 + dInprog;
    const a5 = a4 + dOnhold;
    const a6 = a5 + dResolved;
    const a7 = a6 + dClosed;

    donut.style.background = `conic-gradient(
      #f59e0b ${a0}deg ${a1}deg,
      #38bdf8 ${a1}deg ${a2}deg,
      #6366f1 ${a2}deg ${a3}deg,
      #3b82f6 ${a3}deg ${a4}deg,
      #94a3b8 ${a4}deg ${a5}deg,
      #10b981 ${a5}deg ${a6}deg,
      #065f46 ${a6}deg ${a7}deg,
      #e2e8f0 ${a7}deg 360deg
    )`;
  }

  const LS_KEY = "myjobs.notify.sound.enabled";

  // Turbo Drive replaces the whole <body> on every visit, so nothing here may hold on to the bell or the <audio> of the
  // page that happened to load first: the elements are looked up when they are needed, the click is caught at the
  // document, and the state is re-read from localStorage after every visit.
  const NOTIFY_BUTTONS = "#notifyToggleBtn, #notifyToggleBtnMobileTop, #notifyToggleBtnMobile";

  let soundEnabled = false;
  let audioUnlocked = false; // the browser allows audio on this document (a Turbo visit keeps it, a reload loses it)
  let pendingBeep = 0;       // beeps that arrived while the browser still refused to play

  function setNotifyUI(enabled) {
    const ids = [
      { btn: "notifyToggleBtn", icon: "notifyIcon", dot: "notifyStatusDot" },
      { btn: "notifyToggleBtnMobileTop", icon: "notifyIconMobileTop", dot: "notifyStatusDotMobileTop" },
      { btn: "notifyToggleBtnMobile", icon: null, dot: null } // icon is inside btn
    ];

    ids.forEach(group => {
      const btn = $(group.btn);
      const icon = $(group.icon);
      const dot = $(group.dot);

      if (btn) {
        btn.title = enabled ? "แจ้งเตือน (เปิดเสียงแล้ว)" : "แจ้งเตือน (กดเพื่อเปิดเสียง)";
        // For buttons without a separate icon ID, find the <i> inside
        if (!icon) {
          const innerIcon = btn.querySelector('i');
          if (innerIcon) {
            innerIcon.className = enabled ? 'bi bi-bell-fill me-2' : 'bi bi-bell-slash me-2';
          }
        }
      }

      if (icon) {
        icon.className = enabled ? 'bi bi-bell-fill' : 'bi bi-bell-slash';
      }

      if (dot) {
        dot.classList.toggle('d-none', !enabled && pendingBeep === 0);
        // We can also sync the dot style if needed
      }
    });

    const text = $("notifyText");
    if (text) text.textContent = enabled ? "เปิดเสียง" : "ปิดเสียง";
  }

  async function unlockAudio(audioEl) {
    if (!audioEl) return false;
    try {
      const prevVol = audioEl.volume;
      audioEl.volume = 0;
      await audioEl.play();
      audioEl.pause();
      audioEl.currentTime = 0;
      audioEl.volume = prevVol ?? 1;
      return true;
    } catch (e) {
      console.warn("[Notify] unlockAudio failed:", e);
      return false;
    }
  }

  function playNotifySound() {
    if (!soundEnabled) return;

    const audio = $("notifySound");
    if (!audio) {
      console.warn("[Notify] sound element not found (#notifySound)");
      return;
    }

    if (!audioUnlocked) {
      pendingBeep++;
      setNotifyUI(true);
      return;
    }

    try {
      audio.currentTime = 0;
      audio.play().catch(err => {
        console.warn("[Notify] play blocked:", err);
      });
    } catch (e) {
      console.warn("[Notify] play error:", e);
    }
  }

  // Unlock the browser's audio once (needs a user gesture). Returns whether audio is allowed now.
  async function tryUnlock() {
    if (audioUnlocked) return true;
    if (!(await unlockAudio($("notifySound")))) return false;
    audioUnlocked = true;
    return true;
  }

  function playPendingBeep() {
    if (pendingBeep === 0) return;
    pendingBeep = 0;
    playNotifySound();
  }

  function syncNotifyUI() {
    soundEnabled = localStorage.getItem(LS_KEY) === "1";
    setNotifyUI(soundEnabled);
  }

  async function toggleNotifySound() {
    if (soundEnabled && audioUnlocked) {
      soundEnabled = false;
      localStorage.setItem(LS_KEY, "0");
      setNotifyUI(false);
      return;
    }

    // Off → on. Also the case "on" was restored after a reload but the browser has not allowed audio yet:
    // this click is the gesture that unlocks it — it must not switch the sound off.
    if (!(await tryUnlock())) {
      alert("เบราว์เซอร์บล็อกเสียงอัตโนมัติ: ลองคลิกในหน้า 1 ครั้ง แล้วกดกระดิ่งอีกครั้ง");
      return;
    }

    soundEnabled = true;
    localStorage.setItem(LS_KEY, "1");
    setNotifyUI(true);
    playPendingBeep();
  }

  // After a reload the saved "on" cannot play until the user has touched the page. The first click / key press anywhere
  // (except on the bell itself, whose own handler deals with it) unlocks the audio, and a beep that was waiting plays.
  async function unlockOnFirstGesture(e) {
    if (!soundEnabled || audioUnlocked) return;
    if (e.target?.closest?.(NOTIFY_BUTTONS)) return;
    if (await tryUnlock()) playPendingBeep();
  }

  let subscribedToNewRequests = false;
  function subscribeToNewRequests() {
    if (subscribedToNewRequests) return;
    if (!$("notifySound")) return; // not a page with the bell (members have none): try again on the next visit

    if (!window.Echo) {
      console.warn("[MyJobs] Echo not found. ตรวจ resources/js/echo.js และ env (Pusher/Reverb)");
      return;
    }

    // one subscription for the whole session: it belongs to the WebSocket, not to a page
    subscribedToNewRequests = true;
    window.Echo.channel("maintenance-requests")
      .listen(".maintenance.created", (e) => {
        console.log("[MyJobs] maintenance.created", e);
        playNotifySound();
      });
  }

  // Bound once, at the document, so they survive every Turbo visit.
  document.addEventListener("click", (e) => {
    if (e.target?.closest?.(NOTIFY_BUTTONS)) toggleNotifySound();
  });
  document.addEventListener("pointerdown", unlockOnFirstGesture, true);
  document.addEventListener("keydown", unlockOnFirstGesture, true);
  // the same saved setting, switched in another tab
  window.addEventListener("storage", (e) => { if (e.key === LS_KEY) syncNotifyUI(); });

  // --- Inline "change job type" on a repair card (my-jobs page) ---------------
  // Delegated on document and bound once, so Turbo re-visits don't stack
  // duplicate listeners (which used to fire the request N times).
  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  // The <select> is replaced on screen by a TomSelect widget, which does not follow `select.value = …`.
  // Put the value back through the widget (silently, so it does not fire another `change`).
  function restoreJobType(select, typeId) {
    if (select.tomselect) select.tomselect.setValue(typeId, true);
    else select.value = typeId;
  }

  async function handleJobTypeChange(select) {
    const requestId = select.dataset.id;
    const newTypeId = select.value;
    // saved value: the markup renders data-old-type-id (this used to read a different attribute, so it was undefined)
    const oldTypeId = select.dataset.oldTypeId ?? '';

    // Try to find ticket number for confirmation
    let ticketNo = requestId;
    const card = select.closest('.bg-white.rounded-md');
    if (card) {
      const ticketElem = card.querySelector('.font-mono');
      if (ticketElem) {
        ticketNo = ticketElem.textContent.replace('#', '').trim();
      }
    }

    const confirmed = await window.Confirm.show({
      title: 'ยืนยันการเปลี่ยนประเภทงาน',
      message: `ต้องการเปลี่ยนประเภทงานสำหรับใบงาน #${ticketNo} หรือไม่?`,
      variant: 'primary'
    });

    if (!confirmed) {
      restoreJobType(select, oldTypeId);
      return;
    }

    try {
      showLoader();

      const response = await fetch(`/maintenance/requests/${requestId}/type`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          'Accept': 'application/json'
        },
        body: JSON.stringify({ type_id: newTypeId })
      });

      const result = await response.json();

      if (response.ok) {
        select.dataset.oldTypeId = newTypeId;
        // Update styles
        if (newTypeId) {
          select.classList.remove('border-slate-200', 'bg-slate-50', 'text-slate-500');
          select.classList.add('border-[#0F2D5C]/20', 'bg-[#0F2D5C]/5', 'text-[#0F2D5C]');
        } else {
          select.classList.remove('border-[#0F2D5C]/20', 'bg-[#0F2D5C]/5', 'text-[#0F2D5C]');
          select.classList.add('border-slate-200', 'bg-slate-50', 'text-slate-500');
        }

        // Show toast
        if (typeof window.showToast === 'function' && result.toast) {
          window.showToast(result.toast);
        } else if (result.toast) {
          alert(result.toast.message || 'อัปเดตประเภทงานเรียบร้อยแล้ว');
        } else {
          alert('อัปเดตประเภทงานเรียบร้อยแล้ว');
        }
      } else {
        throw new Error(result.message || result.errors?.type_id?.[0] || 'เกิดข้อผิดพลาดในการอัปเดตประเภทงาน');
      }
    } catch (error) {
      console.error('Update type error:', error);
      alert(error.message);
      restoreJobType(select, oldTypeId);
    } finally {
      hideLoader();
    }
  }

  document.addEventListener('change', (e) => {
    const select = e.target.closest?.('.job-type-select');
    if (select) handleJobTypeChange(select);
  });

  // expose to inline onclick
  window.showLoader = showLoader;
  window.hideLoader = hideLoader;
  window.submitAcknowledge = submitAcknowledge;
  window.submitAccept = submitAccept;
  window.submitReject = submitReject;

  document.addEventListener("turbo:load", () => {
    hideLoader();
    if ($("donut")) renderDonut(); // เฉพาะหน้า my-jobs

    // every visit gets a new top bar: show the saved sound state on it. The Echo subscription is made once.
    syncNotifyUI();
    subscribeToNewRequests();
  });
})();
