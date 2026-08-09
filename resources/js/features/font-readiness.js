// شاشة تحميل CARLED وربطها بجاهزية خط Cairo — تاريخ التعديل: 2026-08-09.
const root = document.documentElement;

if (root.classList.contains('ui-font-loading')) {
    const startedAt = performance.now();
    let revealed = false;
    const revealPage = () => {
        if (revealed) return;
        revealed = true;

        // مدة واضحة بصريًا حتى يظهر تصميم السيارة والـLED حتى مع الصفحات السريعة جدًا.
        // مستخدمو تقليل الحركة لا يفرض عليهم التأخير الكامل.
        const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        const minimumDisplayTime = reducedMotion ? 180 : 1200;
        const remaining = Math.max(0, minimumDisplayTime - (performance.now() - startedAt));
        window.setTimeout(() => {
            root.classList.remove('ui-font-loading');
            const loader = document.querySelector('[data-ui-page-loader]');
            loader?.setAttribute('aria-hidden', 'true');
            window.setTimeout(() => loader?.remove(), 350);
        }, remaining);
    };

    if (!document.fonts?.load) {
        revealPage();
    } else {
        Promise.all([
            document.fonts.load('400 1rem "Cairo"', 'الخط'),
            document.fonts.load('900 1rem "Cairo"', 'الخط'),
        ]).then(revealPage, revealPage);
    }

    window.setTimeout(revealPage, 5000);
}
