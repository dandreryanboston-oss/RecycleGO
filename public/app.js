/* RecycleGO Frontend – vanilla JS */
const $ = (sel) => document.querySelector(sel);
const API = () => window.RG_API_BASE || '/api';

const ui = {
  auth: $('#rg_auth'),
  loginForm: $('#rg_login_form'),
  loginEmail: $('#rg_login_email'),
  loginPass: $('#rg_login_pass'),
  regForm: $('#rg_register_form'),
  regName: $('#rg_reg_name'),
  regEmail: $('#rg_reg_email'),
  regPass: $('#rg_reg_pass'),

  avatar: $('#rg_user_avatar'),
  userName: $('#rg_user_name'),
  points: $('#rg_points_value'),
  monthKg: $('#rg_month_kg'),

  logsList: $('#rg_logs_list'),
  medalsGrid: $('#rg_medals_grid'),
  leaderList: $('#rg_leader_list'),
  tipText: $('#rg_tip_text'),

  modal: $('#rg_modal'),
  modalClose: $('#rg_modal_close'),
  formLog: $('#rg_form_log'),
  mat: $('#rg_material'),
  weight: $('#rg_weight'),

  addQuick: $('#rg_add_quick'),
  logBtn: $('#rg_log_btn'),
  medalsBtn: $('#rg_medals_btn'),
  leaderBtn: $('#rg_leader_btn'),

  ring: $('#rg_ring_fg'),
};

const store = {
  set token(v) { localStorage.setItem('rg_token', v); },
  get token() { return localStorage.getItem('rg_token'); },
  clear() { localStorage.removeItem('rg_token'); }
};

function authHeader() {
  return store.token ? { 'Authorization': 'Bearer ' + store.token } : {};
}

async function api(path, opt = {}) {
  const res = await fetch(API() + path, {
    ...opt,
    headers: { 'Content-Type': 'application/json', ...(opt.headers || {}), ...authHeader() }
  });
  if (!res.ok) {
    const err = await res.json().catch(()=>({error:res.statusText}));
    throw new Error(err.error || 'Request failed');
  }
  return res.json();
}

/* Init */
document.addEventListener('DOMContentLoaded', async () => {
  bindUI();

  if (!store.token) {
    ui.auth.classList.add('show');
  } else {
    await loadAll();
  }
});

function bindUI() {
  // Auth
  ui.loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const { token, user } = await api('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email: ui.loginEmail.value, password: ui.loginPass.value })
      });
      store.token = token;
      ui.auth.classList.remove('show');
      await afterLogin(user);
    } catch (err) { alert(err.message); }
  });

  ui.regForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const { token, user } = await api('/auth/register', {
        method: 'POST',
        body: JSON.stringify({ name: ui.regName.value, email: ui.regEmail.value, password: ui.regPass.value })
      });
      store.token = token;
      ui.auth.classList.remove('show');
      await afterLogin(user);
    } catch (err) { alert(err.message); }
  });

  // Modal
  const openModal = () => ui.modal.classList.add('show');
  const closeModal = () => ui.modal.classList.remove('show');

  ui.logBtn.addEventListener('click', openModal);
  ui.addQuick.addEventListener('click', openModal);
  ui.modalClose.addEventListener('click', closeModal);
  ui.modal.addEventListener('click', (e) => { if (e.target === ui.modal) closeModal(); });

  ui.formLog.addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
      await api('/logs', { method: 'POST', body: JSON.stringify({ material: ui.mat.value, weight_kg: parseFloat(ui.weight.value) }) });
      ui.weight.value = '';
      closeModal();
      await loadAll();
    } catch (err) { alert(err.message); }
  });

  ui.medalsBtn.addEventListener('click', ()=>document.getElementById('rg_medals_card').scrollIntoView({behavior:'smooth'}));
  ui.leaderBtn.addEventListener('click', ()=>document.getElementById('rg_leader_card').scrollIntoView({behavior:'smooth'}));
}

async function afterLogin(user) {
  // Lightweight personalization
  ui.userName.textContent = user.name || 'Friend';
  if (user.avatar_url) ui.avatar.src = user.avatar_url;
  await loadAll();
}

async function loadAll() {
  try {
    const me = await api('/users/me');
    ui.userName.textContent = me.user.name;
    ui.avatar.src = me.user.avatar_url;

    const tips = await api('/tips/random');
    ui.tipText.textContent = tips.tip.text;

    const stats = await api('/stats/summary');
    ui.monthKg.textContent = (stats.month_kg || 0).toFixed(1);
    ui.points.textContent = stats.points || 0;
    paintRing(stats.month_kg, 10); // goal 10 kg

    const logs = await api('/logs');
    paintLogs(logs.logs);

    const medals = await api('/medals');
    paintMedals(medals.medals);

    const lead = await api('/leaderboard');
    paintLeaders(lead.leaders);
  } catch (err) {
    console.error(err);
    store.clear();
    ui.auth.classList.add('show');
  }
}

function paintRing(value, goal) {
  const circumference = 2 * Math.PI * 52; // matches stroke-dasharray in CSS (≈326)
  const pct = Math.max(0, Math.min(1, value/goal));
  const offset = circumference * (1 - pct);
  ui.ring.style.strokeDashoffset = offset;
}

function paintLogs(items) {
  ui.logsList.innerHTML = '';
  if (!items.length) {
    ui.logsList.innerHTML = '<li class="rg-row">No logs yet – add your first! 🌿</li>';
    return;
  }
  items.forEach(i=>{
    const li = document.createElement('li');
    li.className = 'rg-row';
    li.innerHTML = `<div><strong>${i.material}</strong> • <span>${new Date(i.created_at).toLocaleString()}</span></div>
                    <div><strong>${Number(i.weight_kg).toFixed(2)} kg</strong></div>`;
    ui.logsList.appendChild(li);
  });
}

function paintMedals(items) {
  ui.medalsGrid.innerHTML = '';
  items.forEach(m=>{
    const el = document.createElement('div');
    el.className = 'rg-medal';
    el.innerHTML = `<span class="rg-med-ico">${m.icon}</span>
                    <div class="rg-med-name">${m.name}</div>
                    <div class="rg-med-th">${m.earned ? 'Earned ✅' : `Goal: ${Number(m.threshold_kg).toFixed(0)} kg`}</div>`;
    ui.medalsGrid.appendChild(el);
  });
}

function paintLeaders(items) {
  ui.leaderList.innerHTML = '';
  items.forEach(u=>{
    const li = document.createElement('li');
    li.innerHTML = `<div style="display:flex;align-items:center;gap:10px">
                      <img src="${u.avatar_url || 'https://i.pravatar.cc/48?u='+encodeURIComponent(u.name)}"
                           alt="" style="width:28px;height:28px;border-radius:50%;box-shadow:0 6px 14px rgba(0,0,0,.35)">
                      <strong>${u.name}</strong>
                    </div>
                    <div>${Number(u.total_kg).toFixed(1)} kg</div>`;
    ui.leaderList.appendChild(li);
  });
}
