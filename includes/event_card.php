<?php
// Expects: $event, $monthsShort
// Optional: $userPets, $myRegistrations (event_id => pet_name) - when set, signup/cancel UI is rendered
$ts     = strtotime($event['event_date']);
$dayNum = date('j', $ts);
$monthS = $monthsShort[(int)date('n', $ts)];

$eid          = (int)$event['id'];
$registered   = isset($myRegistrations) && isset($myRegistrations[$eid]);
$registeredPet = $registered ? $myRegistrations[$eid] : '';
$showSignup   = isset($userPets);
$loggedIn     = !empty($_SESSION['user_id']);
$titleAttr    = htmlspecialchars($event['title'], ENT_QUOTES);
$cancelActionJS = isset($cancelAction) ? ", '" . htmlspecialchars($cancelAction, ENT_QUOTES) . "'" : '';
$showParticipantsBtn = $showParticipantsBtn ?? true;
?>
<div class="bg-white rounded-2xl p-4 sm:p-6 shadow-lg btn-hover border-2 border-gray-100 hover:border-rose-200 flex flex-col">
    <div class="flex items-start gap-3 sm:gap-4 mb-4">
        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-rose-400 rounded-xl flex flex-col items-center justify-center text-white shadow-lg flex-shrink-0">
            <div class="text-[10px] sm:text-xs font-bold"><?= $monthS ?></div>
            <div class="text-xl sm:text-2xl font-black leading-none"><?= $dayNum ?></div>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="font-bold text-gray-900 mb-1 text-sm sm:text-base leading-snug break-words"><?= htmlspecialchars($event['title']) ?></h3>
            <p class="text-xs sm:text-sm text-gray-600 flex items-center gap-1 min-w-0">
                <i data-lucide="map-pin" class="w-3 h-3 flex-shrink-0"></i>
                <span class="truncate"><?= htmlspecialchars($event['location']) ?></span>
            </p>
        </div>
    </div>

    <div class="flex items-center justify-between pt-3 sm:pt-4 mt-auto border-t border-gray-100 gap-2">
        <span class="text-xs font-bold text-gray-600 whitespace-nowrap"><?= (int)$event['seats'] ?> мест</span>

        <div class="flex items-center gap-1.5">
            <?php if ($showParticipantsBtn): ?>
            <button type="button"
                    onclick="openParticipantsModal(<?= $eid ?>)"
                    title="Участники"
                    class="btn-hover w-9 h-9 bg-rose-50 text-rose-500 rounded-lg hover:bg-rose-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="users" class="w-4 h-4"></i>
            </button>
            <?php endif; ?>
            <?php if (!empty($event['is_completed']) && (int)$event['is_completed'] === 1): ?>
                <span class="px-3 py-2 bg-gray-100 text-gray-600 font-bold text-xs rounded-lg whitespace-nowrap">Событие прошло</span>
            <?php elseif ($registered): ?>
                <button type="button"
                        onclick="openCancelModal(<?= $eid ?>, '<?= $titleAttr ?>', '<?= htmlspecialchars($registeredPet, ENT_QUOTES) ?>'<?= $cancelActionJS ?>)"
                        class="btn-hover px-3 py-2 bg-gray-100 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-200 whitespace-nowrap">
                    Отменить
                </button>
            <?php else: ?>
                <button type="button"
                        onclick="openSignupModal(<?= $eid ?>, '<?= $titleAttr ?>')"
                        class="btn-hover px-3 py-2 bg-rose-50 text-rose-500 font-bold text-xs rounded-lg hover:bg-rose-100 whitespace-nowrap">
                    Записаться
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
