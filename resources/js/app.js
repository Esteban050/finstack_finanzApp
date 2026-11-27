

import Alpine from 'alpinejs';
import { mountPlanning } from './planning/workspace.js';
import './ui/icons/index.js';
import { renderIcon } from './ui/icons/Icon.js';
import { registerFoundation, mountFoundation } from './ui/foundation.js';
import { mountIncome } from './income/index.js';
import { mountMovements } from './ui/movements.js';
import { mountExpenses } from './expenses/index.js';
import { mountAuth } from './auth/index.js';
import { mountTheme } from './ui/theme.js';
import { mountFinance } from './finance/index.js';
import { registerSocial } from './social/index.js';
import { mountCredits } from './credits/index.js';
import { mountCurrencySelector } from './ui/currency.js';
import { mountValueInputs } from './ui/value-input.js';
import { registerRenta } from './renta/index.js';
import { mountAdmin } from './admin/index.js';

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
mountValueInputs();

registerFoundation(Alpine);
registerSocial();
registerRenta(Alpine);
Alpine.start();
mountFoundation();
mountIncome();
mountMovements();
mountExpenses();
mountAuth();
mountTheme();
mountFinance();
mountCredits();
mountCurrencySelector();
mountAdmin();

mountPlanning();
