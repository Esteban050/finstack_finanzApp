// Una página con los listados reales; cada listado conserva su paginación y totales.
export function mountMovements() {
    const section = document.querySelector('[data-movements-history]');
    if (!section) return;
    const frame = section.querySelector('[data-movements-frame]');
    const tabs = [...section.querySelectorAll('[data-history-url]')];
    const loading = section.querySelector('[data-history-loading]');
    let observer;
    const select = (tab) => {
        for (const item of tabs) { item.setAttribute('aria-selected', String(item === tab)); item.tabIndex = item === tab ? 0 : -1; }
        section.querySelector('[role=tabpanel]').setAttribute('aria-labelledby', tab.id);
        frame.title = `Historial de ${tab.textContent.toLocaleLowerCase('es-CO')}`;
        loading.hidden = false; frame.src = tab.dataset.historyUrl;
    };
    for (const tab of tabs) {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? tabs[0] : event.key === 'End' ? tabs.at(-1) : tabs[(tabs.indexOf(tab) + 1) % tabs.length];
            next.focus(); select(next);
        });
    }
    const loaded = () => {
        observer?.disconnect();
        const doc = frame.contentDocument;
        if (!doc?.body) return;
        if (doc.querySelector('input[type=password]') && !doc.querySelector('.ui-main')) { window.location.assign(frame.contentWindow.location.href); return; }
        doc.body.classList.add('ui-embedded');
        const content = doc.querySelector('[data-page-content]') ?? doc.body;
        const resize = () => { frame.style.height = `${Math.ceil(content.getBoundingClientRect().height) + 64}px`; };
        observer = new ResizeObserver(resize); observer.observe(content); resize(); loading.hidden = true;
    };
    frame.addEventListener('load', loaded);
    if (frame.contentDocument?.readyState === 'complete') loaded();
}
