import {
    escapeHtml as e,
    cents,
    money,
    decimal,
    dateLabel,
    taxPeriodLabel,
    statusBadge,
    safeNext,
} from "./lib.js";

const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [
    ...scope.querySelectorAll(selector),
];
const config = JSON.parse($("#e1-config").textContent);
const page = document.body.dataset.page;
const tokenKey = "e1.accessToken";
const period = document.body.dataset.period;
const invoiceId = document.body.dataset.invoiceId;
const icon = (name) =>
    `<svg class="icon" aria-hidden="true"><use href="#solar-${name}"></use></svg>`;
const table = (headers, rows, label = "Data pajak") =>
    `<div class="data-scroll" role="region" aria-label="${e(label)}" tabindex="0"><table class="data-table"><thead><tr>${headers.map((h) => `<th scope="col"${/^(DPP|PPN|PPnBM|Jumlah|Selisih)/.test(h) ? ' class="money"' : ""}>${e(h)}</th>`).join("")}</tr></thead><tbody>${rows.join("")}</tbody></table></div>`;
const td = (value, cls = "") => `<td class="${cls}">${value}</td>`;
const link = (href, text, cls = "") =>
    `<a class="text-link ${cls}" href="${e(href)}">${e(text)}</a>`;
const detailHref = (id) => `/tax-invoices/${encodeURIComponent(id)}`;
const loading = (message) =>
    `<div class="space-y-4 p-5" role="status"><p class="text-sm text-muted">${e(message)}</p><div class="skeleton-bar w-2/3"></div><div class="skeleton-bar"></div><div class="skeleton-bar w-4/5"></div></div>`;
const empty = (title, message) =>
    `<div class="empty-state"><h3>${e(title)}</h3><p>${e(message)}</p></div>`;

function notice(message, variant = "error", target = "#app-alert") {
    const element = $(target);
    if (!element) return;
    element.textContent = message;
    element.className = `notice ${variant === "error" ? "notice-error" : variant === "success" ? "notice-success" : ""} ${target === "#app-alert" ? "mb-5" : ""}`;
    element.hidden = false;
    element.setAttribute("tabindex", "-1");
    if (variant === "error") element.focus({ preventScroll: true });
}

function noticeHtml(html, variant = "info", target = "#app-alert") {
    const element = $(target);
    if (!element) return;
    element.innerHTML = html;
    element.className = `notice ${variant === "error" ? "notice-error" : variant === "success" ? "notice-success" : ""} ${target === "#app-alert" ? "mb-5" : ""}`;
    element.hidden = false;
    element.setAttribute("tabindex", "-1");
    if (variant === "error") element.focus({ preventScroll: true });
}

function flash(message) {
    sessionStorage.setItem("e1.flash", message);
}
function clearNotice(target = "#app-alert") {
    $(target).hidden = true;
}

async function api(path, options = {}) {
    const headers = new Headers(options.headers);
    const token = sessionStorage.getItem(tokenKey);
    if (token) headers.set("Authorization", `Bearer ${token}`);
    headers.set("Accept", "application/json");
    const body = options.body;
    if (body && !(body instanceof FormData))
        headers.set("Content-Type", "application/json");
    let response;
    try {
        response = await fetch(
            path.startsWith("/api/") ? path : `/api/v1${path}`,
            {
                ...options,
                headers,
                body:
                    body && !(body instanceof FormData)
                        ? JSON.stringify(body)
                        : body,
            },
        );
    } catch {
        throw new Error(
            "Server tidak dapat dihubungi. Periksa koneksi lalu coba kembali.",
        );
    }
    if (response.status === 401) {
        sessionStorage.removeItem(tokenKey);
        if (page !== "login")
            location.assign(
                `/login?next=${encodeURIComponent(location.pathname + location.search)}`,
            );
    }
    if (response.status === 403 && page !== "login") {
        $("#page-content").hidden = true;
        $("#access-denied").hidden = false;
    }
    if (!response.ok) {
        const data = await response.json().catch(() => null);
        const error = new Error(
            data?.message ||
                `Permintaan gagal (HTTP ${response.status}). Coba kembali.`,
        );
        error.code = data?.errorCode;
        error.status = response.status;
        throw error;
    }
    if (options.binary) return response;
    const payload = await response.json();
    return payload.data;
}

