"use strict";

/* ------------------------------------------------------------------ *
 * Tiny helpers
 * ------------------------------------------------------------------ */
const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));

const api = {
  async req(method, url, data) {
    const opts = { method, headers: {} };
    if (data !== undefined) {
      opts.headers["Content-Type"] = "application/json";
      opts.body = JSON.stringify(data);
    }
    const res = await fetch("/api" + url, opts);
    let json = {};
    try { json = await res.json(); } catch (_) {}
    if (!res.ok) throw new Error(json.error || "Terjadi kesalahan");
    return json;
  },
  get:  (u)    => api.req("GET", u),
  post: (u, d) => api.req("POST", u, d),
  put:  (u, d) => api.req("PUT", u, d),
  del:  (u)    => api.req("DELETE", u),
};

function toast(msg, type = "success") {
  const el = $("#toast");
  el.textContent = msg;
  el.className = "toast " + type;
  el.classList.remove("hidden");
  clearTimeout(toast._t);
  toast._t = setTimeout(() => el.classList.add("hidden"), 2600);
}

function initials(name) {
  const p = name.trim().split(/\s+/);
  return ((p[0]?.[0] || "") + (p[1]?.[0] || "")).toUpperCase() || "?";
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

/* ------------------------------------------------------------------ *
 * State
 * ------------------------------------------------------------------ */
let members = [];
let orgName = "Struktur Organisasi";
let zoom = 1;

/* ------------------------------------------------------------------ *
 * Auth flow
 * ------------------------------------------------------------------ */
async function boot() {
  try {
    const s = await api.get("/session");
    if (s.authenticated) return showApp();
  } catch (_) {}
  showLogin();
}

function showLogin() {
  $("#appView").classList.add("hidden");
  $("#loginView").classList.remove("hidden");
}

async function showApp() {
  $("#loginView").classList.add("hidden");
  $("#appView").classList.remove("hidden");
  await Promise.all([loadSettings(), loadMembers()]);
}

$("#loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const btn = $("#loginBtn");
  const err = $("#loginError");
  err.classList.add("hidden");
  btn.disabled = true;
  btn.textContent = "Memproses...";
  try {
    await api.post("/login", {
      username: $("#username").value.trim(),
      password: $("#password").value,
    });
    await showApp();
    $("#loginForm").reset();
  } catch (ex) {
    err.textContent = ex.message;
    err.classList.remove("hidden");
  } finally {
    btn.disabled = false;
    btn.textContent = "Masuk";
  }
});

$("#logoutBtn").addEventListener("click", async () => {
  try { await api.post("/logout"); } catch (_) {}
  showLogin();
});

/* ------------------------------------------------------------------ *
 * Data loading
 * ------------------------------------------------------------------ */
async function loadSettings() {
  const s = await api.get("/settings");
  orgName = s.org_name || "Struktur Organisasi";
  $("#orgTitle").textContent = orgName;
  $("#chartTitle").textContent = orgName;
  document.title = orgName;
}

async function loadMembers() {
  members = await api.get("/members");
  renderChart();
  renderTable();
}

/* ------------------------------------------------------------------ *
 * Org chart
 * ------------------------------------------------------------------ */
function buildTree() {
  const map = new Map();
  members.forEach((m) => map.set(m.id, { ...m, children: [] }));
  const roots = [];
  map.forEach((node) => {
    if (node.parent_id && map.has(node.parent_id)) {
      map.get(node.parent_id).children.push(node);
    } else {
      roots.push(node);
    }
  });
  const sortRec = (arr) => {
    arr.sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);
    arr.forEach((n) => sortRec(n.children));
  };
  sortRec(roots);
  return roots;
}

function nodeHtml(node) {
  const avatar = node.photo
    ? `<div class="avatar"><img src="${escapeHtml(node.photo)}" alt="" onerror="this.parentNode.textContent='${initials(node.name)}'"></div>`
    : `<div class="avatar">${initials(node.name)}</div>`;
  const kids = node.children.length
    ? `<ul>${node.children.map((c) => `<li>${nodeHtml(c)}</li>`).join("")}</ul>`
    : "";
  return `
    <div class="node-wrap">
      <div class="node" title="${escapeHtml(node.position)}">
        ${avatar}
        <div class="n-name">${escapeHtml(node.name)}</div>
        <div class="n-pos">${escapeHtml(node.position)}</div>
      </div>
      ${kids}
    </div>`;
}

