import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const { icons } = JSON.parse(readFileSync(new URL('../node_modules/@iconify-json/solar/icons.json', import.meta.url)));
const selected = {
    document: 'document-text-linear', calendar: 'calendar-linear', wallet: 'wallet-linear',
    logout: 'logout-2-linear', login: 'login-2-linear', menu: 'hamburger-menu-linear',
    close: 'close-circle-linear', left: 'alt-arrow-left-linear', right: 'alt-arrow-right-linear',
    plus: 'add-circle-linear', search: 'magnifier-linear', scan: 'scanner-linear',
    save: 'diskette-linear', edit: 'pen-linear', replace: 'refresh-circle-linear',
    download: 'download-minimalistic-linear', lock: 'lock-keyhole-linear', check: 'check-circle-linear',
    history: 'history-linear', trash: 'trash-bin-trash-linear', info: 'info-circle-linear',
};
const symbols = Object.entries(selected).map(([name, key]) => {
    if (!icons[key]) throw new Error(`Solar icon missing: ${key}`);
    return `<symbol id="solar-${name}" viewBox="0 0 24 24">${icons[key].body}</symbol>`;
});
mkdirSync(new URL('../resources/icons/', import.meta.url), { recursive: true });
writeFileSync(new URL('../resources/icons/solar.svg', import.meta.url), `<svg xmlns="http://www.w3.org/2000/svg"><!-- Solar Icons by 480 Design / R4IN80W, CC BY 4.0; https://github.com/480-Design/Solar-Icon-Set -->${symbols.join('')}</svg>\n`);
