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

  let soundEnabled = false;
  let audioUnlocked = false;
  let pendingBeep = 0;

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

  function initRealtimeNotify() {
    const audio = $("notifySound");
    if (!audio) return;

    // restore state
    soundEnabled = localStorage.getItem(LS_KEY) === "1";
    setNotifyUI(soundEnabled);

    const toggle = async () => {
      if (soundEnabled) {
        soundEnabled = false;
        localStorage.setItem(LS_KEY, "0");
        setNotifyUI(false);
        return;
      }

      const ok = await unlockAudio(audio);
      if (!ok) {
        alert("เบราว์เซอร์บล็อกเสียงอัตโนมัติ: ลองคลิกในหน้า 1 ครั้ง แล้วกดกระดิ่งอีกครั้ง");
        return;
      }

      audioUnlocked = true;
      soundEnabled = true;
      localStorage.setItem(LS_KEY, "1");

      if (pendingBeep > 0 && audio) {
        pendingBeep = 0;
        try {
          audio.currentTime = 0;
          await audio.play();
        } catch (e) {}
      }
      setNotifyUI(true);
    };

    // Bind to all buttons
    ["notifyToggleBtn", "notifyToggleBtnMobileTop", "notifyToggleBtnMobile"].forEach(id => {
      const btn = $(id);
      if (btn) btn.addEventListener("click", toggle);
    });

    // ต้องมี Echo
    if (!window.Echo) {
      console.warn("[MyJobs] Echo not found. ตรวจ resources/js/echo.js และ env (Pusher/Reverb)");
      return;
    }

    // subscribe + listen
    window.Echo.channel("maintenance-requests")
      .listen(".maintenance.created", (e) => {
        console.log("[MyJobs] maintenance.created", e);
        playNotifySound();
      });
  }

  // --- Inline "change job type" on a repair card (my-jobs page) ---------------
  // Delegated on document and bound once, so Turbo re-visits don't stack
  // duplicate listeners (which used to fire the request N times).
  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  async function handleJobTypeChange(select) {
    const requestId = select.dataset.id;
    const newTypeId = select.value;
    const oldTypeId = select.dataset.oldValue;

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
      select.value = oldTypeId;
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
        select.dataset.oldValue = newTypeId;
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
      select.value = oldTypeId;
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

    // initRealtimeNotify ทำงานครั้งเดียว (Echo subscription ผูกกับ WebSocket ไม่ควร subscribe ซ้ำ)
    if (!window.__realtimeNotifyInit) {
      window.__realtimeNotifyInit = true;
      initRealtimeNotify();
    }
  });
})();