function renderChart() {
  const chart = $("#chart");
  const empty = $("#chartEmpty");
  const roots = buildTree();

  if (!roots.length) {
    chart.innerHTML = "";
    empty.classList.remove("hidden");
    return;
  }
  empty.classList.add("hidden");

  // Multiple roots are wrapped so connectors render correctly.
  chart.innerHTML =
    roots.length === 1
      ? nodeHtml(roots[0])
      : `<ul>${roots.map((r) => `<li>${nodeHtml(r)}</li>`).join("")}</ul>`;
  applyZoom();
  centerChart();
}

// The chart is centered, so wide trees start scrolled to the middle
// where the root node sits.
function centerChart() {
  requestAnimationFrame(() => {
    const scroll = $(".chart-scroll");
    if (scroll) scroll.scrollLeft = (scroll.scrollWidth - scroll.clientWidth) / 2;
  });
}

function applyZoom() {
  $("#chart").style.transform = `scale(${zoom})`;
  $("#zoomLabel").textContent = Math.round(zoom * 100) + "%";
}
$("#zoomIn").addEventListener("click", () => { zoom = Math.min(1.6, zoom + 0.1); applyZoom(); });
$("#zoomOut").addEventListener("click", () => { zoom = Math.max(0.5, zoom - 0.1); applyZoom(); });

/* ------------------------------------------------------------------ *
 * Table (Kelola Data)
 * ------------------------------------------------------------------ */
function renderTable() {
  const body = $("#membersBody");
  const empty = $("#tableEmpty");
  const nameById = new Map(members.map((m) => [m.id, m.name]));

  if (!members.length) {
    body.innerHTML = "";
    empty.classList.remove("hidden");
    return;
  }
  empty.classList.add("hidden");

  const ordered = [...members].sort(
    (a, b) => a.sort_order - b.sort_order || a.id - b.id
  );

  body.innerHTML = ordered
    .map((m) => {
      const avatar = m.photo
        ? `<div class="cell-avatar"><img src="${escapeHtml(m.photo)}" alt="" onerror="this.parentNode.textContent='${initials(m.name)}'"></div>`
        : `<div class="cell-avatar">${initials(m.name)}</div>`;
      const parent = m.parent_id
        ? escapeHtml(nameById.get(m.parent_id) || "—")
        : '<span class="muted">— Puncak —</span>';
      return `
      <tr>
        <td><div class="cell-name">${avatar}<span>${escapeHtml(m.name)}</span></div></td>
        <td><span class="badge">${escapeHtml(m.position)}</span></td>
        <td>${parent}</td>
        <td>${m.sort_order}</td>
        <td>
          <div class="row-actions">
            <button class="btn btn-ghost btn-sm" data-edit="${m.id}">Ubah</button>
            <button class="btn btn-danger btn-sm" data-del="${m.id}">Hapus</button>
          </div>
        </td>
      </tr>`;
    })
    .join("");
}

$("#membersBody").addEventListener("click", (e) => {
  const editId = e.target.getAttribute("data-edit");
  const delId = e.target.getAttribute("data-del");
  if (editId) openModal(Number(editId));
  if (delId) removeMember(Number(delId));
});

async function removeMember(id) {
  const m = members.find((x) => x.id === id);
  const hasKids = members.some((x) => x.parent_id === id);
  const warn = hasKids
    ? "\n\nPeringatan: seluruh bawahannya juga akan terhapus."
    : "";
  if (!confirm(`Hapus "${m?.name}"?${warn}`)) return;
  try {
    await api.del("/members/" + id);
    toast("Anggota dihapus");
    await loadMembers();
  } catch (ex) {
    toast(ex.message, "error");
  }
}

/* ------------------------------------------------------------------ *
 * Modal (add / edit member)
 * ------------------------------------------------------------------ */
