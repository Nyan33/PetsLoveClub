<?php
// Expects: $userPets (may be empty), $viewer (may be null)
$_userPets   = $userPets ?? [];
$_loggedIn   = !empty($viewer);
$_restricted = $_loggedIn && is_restricted($viewer);
$_restrictReason = $_loggedIn ? restricted_reason($viewer) : null;
?>
<!-- Signup modal -->
<div id="signup-modal" class="hidden fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm items-center justify-center px-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative animate-fade-in">
        <button type="button" onclick="closeModal('signup-modal')" class="btn-hover absolute top-4 right-4 w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
            <i data-lucide="x" class="w-5 h-5 text-gray-600"></i>
        </button>
        <div class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center mb-4">
            <i data-lucide="calendar-plus" class="w-7 h-7 text-rose-500"></i>
        </div>
        <h2 class="text-2xl font-black text-gray-900 mb-1">Запись на событие</h2>
        <p class="text-gray-500 font-medium mb-5" id="signup-event-title"></p>

        <?php if (!$_loggedIn): ?>
            <p class="text-gray-700 mb-5">Чтобы записаться, войдите в аккаунт.</p>
            <a href="login.php" class="btn-hover block w-full text-center px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">Войти</a>
        <?php elseif ($_restricted): ?>
            <p class="text-gray-700 mb-5"><?= htmlspecialchars(restricted_message($viewer, 'запись на события')) ?></p>
            <?php if ($_restrictReason === 'unverified'): ?>
                <a href="verify.php" class="btn-hover block w-full text-center px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">Подтвердить email</a>
            <?php endif; ?>
        <?php elseif (empty($_userPets)): ?>
            <p class="text-gray-700 mb-5">У вас ещё нет добавленных питомцев - добавьте хотя бы одного, чтобы записаться на событие.</p>
            <a href="profile.php" class="btn-hover block w-full text-center px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">Перейти в профиль</a>
        <?php else: ?>
            <form method="post" action="events.php" class="space-y-4">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="event_id" id="signup-event-id">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Выберите питомца</label>
                    <select name="pet_id" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-rose-400 focus:outline-none transition-colors bg-white font-bold text-gray-700">
                        <?php foreach ($_userPets as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('signup-modal')" class="btn-hover flex-1 px-6 py-3 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200">Отмена</button>
                    <button type="submit" class="btn-hover flex-1 px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">Записаться</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Participants modal -->
<div id="participants-modal" class="hidden fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm items-center justify-center px-3 sm:px-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full relative animate-fade-in flex flex-col" style="max-height: calc(100dvh - 2rem);">
        <button type="button" onclick="closeModal('participants-modal')" class="btn-hover absolute top-4 right-4 w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center z-10">
            <i data-lucide="x" class="w-5 h-5 text-gray-600"></i>
        </button>
        <div class="p-6 sm:p-7 pb-3 flex-shrink-0">
            <div class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center mb-4">
                <i data-lucide="users" class="w-7 h-7 text-rose-500"></i>
            </div>
            <h2 class="text-2xl font-black text-gray-900 mb-1">Участники</h2>
            <p class="text-gray-500 font-medium" id="participants-event-title"></p>
            <p class="text-xs text-gray-500 mt-2" id="participants-status"></p>
        </div>
        <div class="px-5 sm:px-6 pb-6 sm:pb-7 flex-1 min-h-0 overflow-y-auto">
            <div id="participants-list" class="space-y-2"></div>
        </div>
    </div>
</div>

<!-- Cancel modal -->
<div id="cancel-modal" class="hidden fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm items-center justify-center px-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative animate-fade-in">
        <button type="button" onclick="closeModal('cancel-modal')" class="btn-hover absolute top-4 right-4 w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
            <i data-lucide="x" class="w-5 h-5 text-gray-600"></i>
        </button>
        <div class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center mb-4">
            <i data-lucide="calendar-x" class="w-7 h-7 text-rose-500"></i>
        </div>
        <h2 class="text-2xl font-black text-gray-900 mb-1">Отменить запись?</h2>
        <p class="text-gray-500 font-medium mb-2" id="cancel-event-title"></p>
        <p class="text-sm text-gray-500 mb-5">Питомец: <span class="font-bold text-gray-700" id="cancel-pet-name">-</span></p>

        <form method="post" action="<?= basename($_SERVER['SCRIPT_NAME']) === 'profile.php' ? 'profile.php?u=' . urlencode($viewer['username']) . '&tab=events' : 'events.php' ?>" class="space-y-3">
            <input type="hidden" name="action" id="cancel-action" value="unregister">
            <input type="hidden" name="event_id" id="cancel-event-id">
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('cancel-modal')" class="btn-hover flex-1 px-6 py-3 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200">Не отменять</button>
                <button type="submit" class="btn-hover flex-1 px-6 py-3 bg-rose-400 text-white font-bold rounded-xl hover:bg-rose-500">Да, отменить</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
    if (window.lucide) lucide.createIcons();
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
}
function openSignupModal(eventId, eventTitle) {
    const idInput = document.getElementById('signup-event-id');
    if (idInput) idInput.value = eventId;
    const t = document.getElementById('signup-event-title');
    if (t) t.textContent = eventTitle || '';
    openModal('signup-modal');
}
function openCancelModal(eventId, eventTitle, petName, action) {
    document.getElementById('cancel-event-id').value = eventId;
    document.getElementById('cancel-event-title').textContent = eventTitle || '';
    document.getElementById('cancel-pet-name').textContent = petName || '-';
    if (action) document.getElementById('cancel-action').value = action;
    openModal('cancel-modal');
}
document.addEventListener('click', function(e) {
    const m = e.target.closest && e.target;
    ['signup-modal', 'cancel-modal', 'participants-modal'].forEach(id => {
        const el = document.getElementById(id);
        if (el && e.target === el) closeModal(id);
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('signup-modal');
        closeModal('cancel-modal');
        closeModal('participants-modal');
    }
});

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function openParticipantsModal(eventId) {
    const dataEl = document.getElementById('participants-data');
    if (!dataEl) return;
    let all;
    try { all = JSON.parse(dataEl.textContent || '{}'); } catch (e) { all = {}; }
    const data = all[String(eventId)] || all[eventId];
    if (!data) return;

    document.getElementById('participants-event-title').textContent = data.title || '';
    const statusEl = document.getElementById('participants-status');
    const listEl   = document.getElementById('participants-list');

    if (data.is_completed) {
        statusEl.textContent = 'Событие завершено - голосование закрыто.';
        statusEl.className = 'text-xs text-gray-600 font-bold mt-2';
    } else if (!data.logged_in) {
        statusEl.textContent = 'Чтобы голосовать, войдите в аккаунт.';
        statusEl.className = 'text-xs text-rose-500 font-bold mt-2';
    } else if (data.restricted) {
        statusEl.textContent = data.restrict_reason === 'unverified'
            ? 'Голосование недоступно - подтвердите email.'
            : 'Голосование недоступно - аккаунт заблокирован.';
        statusEl.className = 'text-xs text-rose-500 font-bold mt-2';
    } else {
        statusEl.textContent = 'Поддержите одного участника. Можно отозвать голос или сменить выбор.';
        statusEl.className = 'text-xs text-gray-500 mt-2';
    }

    listEl.innerHTML = '';
    if (!data.participants.length) {
        listEl.innerHTML = '<p class="text-sm text-gray-400 py-8 text-center">Пока нет участников.</p>';
    } else {
        const myPetIds  = new Set((data.my_pet_ids || []).map(Number));
        const mySupport = data.my_support;
        const winnerId  = data.winner_pet_id;

        data.participants.forEach(p => {
            const isWinner   = (winnerId && Number(winnerId) === Number(p.pet_id)) || p.is_winner;
            const isMine     = myPetIds.has(Number(p.pet_id));
            const supported  = mySupport && Number(mySupport) === Number(p.pet_id);

            let btnHtml = '';
            if (data.is_completed) {
                btnHtml = `<div class="flex flex-col items-center justify-center min-w-[3rem] px-2"><div class="text-lg font-black text-gray-700">${p.support_count}</div><div class="text-[10px] text-gray-500 font-bold uppercase">голос${p.support_count===1?'':(p.support_count>=2&&p.support_count<=4?'а':'ов')}</div></div>`;
            } else if (!data.logged_in || data.restricted || isMine) {
                const cls = isMine ? 'bg-gray-100 text-gray-400' : 'bg-gray-100 text-gray-500';
                let tip = 'Войдите для голосования';
                if (isMine) tip = 'Нельзя голосовать за своего питомца';
                else if (data.restrict_reason === 'banned') tip = 'Аккаунт заблокирован';
                else if (data.restrict_reason === 'unverified') tip = 'Подтвердите email для голосования';
                btnHtml = `<div title="${escapeHtml(tip)}" class="flex flex-col items-center justify-center min-w-[3rem] px-2 py-1 rounded-xl ${cls}"><i data-lucide="arrow-up" class="w-4 h-4"></i><div class="text-sm font-black">${p.support_count}</div></div>`;
            } else {
                const cls = supported
                    ? 'bg-rose-500 text-white hover:bg-rose-600'
                    : 'bg-rose-50 text-rose-500 hover:bg-rose-100';
                btnHtml = `<button type="button" data-support-btn data-event-id="${eventId}" data-pet-id="${p.pet_id}" class="btn-hover flex flex-col items-center justify-center min-w-[3rem] px-2 py-1.5 rounded-xl ${cls} font-bold">
                    <i data-lucide="arrow-up" class="w-4 h-4"></i>
                    <span class="text-sm" data-support-count="${p.pet_id}">${p.support_count}</span>
                </button>`;
            }

            const avatar = p.photo_url
                ? `<img src="${escapeHtml(p.photo_url)}" alt="" class="w-12 h-12 rounded-xl object-cover flex-shrink-0">`
                : `<div class="w-12 h-12 rounded-xl bg-rose-200 flex items-center justify-center text-rose-600 font-bold flex-shrink-0">${escapeHtml((p.pet_name||'?').slice(0,1).toUpperCase())}</div>`;

            const crown = isWinner
                ? `<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 text-amber-600 ml-1" title="Победитель"><i data-lucide="crown" class="w-3.5 h-3.5"></i></span>`
                : '';

            const ring = isWinner ? 'ring-2 ring-amber-300 bg-amber-50/50' : 'bg-gray-50';

            listEl.insertAdjacentHTML('beforeend',
                `<div class="flex items-center gap-3 p-2.5 rounded-xl ${ring}">
                    ${avatar}
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-900 text-sm truncate flex items-center">${escapeHtml(p.pet_name)}${crown}</p>
                        <p class="text-xs text-gray-500 truncate">${escapeHtml(p.pet_breed)}</p>
                        <a href="profile.php?u=${encodeURIComponent(p.username)}" class="btn-hover text-xs text-rose-500 font-bold hover:text-rose-700 inline-flex items-center gap-1 mt-0.5">
                            <i data-lucide="user" class="w-3 h-3"></i>
                            @${escapeHtml(p.username)}
                        </a>
                    </div>
                    ${btnHtml}
                </div>`
            );
        });
    }

    openModal('participants-modal');
}

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-support-btn]');
    if (!btn) return;
    e.preventDefault();
    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';
    const eventId = btn.dataset.eventId;
    const petId   = btn.dataset.petId;
    try {
        const fd = new FormData();
        fd.append('action', 'support');
        fd.append('event_id', eventId);
        fd.append('pet_id', petId);
        const res = await fetch('events.php', {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.ok) return;

        const dataEl = document.getElementById('participants-data');
        if (dataEl) {
            let all;
            try { all = JSON.parse(dataEl.textContent || '{}'); } catch (err) { all = {}; }
            const ev = all[String(eventId)] || all[eventId];
            if (ev) {
                ev.my_support = data.my_support;
                if (Array.isArray(ev.participants)) {
                    ev.participants.forEach(p => {
                        if (data.counts && Object.prototype.hasOwnProperty.call(data.counts, p.pet_id)) {
                            p.support_count = data.counts[p.pet_id];
                        }
                    });
                }
            }
            dataEl.textContent = JSON.stringify(all);
        }

        document.querySelectorAll(`[data-support-btn][data-event-id="${eventId}"]`).forEach(b => {
            const pid = b.dataset.petId;
            const supported = data.my_support && Number(data.my_support) === Number(pid);
            b.className = 'btn-hover flex flex-col items-center justify-center min-w-[3rem] px-2 py-1.5 rounded-xl font-bold ' +
                (supported ? 'bg-rose-500 text-white hover:bg-rose-600' : 'bg-rose-50 text-rose-500 hover:bg-rose-100');
            const cnt = b.querySelector(`[data-support-count="${pid}"]`);
            if (cnt && data.counts && Object.prototype.hasOwnProperty.call(data.counts, pid)) {
                cnt.textContent = data.counts[pid];
            }
        });
    } finally {
        btn.dataset.busy = '';
    }
});
</script>
