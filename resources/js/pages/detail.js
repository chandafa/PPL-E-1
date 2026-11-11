import {
    e,
    money,
    decimal,
    dateLabel,
    taxPeriodLabel,
    statusBadge,
    $,
    $$,
    invoiceId,
    icon,
    link,
    detailHref,
    empty,
    notice,
    clearNotice,
    api,
    busy,
    details,
    itemsTable,
    openDialog,
} from "../shared.js";

function correctionItem(item = {}) {
    return `<div class="rounded-md border border-border p-4" data-correction-item><div class="grid gap-3 sm:grid-cols-2">${[
        ["itemCode", "Kode", item.itemCode],
        ["itemName", "Nama barang / jasa", item.itemName],
        [
            "saleReplacementAdvanceTermAmount",
            "Harga / penggantian / termin",
            item.saleReplacementAdvanceTermAmount ?? "0.00",
        ],
        ["discountAmount", "Potongan", item.discountAmount ?? "0.00"],
        [
            "receivedAdvanceAmount",
            "Uang muka diterima",
            item.receivedAdvanceAmount ?? "0.00",
        ],
    ]
        .map(
            ([name, label, value]) =>
                `<label class="block text-xs font-medium">${label}<input class="field mt-2" data-item-field="${name}" value="${e(value)}" required ${["itemCode", "itemName"].includes(name) ? "" : 'inputmode="decimal" pattern="[0-9]+([.][0-9]{1,2})?"'}></label>`,
        )
        .join(
            "",
        )}</div><button type="button" class="btn btn-ghost mt-2" data-remove-item>${icon("trash")}Hapus item</button></div>`;
}

const historyLabels = {
    taxInvoiceNumber: "Nomor faktur / NSFP",
    taxInvoiceDate: "Tanggal e-Faktur",
    taxPeriod: "Masa pajak",
    dpp: "DPP",
    ppn: "PPN",
    ppnbm: "PPnBM",
    status: "Status faktur",
    replacesTaxInvoiceId: "Faktur pengganti dari",
    replacedByTaxInvoiceId: "Digantikan oleh",
};
const historyActions = {
    CREATE: "Pencatatan faktur",
    UPDATE: "Koreksi faktur",
    STATUS_CHANGE: "Perubahan status",
    DELETE: "Penghapusan faktur",
};
const historyActionIcons = {
    CREATE: "plus",
    UPDATE: "edit",
    STATUS_CHANGE: "replace",
    DELETE: "close",
};

function historyValue(field, value) {
    if (value === null || value === undefined || value === "")
        return '<span class="text-muted">Tidak ada</span>';
    if (field === "status") return statusBadge(value);
    if (field === "taxInvoiceDate") return e(dateLabel(value));
    if (field === "taxPeriod") return e(taxPeriodLabel(value));
    if (
        [
            "dpp",
            "ppn",
            "ppnbm",
            "saleReplacementAdvanceTermAmount",
            "discountAmount",
            "receivedAdvanceAmount",
        ].includes(field)
    ) {
        return /^-?\d+(\.\d{1,2})?$/.test(String(value))
            ? money(value)
            : e(value);
    }
    return e(value);
}

function historyItems(items) {
    if (!Array.isArray(items) || !items.length)
        return '<p class="text-sm text-muted">Tidak ada item barang atau jasa.</p>';
    return `<ul class="space-y-3">${items.map((item, index) => `<li class="rounded-md border border-border bg-white p-3"><p class="text-sm font-semibold">${e(item.itemName || `Item ${index + 1}`)}</p><p class="mt-1 text-xs text-muted">Kode ${e(item.itemCode || "—")}</p><dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3"><div><dt class="text-xs text-muted">Harga / termin</dt><dd class="mt-1 text-sm font-medium">${historyValue("saleReplacementAdvanceTermAmount", item.saleReplacementAdvanceTermAmount)}</dd></div><div><dt class="text-xs text-muted">Potongan</dt><dd class="mt-1 text-sm font-medium">${historyValue("discountAmount", item.discountAmount)}</dd></div><div><dt class="text-xs text-muted">Uang muka diterima</dt><dd class="mt-1 text-sm font-medium">${historyValue("receivedAdvanceAmount", item.receivedAdvanceAmount)}</dd></div></dl></li>`).join("")}</ul>`;
}

function historySnapshot(snapshot) {
    if (!snapshot || typeof snapshot !== "object")
        return '<p class="text-sm text-muted">Belum ada nilai sebelumnya.</p>';
    const fields = Object.entries(snapshot).filter(
        ([field]) => field !== "items",
    );
    return `<dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">${fields.map(([field, value]) => `<div class="min-w-0"><dt class="text-xs text-muted">${e(historyLabels[field] || field.replace(/([A-Z])/g, " $1"))}</dt><dd class="mt-1 break-words text-sm font-medium">${historyValue(field, value)}</dd></div>`).join("")}</dl>${Array.isArray(snapshot.items) ? `<div class="${fields.length ? "mt-5 border-t border-border pt-4" : ""}"><h4 class="mb-3 text-xs font-semibold text-muted">Item barang / jasa (${snapshot.items.length})</h4>${historyItems(snapshot.items)}</div>` : ""}`;
}