function fillParentOptions(excludeId) {
  const sel = $("#mParent");
  const opts = ['<option value="">— Puncak (tanpa atasan) —</option>'];
  // Prevent choosing self or a descendant as parent.
  const banned = new Set();
  if (excludeId) {
    banned.add(excludeId);
    let changed = true;
    while (changed) {
      changed = false;
      members.forEach((m) => {
        if (m.parent_id && banned.has(m.parent_id) && !banned.has(m.id)) {
          banned.add(m.id);
          changed = true;
        }
      });
    }
  }
  members
    .filter((m) => !banned.has(m.id))
    .sort((a, b) => a.position.localeCompare(b.position))
    .forEach((m) => {
      opts.push(
        `<option value="${m.id}">${escapeHtml(m.name)} — ${escapeHtml(m.position)}</option>`
      );
    });
  sel.innerHTML = opts.join("");
}

function openModal(id = null) {
  const isEdit = id !== null;
  $("#modalTitle").textContent = isEdit ? "Ubah Anggota" : "Tambah Anggota";
  $("#formError").classList.add("hidden");
  fillParentOptions(id);

  if (isEdit) {
    const m = members.find((x) => x.id === id);
    $("#memberId").value = m.id;
    $("#mName").value = m.name;
    $("#mPosition").value = m.position;
    $("#mParent").value = m.parent_id ?? "";
    $("#mSort").value = m.sort_order;
    $("#mPhoto").value = m.photo ?? "";
  } else {
    $("#memberForm").reset();
    $("#memberId").value = "";
    $("#mSort").value = 0;
  }
  $("#modal").classList.remove("hidden");
  $("#mName").focus();
}

function closeModal() { $("#modal").classList.add("hidden"); }

$("#addBtn").addEventListener("click", () => openModal());
$("#goManage").addEventListener("click", () => { switchTab("manage"); openModal(); });
$("#modalClose").addEventListener("click", closeModal);
$("#cancelBtn").addEventListener("click", closeModal);
$("#modal").addEventListener("click", (e) => { if (e.target.id === "modal") closeModal(); });

$("#memberForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const err = $("#formError");
  err.classList.add("hidden");
  const id = $("#memberId").value;
  const payload = {
    name: $("#mName").value.trim(),
    position: $("#mPosition").value.trim(),
    parent_id: $("#mParent").value || null,
    sort_order: Number($("#mSort").value) || 0,
    photo: $("#mPhoto").value.trim(),
  };
  const btn = $("#saveBtn");
  btn.disabled = true;
  try {
    if (id) await api.put("/members/" + id, payload);
    else await api.post("/members", payload);
    closeModal();
    toast(id ? "Perubahan disimpan" : "Anggota ditambahkan");
    await loadMembers();
  } catch (ex) {
    err.textContent = ex.message;
    err.classList.remove("hidden");
  } finally {
    btn.disabled = false;
  }
});

/* ------------------------------------------------------------------ *
 * Org name modal
 * ------------------------------------------------------------------ */
$("#editOrgBtn").addEventListener("click", () => {
  $("#orgNameInput").value = orgName;
  $("#orgModal").classList.remove("hidden");
  $("#orgNameInput").focus();
});
const closeOrg = () => $("#orgModal").classList.add("hidden");
$("#orgClose").addEventListener("click", closeOrg);
$("#orgCancel").addEventListener("click", closeOrg);
$("#orgModal").addEventListener("click", (e) => { if (e.target.id === "orgModal") closeOrg(); });

$("#orgForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  try {
    await api.put("/settings", { org_name: $("#orgNameInput").value.trim() });
    closeOrg();
    await loadSettings();
    toast("Nama organisasi diperbarui");
  } catch (ex) {
    toast(ex.message, "error");
  }
});

/* ------------------------------------------------------------------ *
 * Tabs
 * ------------------------------------------------------------------ */
function switchTab(name) {
  $$(".tab").forEach((t) => t.classList.toggle("active", t.dataset.tab === name));
  $("#dashboardTab").classList.toggle("hidden", name !== "dashboard");
  $("#manageTab").classList.toggle("hidden", name !== "manage");
}
$$(".tab").forEach((t) => t.addEventListener("click", () => switchTab(t.dataset.tab)));

// Esc closes modals
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") { closeModal(); closeOrg(); }
});

/* ------------------------------------------------------------------ *
 * Go
 * ------------------------------------------------------------------ */
boot();
