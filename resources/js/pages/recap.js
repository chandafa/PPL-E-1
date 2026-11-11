import {
    e,
    money,
    dateLabel,
    taxPeriodLabel,
    statusBadge,
    $,
    period,
    icon,
    table,
    td,
    link,
    detailHref,
    empty,
    notice,
    noticeHtml,
    clearNotice,
    api,
    busy,
    openDialog,
} from "../shared.js";

async function allInvoices(taxPeriod) {
    const items = [];
    let number = 1,
        total;
    do {
        const data = await api(
            `/tax-invoices?${new URLSearchParams({ taxPeriod, page: String(number), pageSize: "100" })}`,
        );
        items.push(...data.items);
        total = data.pagination.totalItems;
        number++;
        if (!data.items.length) break;
    } while (items.length < total);
    return new Map(items.map((item) => [item.taxInvoiceId, item]));
}

function recapSummary(recap) {
    return `<dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><div class="metric"><dt>Faktur dihitung</dt><dd>${recap.invoiceCount}</dd></div><div class="metric"><dt>Total DPP</dt><dd>${money(recap.totalDpp)}</dd></div><div class="metric"><dt>Total PPN</dt><dd>${money(recap.totalPpn)}</dd></div><div class="metric"><dt>Total PPnBM</dt><dd>${money(recap.totalPpnbm)}</dd></div></dl><div class="panel mt-3 flex flex-wrap items-center justify-between gap-5 p-5"><div><p class="mb-2 text-xs text-muted">Status pembayaran</p>${statusBadge(recap.paymentStatus)}</div><div><p class="text-xs text-muted">Jumlah setor</p><p class="mt-2 font-semibold tabular-nums">${money(recap.amountPaid)}</p></div><div><p class="text-xs text-muted">Selisih (setor − PPN)</p><p class="mt-2 font-semibold tabular-nums">${money(recap.paymentDifference)}</p></div></div>`;
}

function recapRows(invoices, references, interactive = true) {
    return invoices.map((item) => {
        const reference = references.get(item.taxInvoiceId);
        return `<tr>${td(interactive ? link(detailHref(item.taxInvoiceId), item.taxInvoiceNumber, "whitespace-nowrap") : e(item.taxInvoiceNumber), "whitespace-nowrap")}${td(e(reference ? dateLabel(reference.taxInvoiceDate) : "Belum tersedia"), "whitespace-nowrap")}${td(e(item.invoiceId), "id-text")}${td(e(item.projectId) || "Belum tersedia", "id-text")}${td(e(item.clientId) || "Belum tersedia", "id-text")}${td(statusBadge(item.status, !!reference?.replacedByTaxInvoiceId))}${td(money(item.dpp), "money")}${td(money(item.ppn), "money")}${td(money(item.ppnbm), "money")}${td(item.isCounted ? "Ya" : "Tidak")}${interactive ? td(link(detailHref(item.taxInvoiceId), "Detail")) : ""}</tr>`;
    });
}

function paymentRows(payments, taxPeriod, interactive = true) {
    return payments.map(
        (item) =>
            `<tr>${td(e(item.billingId))}${td(e(item.ntpn) || "Belum diisi")}${td(money(item.amountPaid), "money")}${td(statusBadge(item.status))}${td(e(dateLabel(item.createdAt)), "whitespace-nowrap")}${interactive ? td(item.status === "INCOMPLETE" ? link(`/tax-recaps/${taxPeriod}/payments/create?paymentId=${encodeURIComponent(item.taxPaymentId)}`, "Lengkapi") : "Lengkap") : ""}</tr>`,
    );
}

