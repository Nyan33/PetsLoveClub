<div id="crop-modal" class="hidden fixed inset-0 z-[70] bg-black/70 backdrop-blur-sm items-center justify-center p-3 sm:p-6">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full border-2 border-gray-100 flex flex-col overflow-hidden" style="max-height: calc(100vh - 1.5rem); max-height: calc(100dvh - 1.5rem);">
        <div class="p-5 border-b-2 border-gray-100 flex items-center justify-between flex-shrink-0">
            <h3 class="text-lg font-black text-gray-900">Обрезка изображения</h3>
            <button type="button" id="crop-close" class="btn-hover w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5 text-gray-700"></i>
            </button>
        </div>
        <div class="p-5 flex-1 min-h-0 bg-gray-50 overflow-auto">
            <div class="max-w-full max-h-[55vh] mx-auto">
                <img id="crop-image" src="" alt="" class="block max-w-full">
            </div>
        </div>
        <div class="p-5 border-t-2 border-gray-100 flex flex-wrap gap-2 justify-between flex-shrink-0">
            <div class="flex gap-2">
                <button type="button" data-crop-action="rotate-left" class="btn-hover px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-700"><i data-lucide="rotate-ccw" class="w-4 h-4"></i></button>
                <button type="button" data-crop-action="rotate-right" class="btn-hover px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-700"><i data-lucide="rotate-cw" class="w-4 h-4"></i></button>
                <button type="button" data-crop-action="reset" class="btn-hover px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-700 text-sm font-bold">Сброс</button>
            </div>
            <div class="flex gap-2">
                <button type="button" id="crop-cancel" class="btn-hover px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-700 font-bold text-sm">Отмена</button>
                <button type="button" id="crop-apply"  class="btn-hover px-4 py-2 bg-rose-400 hover:bg-rose-500 text-white rounded-xl font-bold text-sm">Применить</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal     = document.getElementById('crop-modal');
    const cropImg   = document.getElementById('crop-image');
    const closeBtn  = document.getElementById('crop-close');
    const cancelBtn = document.getElementById('crop-cancel');
    const applyBtn  = document.getElementById('crop-apply');

    let cropper = null;
    let pending = null;

    window.openCropModal = function (file, opts, onApply) {
        const reader = new FileReader();
        reader.onload = (e) => {
            cropImg.src = e.target.result;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            if (cropper) cropper.destroy();
            cropper = new Cropper(cropImg, {
                aspectRatio: opts.aspectRatio,
                viewMode: 1,
                autoCropArea: 1,
                background: false,
                movable: true,
                zoomable: true,
            });
            pending = { ...opts, onApply };
        };
        reader.readAsDataURL(file);
    };

    function closeCrop() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        if (cropper) { cropper.destroy(); cropper = null; }
        pending = null;
    }

    closeBtn.addEventListener('click', closeCrop);
    cancelBtn.addEventListener('click', closeCrop);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeCrop(); });

    document.querySelectorAll('[data-crop-action]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!cropper) return;
            const a = btn.dataset.cropAction;
            if (a === 'rotate-left')  cropper.rotate(-90);
            if (a === 'rotate-right') cropper.rotate(90);
            if (a === 'reset')        cropper.reset();
        });
    });

    applyBtn.addEventListener('click', () => {
        if (!cropper || !pending) return;
        const ar = pending.aspectRatio || 1;
        const canvas = cropper.getCroppedCanvas({
            width:  pending.outSize,
            height: pending.outSize / ar,
            imageSmoothingQuality: 'high',
        });
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        pending.onApply(dataUrl);
        closeCrop();
    });
})();
</script>
