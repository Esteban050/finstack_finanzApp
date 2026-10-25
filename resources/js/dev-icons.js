import { ICON_NAMES } from './ui/icons/icons.js';
import { renderIcon } from './ui/icons/Icon.js';

const grid = document.querySelector('[data-icon-preview]');
if (grid) for (const name of ICON_NAMES) {
  const cell = document.createElement('div');
  cell.className = 'icon-preview-cell';
  const row = document.createElement('div');
  row.className = 'icon-preview-row';
  for (const size of [16, 24, 48]) row.append(renderIcon(name, { size }));
  cell.append(row, Object.assign(document.createElement('code'), { textContent: name }));
  grid.append(cell);
}
