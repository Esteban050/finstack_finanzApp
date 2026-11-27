<script>
(() => {
    const palettes = @js(array_keys(config('themes.palettes')));
    const cookies = document.cookie.split('; ');
    const read = name => cookies.find(value => value.startsWith(name + '='))?.slice(name.length + 1);
    const savedMode = read('finanzapp_theme');
    const [light, dark] = (read('finanzapp_palettes') || '').split('.');
    const root = document.documentElement;
    root.dataset.theme = ['light', 'dark'].includes(savedMode) ? savedMode : (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
    root.dataset.lightPalette = palettes.includes(light) ? light : 'orange';
    root.dataset.darkPalette = palettes.includes(dark) ? dark : 'orange';
    root.dataset.palette = root.dataset.theme === 'dark' ? root.dataset.darkPalette : root.dataset.lightPalette;
})();
</script>