async function recapPage() {
    $("#period-form").onsubmit = (event) => {
        event.preventDefault();
        location.assign(`/tax-recaps/${$("#selectedPeriod").value}`);
    };
    const { periods } = await api("/api/ui/tax-recap-periods");
    const select = $("#selectedPeriod");
    select.replaceChildren(
        ...periods.map((value) => new Option(taxPeriodLabel(value), value)),
    );
    if (!periods.length) {
        select.add(new Option("Belum ada masa pajak", ""));
    } else {
        select.disabled = false;
        $('#period-form button[type="submit"]').disabled = false;
        if (periods.includes(period)) {
            select.value = period;
        } else {
            const placeholder = new Option(
                "Pilih masa pajak tersedia",
                "",
                true,
                true,
            );
            placeholder.disabled = true;
            select.add(placeholder, 0);
        }
    }
    const [data, references] = await Promise.all([
        api(`/tax-recaps/${period}`),
        allInvoices(period),
    ]);
    $("#recap-summary").innerHTML = recapSummary(data.recap);
    $("#recap-count").textContent =
        `${data.invoices.length} tercatat · ${data.recap.invoiceCount} dihitung`;
    const headers = [
        "NSFP",
        "Tanggal",
        "Invoice",
        "Proyek",
        "Klien",
        "Status",
        "DPP",
        "PPN",
        "PPnBM",
        "Dihitung",
    ];
    $("#recap-invoices").innerHTML = data.invoices.length
        ? table(
              [...headers, "Detail"],
              recapRows(data.invoices, references),
              "Faktur masa pajak",
          )
        : empty(
              "Rekap masa pajak kosong",
              "Belum ada faktur pada periode ini. Pilih masa pajak lain atau catat faktur keluaran.",
          );
    $("#recap-payments").innerHTML = data.payments.length
        ? table(
              [
                  "ID Billing",
                  "NTPN",
                  "Jumlah setor",
                  "Status",
                  "Dicatat",
                  "Tindakan",
              ],
              paymentRows(data.payments, period),
              "Pembayaran masa pajak",
          )
        : empty(
              "Pembayaran belum dicatat",
              "Catat pembayaran setelah faktur periode ini tersedia. NTPN dan bukti pembayaran dapat dilengkapi kemudian.",
          );
    if (!data.invoices.length) {
        noticeHtml(
            `<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <strong class="font-semibold block sm:inline">Data rekap periode ${e(taxPeriodLabel(period))} belum tersedia.</strong>
                    <span> Silakan catat atau unggah e-Faktur terlebih dahulu melalui menu <strong>Faktur Pajak</strong> agar data rekap dan perhitungan pajak otomatis terisi di sini.</span>
                </div>
                <a href="/tax-invoices/create" class="btn btn-secondary whitespace-nowrap text-xs">Catat Faktur Baru</a>
            </div>`,
            "info",
        );
    } else {
        clearNotice();
    }

    const paymentLink = $(
        "#btn-catat-pembayaran, a[href$='/payments/create']",
        $("#page-content"),
    );
    if (paymentLink) {
        if (!data.invoices.length) {
            paymentLink.setAttribute("aria-disabled", "true");
            paymentLink.classList.add("opacity-60");
            paymentLink.onclick = (event) => {
                event.preventDefault();
                notice(
                    "Catat faktur untuk periode ini terlebih dahulu sebelum mencatat pembayaran.",
                    "info",
                );
            };
        } else {
            paymentLink.removeAttribute("aria-disabled");
            paymentLink.classList.remove("opacity-60");
            paymentLink.onclick = null;
        }
    }
    $("#open-export").disabled = false;
    $("#open-export").onclick = () => {
        $("#export-preview").innerHTML =
            `${recapSummary(data.recap)}<h3 class="mb-3 mt-5 text-sm font-semibold">Faktur</h3>${data.invoices.length ? table(headers, recapRows(data.invoices, references, false), "Pratinjau faktur ekspor") : '<p class="text-sm text-muted">Tidak ada faktur pada periode ini.</p>'}<h3 class="mb-3 mt-5 text-sm font-semibold">Pembayaran</h3>${data.payments.length ? table(["ID Billing", "NTPN", "Jumlah setor", "Status", "Dicatat"], paymentRows(data.payments, period, false), "Pratinjau pembayaran ekspor") : '<p class="text-sm text-muted">Belum ada pembayaran.</p>'}`;
        clearNotice("#export-error");
        resetPreview();
        openDialog("export-dialog");
    };
    let pdfUrl;
    let previewGeneration = 0;
    const previewButton = $("#preview-pdf");
    const previewFormat = $("#export-format");
    function updatePreviewLabel() {
        previewButton.innerHTML = `${icon("document")}Pratinjau ${previewFormat.value === "xlsx" ? "Excel" : "PDF"}`;
    }
    function resetPreview() {
        previewGeneration++;
        $("#export-preview").hidden = true;
        $("#pdf-preview").hidden = true;
        $("#pdf-preview iframe").removeAttribute("src");
        if (pdfUrl) URL.revokeObjectURL(pdfUrl);
        pdfUrl = null;
        updatePreviewLabel();
    }
    updatePreviewLabel();
    previewFormat.onchange = () => {
        resetPreview();
        clearNotice("#export-error");
    };
    $("#preview-pdf").onclick = async () => {
        clearNotice("#export-error");
        if (previewFormat.value === "xlsx") {
            $("#export-preview").hidden = false;
            $("#export-preview").scrollIntoView({
                block: "nearest",
                behavior: "smooth",
            });
            return;
        }
        const generation = ++previewGeneration;
        try {
            await busy(
                $("#preview-pdf"),
                async () => {
                    const response = await api(
                        `/tax-recaps/${period}/export?format=pdf`,
                        { binary: true },
                    );
                    const blob = await response.blob();
                    if (generation !== previewGeneration) return;
                    if (pdfUrl) URL.revokeObjectURL(pdfUrl);
                    pdfUrl = URL.createObjectURL(blob);
                    $("#pdf-preview iframe").src = pdfUrl;
                    $("#pdf-preview").hidden = false;
                },
                "Menyiapkan PDF…",
            );
        } catch (error) {
            if (generation === previewGeneration)
                notice(error.message, "error", "#export-error");
        } finally {
            updatePreviewLabel();
        }
    };
    $("#export-dialog").addEventListener("close", resetPreview);
    $("#download-export").onclick = async () => {
        clearNotice("#export-error");
        try {
            await busy(
                $("#download-export"),
                async () => {
                    const format = $("#export-format").value;
                    const response = await api(
                        `/tax-recaps/${period}/export?format=${format}`,
                        { binary: true },
                    );
                    const url = URL.createObjectURL(await response.blob());
                    const anchor = document.createElement("a");
                    anchor.href = url;
                    anchor.download =
                        /filename="?([^";]+)"?/.exec(
                            response.headers.get("Content-Disposition") || "",
                        )?.[1] || `rekap-pajak-${period}.${format}`;
                    anchor.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                },
                "Menyiapkan unduhan…",
            );
        } catch (error) {
            notice(error.message, "error", "#export-error");
        }
    };
}


export default recapPage;
