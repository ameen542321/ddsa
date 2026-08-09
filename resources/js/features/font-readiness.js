const root = document.documentElement;

if (root.classList.contains('ui-font-loading')) {
    const revealPage = () => root.classList.remove('ui-font-loading');

    if (!document.fonts?.load) {
        revealPage();
    } else {
        Promise.all([
            document.fonts.load('400 1rem "Cairo"', 'الخط'),
            document.fonts.load('900 1rem "Cairo"', 'الخط'),
        ]).then(revealPage, revealPage);
    }
}