function historyChanges(before, after) {
    if (!before)
        return `<div class="rounded-lg border border-[#d9e3eb] bg-[#f7f9fb] p-4"><h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted">Rincian faktur</h4>${historySnapshot(after)}</div>`;
    const fields = [
        ...new Set([...Object.keys(before || {}), ...Object.keys(after || {})]),
    ];
    if (!fields.length)
        return '<p class="text-sm text-muted">Detail perubahan tidak tersedia.</p>';
    return `<div class="space-y-3">${fields
        .map((field) => {
            const label =
                historyLabels[field] || field.replace(/([A-Z])/g, " $1");
            if (field === "items")
                return `<div class="rounded-lg border border-border p-4"><h4 class="mb-3 text-sm font-semibold">Perubahan item barang / jasa</h4><div class="grid gap-4 lg:grid-cols-2"><section><p class="mb-2 text-xs font-semibold text-muted">Sebelum (${Array.isArray(before.items) ? before.items.length : 0} item)</p>${historyItems(before.items)}</section><section><p class="mb-2 text-xs font-semibold text-muted">Sesudah (${Array.isArray(after.items) ? after.items.length : 0} item)</p>${historyItems(after.items)}</section></div></div>`;
            return `<div class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center"><h4 class="text-sm font-semibold sm:col-span-3">${e(label)}</h4><div class="min-w-0"><p class="mb-1 text-xs text-muted">Sebelum</p><div class="break-words text-sm">${historyValue(field, before[field])}</div></div><span class="hidden text-muted sm:block" aria-hidden="true">→</span><div class="min-w-0"><p class="mb-1 text-xs text-muted">Sesudah</p><div class="break-words text-sm font-medium">${historyValue(field, after[field])}</div></div></div>`;
        })
        .join("")}</div>`;
}

function historyEntry(entry) {
    const title = historyActions[entry.action] || entry.action;
    const actionIcon = historyActionIcons[entry.action] || "document";
    return `<li class="relative pl-8"><span class="absolute left-[-0.625rem] top-2 inline-flex h-8 w-8 items-center justify-center rounded-full border border-border bg-white text-primary shadow-sm" aria-hidden="true">${icon(actionIcon)}</span><details class="group rounded-lg border border-border bg-white"><summary class="flex min-h-16 cursor-pointer list-none flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-lg px-4 py-3 hover:bg-slate-50 focus-visible:outline-offset-[-3px]"><span class="min-w-0"><span class="block text-sm font-semibold">${e(title)}</span><span class="mt-1 block text-xs text-muted">Oleh ${e(entry.actorAkunId)}</span></span><time class="text-xs text-muted">${e(dateLabel(entry.changedAt))}</time></summary><div class="border-t border-border p-4 sm:p-5">${entry.note ? `<p class="mb-4 rounded-md border-l-2 border-primary bg-slate-50 px-3 py-2 text-sm leading-relaxed">${e(entry.note)}</p>` : ""}${historyChanges(entry.beforeSnapshot, entry.afterSnapshot)}</div></details></li>`;
}

async function detailPage() {
    let snapshot;
    const endpoint = `/tax-invoices/${encodeURIComponent(invoiceId)}`;
    async function loadDetail() {
        snapshot = await api(endpoint);
        const f = snapshot.taxInvoice;
        $("#detail-status").innerHTML = statusBadge(
            f.status,
            !!snapshot.replacedByTaxInvoiceId,
        );
        const active = f.status !== "CANCELLED";
        $("#detail-actions").hidden = !active;
        $("#replace-invoice").disabled = !!snapshot.replacedByTaxInvoiceId;
        $("#detail-data").innerHTML =
            `<div class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]"><section class="panel"><div class="panel-heading"><h2 class="panel-title">Identitas faktur</h2></div><div class="panel-body">${details(
                [
                    ["Nomor faktur / NSFP", e(f.taxInvoiceNumber)],
                    ["Tanggal e-Faktur", e(dateLabel(f.taxInvoiceDate))],
                    [
                        "Masa pajak",
                        link(
                            `/tax-recaps/${f.taxPeriod}`,
                            taxPeriodLabel(f.taxPeriod),
                        ),
                    ],
                    [
                        "Invoice D-2",
                        `<span class="id-text">${e(f.invoiceId)}</span>`,
                    ],
                    [
                        "Proyek",
                        `<span class="id-text">${e(f.projectId) || "Belum tersedia"}</span>`,
                    ],
                    [
                        "Klien",
                        `<span class="id-text">${e(f.clientId) || "Belum tersedia"}</span>`,
                    ],
                    ["Dicatat oleh", e(f.createdByAkunId)],
                    ["Waktu pencatatan", e(dateLabel(f.createdAt))],
                ],
            )}<div class="mt-5 space-y-3 border-t border-border pt-4">${snapshot.replacesTaxInvoiceId ? `<p class="text-sm">Faktur asal: ${link(detailHref(snapshot.replacesTaxInvoiceId), "Buka faktur asal")}</p>` : ""}${snapshot.replacedByTaxInvoiceId ? `<p class="text-sm">Digantikan oleh: ${link(detailHref(snapshot.replacedByTaxInvoiceId), "Buka faktur pengganti")}</p>` : ""}<p class="text-xs text-muted">PDF tersimpan <span class="mt-1 block break-all">${e(f.pdfUri)}</span></p><p class="field-help">Berkas tercatat; akses pembukaan PDF belum tersedia.</p></div></div></section><section class="panel"><div class="panel-heading"><h2 class="panel-title">Nilai pajak</h2></div><div class="panel-body">${details(
                [
                    ["DPP", money(f.dpp)],
                    ["PPN", money(f.ppn)],
                    ["PPnBM", money(f.ppnbm)],
                ],
            )}</div></section></div><section class="panel mt-6"><div class="panel-heading"><h2 class="panel-title">Item barang / jasa</h2></div>${itemsTable(snapshot.items)}</section>`;
    }
    async function loadHistory() {
        try {
            const data = await api(`${endpoint}/history`);
            $("#invoice-history").innerHTML = data.history.length
                ? `<ol class="space-y-7 p-5 sm:p-6">${data.history.map(historyEntry).join("")}</ol>`
                : empty(
                      "Belum ada riwayat",
                      "Perubahan faktur akan tercatat di sini.",
                  );
        } catch (error) {
            $("#invoice-history").innerHTML = empty(
                "Riwayat gagal dimuat",
                error.message,
            );
        }
    }
    $("#replace-invoice").onclick = () =>
        location.assign(
            `/tax-invoices/create?replacesTaxInvoiceId=${encodeURIComponent(invoiceId)}`,
        );
    $("#correct-invoice").onclick = () => {
        const f = snapshot.taxInvoice;
        for (const name of [
            "taxInvoiceNumber",
            "taxInvoiceDate",
            "taxPeriod",
            "dpp",
            "ppn",
            "ppnbm",
        ])
            $(`[name="${name}"]`, $("#correction-form")).value =
                f[name] ?? "0.00";
        $("#correction-items").innerHTML = snapshot.items
            .map(correctionItem)
            .join("");
        $("#note").value = "";
        clearNotice("#correction-error");
        openDialog("correction-dialog");
    };
    $("#add-correction-item").onclick = () =>
        $("#correction-items").insertAdjacentHTML(
            "beforeend",
            correctionItem(),
        );
    $("#correction-items").onclick = (event) => {
        if (event.target.closest("[data-remove-item]"))
            event.target.closest("[data-correction-item]").remove();
    };
    $("#cancel-invoice").onclick = () => {
        $("#cancelNote").value = "";
        clearNotice("#cancellation-error");
        openDialog("cancellation-dialog");
    };
    async function update(form, changes, note, dialog, errorTarget) {
        clearNotice(errorTarget);
        try {
            await busy(
                form,
                () =>
                    api(endpoint, {
                        method: "PATCH",
                        body: { changes, note: note || null },
                    }),
                "Menyimpan perubahan…",
            );
            $(`#${dialog}`).close();
            notice("Perubahan faktur berhasil disimpan.", "success");
            await Promise.all([loadDetail(), loadHistory()]);
        } catch (error) {
            notice(error.message, "error", errorTarget);
        }
    }
    $("#correction-form").onsubmit = async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = Object.fromEntries(new FormData(form));
        const changes = Object.fromEntries(
            Object.entries(data).filter(([key]) => key !== "note"),
        );
        changes.items = $$("[data-correction-item]").map((row) =>
            Object.fromEntries(
                $$("[data-item-field]", row).map((input) => [
                    input.dataset.itemField,
                    input.value,
                ]),
            ),
        );
        if (!changes.items.length) {
            notice(
                "Faktur wajib memiliki minimal satu item.",
                "error",
                "#correction-error",
            );
            return;
        }
        await update(
            form,
            changes,
            data.note,
            "correction-dialog",
            "#correction-error",
        );
    };
    $("#cancellation-form").onsubmit = async (event) => {
        event.preventDefault();
        await update(
            event.currentTarget,
            { status: "CANCELLED" },
            $("#cancelNote").value,
            "cancellation-dialog",
            "#cancellation-error",
        );
    };
    // Kontrol siap sebelum detail terlihat, meskipun riwayat masih dimuat.
    await Promise.all([loadDetail(), loadHistory()]);
}


export default detailPage;
