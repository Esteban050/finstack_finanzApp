<script>
(() => {
    const saved = document.cookie.split('; ').find(value => value.startsWith('finanzapp_theme='))?.split('=')[1];
    document.documentElement.dataset.theme = ['light', 'dark'].includes(saved) ? saved : (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
})();
</script>
