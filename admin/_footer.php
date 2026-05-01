</main>

<script>
    lucide.createIcons();
    (function () {
        const btn   = document.getElementById('admin-mobile-btn');
        const side  = document.getElementById('admin-side');
        const ovr   = document.getElementById('admin-overlay');
        if (!btn || !side || !ovr) return;
        function open()  { side.classList.remove('translate-x-full'); ovr.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
        function close() { side.classList.add('translate-x-full');    ovr.classList.add('hidden');    document.body.style.overflow = ''; }
        btn.addEventListener('click', () => {
            if (side.classList.contains('translate-x-full')) open(); else close();
        });
        ovr.addEventListener('click', close);
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                side.classList.remove('translate-x-full');
                ovr.classList.add('hidden');
                document.body.style.overflow = '';
            } else {
                side.classList.add('translate-x-full');
            }
        });
    })();
</script>
</body>
</html>
