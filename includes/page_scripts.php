<script>
    lucide.createIcons();
    (function () {
        const btn   = document.getElementById('mobile-menu-btn');
        const menu  = document.getElementById('mobile-menu');
        const ovr   = document.getElementById('mobile-overlay');
        const close = document.getElementById('mobile-close-btn');
        if (!btn || !menu || !ovr) return;
        function openMenu() {
            menu.classList.remove('translate-x-full');
            ovr.classList.remove('opacity-0', 'pointer-events-none');
            ovr.classList.add('opacity-100');
            document.body.style.overflow = 'hidden';
        }
        function closeMenu() {
            menu.classList.add('translate-x-full');
            ovr.classList.add('opacity-0', 'pointer-events-none');
            ovr.classList.remove('opacity-100');
            document.body.style.overflow = '';
        }
        btn.addEventListener('click', openMenu);
        if (close) close.addEventListener('click', closeMenu);
        ovr.addEventListener('click', closeMenu);
        menu.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) closeMenu();
        });
    })();
    const navbar = document.getElementById('navbar');
    if (navbar) {
        const onScroll = () => {
            const scrolled = window.scrollY > 50;
            navbar.classList.toggle('bg-white/90', scrolled);
            navbar.classList.toggle('backdrop-blur-lg', scrolled);
            navbar.classList.toggle('shadow-lg', scrolled);
        };
        window.addEventListener('scroll', onScroll);
        onScroll();
    }
    (function () {
        let tip = null;
        function show(el) {
            const text = el.dataset.roleTip;
            if (!text) return;
            tip = document.createElement('div');
            tip.className = 'role-tip';
            tip.textContent = text;
            document.body.appendChild(tip);
            const r = el.getBoundingClientRect();
            const tw = tip.offsetWidth, th = tip.offsetHeight;
            let x = r.left + r.width / 2 - tw / 2;
            let y = r.top - th - 8;
            if (y < 8) y = r.bottom + 8;
            x = Math.max(8, Math.min(x, window.innerWidth - tw - 8));
            tip.style.left = x + 'px';
            tip.style.top  = y + 'px';
            requestAnimationFrame(() => tip && tip.classList.add('is-visible'));
        }
        function hide() {
            if (tip) { tip.remove(); tip = null; }
        }
        document.querySelectorAll('.role-badge').forEach(el => {
            el.addEventListener('mouseenter', () => show(el));
            el.addEventListener('mouseleave', hide);
            el.addEventListener('focus',      () => show(el));
            el.addEventListener('blur',       hide);
        });
    })();
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.length > 1) {
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });
</script>
