

import Alpine from 'alpinejs';
import './ui/icons/index.js';
import { renderIcon } from './ui/icons/Icon.js';
import { registerFoundation, mountFoundation } from './ui/foundation.js';
import { mountIncome } from './income/index.js';
import { mountMovements } from './ui/movements.js';
import { mountExpenses } from './expenses/index.js';
import { mountAuth } from './auth/index.js';

document.querySelectorAll('[data-icon-name]').forEach((host) => {
    const svg = renderIcon(host.dataset.iconName, {
        size: host.dataset.iconSize,
        strokeWidth: host.dataset.iconStrokeWidth,
        label: host.dataset.iconLabel,
        className: host.className,
    });
    host.replaceWith(svg);
});

window.Alpine = Alpine;

registerFoundation(Alpine);
Alpine.start();
mountFoundation();
mountIncome();
mountMovements();
mountExpenses();
mountAuth();