async function busy(element, action, label = "Memproses…") {
    const buttons = element.matches("button")
        ? [element]
        : $$("button", element);
    const previous = buttons.map((button) => [
        button,
        button.disabled,
        button.innerHTML,
    ]);
    buttons.forEach((button) => {
        button.disabled = true;
    });
    const submit = element.matches("button")
        ? element
        : $('button[type="submit"]', element);
    if (submit) submit.textContent = label;
    element.setAttribute("aria-busy", "true");
    try {
        return await action();
    } finally {
        element.removeAttribute("aria-busy");
        previous.forEach(([button, disabled, html]) => {
            button.disabled = disabled;
            button.innerHTML = html;
        });
    }
}

function validateFile(input, required = true) {
    const file = input.files[0];
    if (!file && !required) return;
    if (!file) throw new Error("Pilih berkas PDF terlebih dahulu.");
    if (!/\.pdf$/i.test(file.name)) throw new Error("Berkas harus berupa PDF.");
    if (file.size > config.uploadMaxKb * 1024)
        throw new Error(`Ukuran PDF melebihi ${config.uploadMaxKb / 1024} MB.`);
}

function details(values) {
    return `<dl class="detail-grid">${values.map(([name, value]) => `<div><dt>${e(name)}</dt><dd>${value}</dd></div>`).join("")}</dl>`;
}
function itemsTable(items) {
    return table(
        [
            "Kode",
            "Barang / jasa",
            "Harga / penggantian / termin",
            "Potongan",
            "Uang muka diterima",
        ],
        items.map(
            (item) =>
                `<tr>${td(e(item.itemCode))}${td(e(item.itemName))}${td(money(item.saleReplacementAdvanceTermAmount), "money")}${td(money(item.discountAmount), "money")}${td(money(item.receivedAdvanceAmount), "money")}</tr>`,
        ),
        "Item barang dan jasa",
    );
}

const dialogTriggers = new WeakMap();
function openDialog(id) {
    const dialog = $(`#${id}`);
    dialogTriggers.set(dialog, document.activeElement);
    if (!dialog.open) dialog.showModal();
}
$$("dialog").forEach((dialog) =>
    dialog.addEventListener("close", () => dialogTriggers.get(dialog)?.focus()),
);
document.addEventListener("click", (event) => {
    const close = event.target.closest("[data-close-dialog]");
    if (close) close.closest("dialog").close();
});

function configureMenu() {
    const menu = $("#sidebar");
    const open = $("#open-menu");
    const backdrop = $("#sidebar-backdrop");
    const desktop = matchMedia("(min-width: 1024px)");
    menu.inert = !desktop.matches;
    let previous;
    function toggle(show) {
        if (show) previous = document.activeElement;
        menu.classList.toggle("-translate-x-full", !show);
        menu.inert = !show && !desktop.matches;
        backdrop.hidden = !show;
        open.setAttribute("aria-expanded", String(show));
        document.body.style.overflow = show ? "hidden" : "";
        if (show) {
            menu.setAttribute("role", "dialog");
            menu.setAttribute("aria-modal", "true");
            $("#close-menu").focus();
        } else {
            menu.removeAttribute("role");
            menu.removeAttribute("aria-modal");
            previous?.focus();
        }
    }
    open.onclick = () => toggle(true);
    $("#close-menu").onclick = () => toggle(false);
    backdrop.onclick = () => toggle(false);
    document.addEventListener("keydown", (event) => {
        if (backdrop.hidden) return;
        if (event.key === "Escape") toggle(false);
        if (event.key === "Tab") {
            const controls = $$("a, button", menu).filter(
                (element) => element.getClientRects().length,
            );
            const first = controls[0],
                last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    desktop.addEventListener("change", () => {
        if (!backdrop.hidden) toggle(false);
        menu.inert = !desktop.matches;
    });
    $("#logout").onclick = () => {
        sessionStorage.removeItem(tokenKey);
        location.assign("/login");
    };
}


export { e, cents, money, decimal, dateLabel, taxPeriodLabel, statusBadge, safeNext, $, $$, config, page, tokenKey, period, invoiceId, icon, table, td, link, detailHref, loading, empty, notice, noticeHtml, flash, clearNotice, api, busy, validateFile, details, itemsTable, openDialog, configureMenu };
