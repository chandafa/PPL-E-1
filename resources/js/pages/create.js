import {
    e,
    money,
    dateLabel,
    taxPeriodLabel,
    statusBadge,
    $,
    config,
    link,
    detailHref,
    loading,
    empty,
    notice,
    flash,
    clearNotice,
    api,
    busy,
    validateFile,
    details,
    itemsTable,
} from "../shared.js";
import {
    enhanceInvoiceSelect,
} from "../invoice-picker.js";

function extractionPreview(data) {
    return `${details([
        ["Nomor faktur / NSFP", e(data.taxInvoiceNumber)],
        ["Tanggal e-Faktur", e(dateLabel(data.taxInvoiceDate))],
        ["Masa pajak", e(taxPeriodLabel(data.taxPeriod))],
        ["Status saat disimpan", statusBadge("NORMAL")],
    ])}<div class="data-scroll my-6">${itemsTable(data.items)}</div>${details([
        ["DPP", money(data.dpp)],
        ["PPN", money(data.ppn)],
        ["PPnBM", money(data.ppnbm)],
    ])}`;
}

async function createPage() {
    const form = $("#invoice-form"),
        file = $("#eFakturFile"),
        invoice = $("#invoiceId"),
        save = $("#save-invoice");
    const replacesId = new URLSearchParams(location.search).get(
        "replacesTaxInvoiceId",
    );
    let extracted = false,
        processing = false,
        revision = 0,
        replacementReady = false;
    let catalogReady = invoice.tagName !== "SELECT";
    const catalog = new Map();
    const picker = enhanceInvoiceSelect(invoice);
    $("#file-limit").textContent =
        `Maksimal ${config.uploadMaxKb / 1024} MB per PDF.`;
    const canSave = () => {
        save.disabled =
            processing ||
            (replacesId ? !replacementReady || !file.files[0] : !extracted);
        $("#extract").disabled = processing || !catalogReady || !invoice.value;
        picker?.sync();
    };
    function showInvoiceContext() {
        const context = $("#invoice-context");
        if (!context) return;
        const selected = catalog.get(invoice.value);
        context.innerHTML = selected
            ? details([
                  ["ID invoice", e(selected.invoiceId)],
                  [
                      "Nomor invoice",
                      e(selected.invoiceNumber || "Belum tersedia"),
                  ],
                  ["Tanggal invoice", e(dateLabel(selected.invoiceDate))],
                  [
                      "Proyek",
                      e(
                          selected.projectName ||
                              selected.projectId ||
                              "Belum tersedia",
                      ),
                  ],
                  [
                      "Klien",
                      e(
                          selected.clientName ||
                              selected.clientId ||
                              "Belum tersedia",
                      ),
                  ],
                  ["Status invoice", e(selected.status || "Belum tersedia")],
                  [
                      "DPP invoice",
                      selected.dppAmount == null
                          ? "Belum tersedia"
                          : money(selected.dppAmount),
                  ],
                  [
                      "PPN invoice",
                      selected.ppnAmount == null
                          ? "Belum tersedia"
                          : money(selected.ppnAmount),
                  ],
              ])
            : replacesId && invoice.value
              ? `<p class="text-sm text-muted">Invoice ${e(invoice.value)} mengikuti faktur asal dan tidak dapat diganti.</p>`
              : '<p class="text-sm text-muted">Pilih invoice untuk melihat proyek, klien, dan nilai tagihan.</p>';
    }
    async function loadInvoiceCatalog() {
        const context = $("#invoice-context"),
            retry = $("#retry-invoices");
        if (!context) return;
        const previous = invoice.value;
        catalogReady = false;
        invoice.disabled = true;
        retry.hidden = true;
        canSave();
        context.innerHTML = loading("Memuat invoice dummy D-2…");
        try {
            const data = await api("/api/ui/d2/invoices");
            catalog.clear();
            invoice.replaceChildren(new Option("Pilih invoice D-2", ""));
            for (const item of data.invoices) {
                catalog.set(item.invoiceId, item);
                invoice.add(
                    new Option(
                        `${item.invoiceNumber || item.invoiceId} · ${item.projectName || item.projectId || "Proyek belum tersedia"} · ${item.invoiceId}`,
                        item.invoiceId,
                    ),
                );
                invoice.options[invoice.options.length - 1].dataset.search = [
                    item.invoiceId,
                    item.invoiceNumber,
                    item.projectId,
                    item.projectName,
                    item.clientId,
                    item.clientName,
                ]
                    .filter(Boolean)
                    .join(" ");
            }
            if (previous && replacesId && !catalog.has(previous))
                invoice.add(new Option(previous, previous));
            invoice.value = previous;
            catalogReady = true;
            invoice.disabled = !!replacesId || !catalog.size;
            showInvoiceContext();
            if (!catalog.size)
                context.innerHTML =
                    '<p class="text-sm text-muted">Tidak ada invoice tanpa faktur aktif. Tambahkan invoice D-2 baru atau batalkan faktur melalui detail jika diperlukan.</p>';
            retry.hidden = catalog.size > 0;
        } catch (error) {
            context.textContent = `Daftar invoice gagal dimuat. ${error.message}`;
            retry.hidden = false;
        } finally {
            canSave();
        }
    }
    function invalidate() {
        revision++;
        extracted = false;
        canSave();
        if (!replacesId)
            $("#extraction-preview").innerHTML = empty(
                "PDF belum diperiksa",
                "Periksa kembali PDF setelah mengganti invoice atau berkas.",
            );
    }
    file.onchange = invalidate;
    invoice.oninput = invalidate;
    invoice.onchange = () => {
        invalidate();
        showInvoiceContext();
    };
    if ($("#retry-invoices"))
        $("#retry-invoices").onclick = () => loadInvoiceCatalog();
    await loadInvoiceCatalog();
    if (replacesId) {
        $("#create-title").textContent = "Catat Faktur Pengganti";
        $("#extract").hidden = true;
        $("#replacement-info").hidden = false;
        $("#replacement-info").textContent =
            "Faktur pengganti wajib menggunakan invoice yang sama. Pratinjau belum tersedia untuk invoice dengan faktur aktif; PDF diperiksa saat penyimpanan.";
        $("#extraction-preview").innerHTML = empty(
            "Hasil tersedia setelah penyimpanan",
            "Unggah PDF pengganti. Identitas dan nominal dibaca server dari PDF saat Anda menyimpan.",
        );
        const original = await api(
            `/tax-invoices/${encodeURIComponent(replacesId)}`,
        );
        if (
            original.taxInvoice.status === "CANCELLED" ||
            original.replacedByTaxInvoiceId
        )
            throw new Error(
                "Faktur asal sudah dibatalkan atau terganti. Buka faktur yang masih aktif.",
            );
        if (invoice.tagName === "SELECT") {
            if (
                ![...invoice.options].some(
                    (option) => option.value === original.taxInvoice.invoiceId,
                )
            )
                invoice.add(
                    new Option(
                        original.taxInvoice.invoiceId,
                        original.taxInvoice.invoiceId,
                    ),
                );
            invoice.disabled = true;
        } else invoice.readOnly = true;
        invoice.value = original.taxInvoice.invoiceId;
        showInvoiceContext();
        const href = detailHref(replacesId);
        $("#create-back").href = $("#cancel-create").href = href;
        $("#original-reference").hidden = false;
        $("#original-reference").innerHTML =
            `<p class="text-xs text-muted">Faktur asal</p>${link(href, original.taxInvoice.taxInvoiceNumber)}`;
        replacementReady = true;
        canSave();
    }
    $("#extract").onclick = async () => {
        clearNotice();
        if (!form.reportValidity()) return;
        try {
            validateFile(file);
            processing = true;
            canSave();
            const captured = revision;
            const body = new FormData(form);
            body.set("invoiceId", invoice.value);
            $("#extraction-preview").innerHTML = loading(
                "Membaca identitas dan nilai dari PDF…",
            );
            const data = await busy(
                $("#extract"),
                () =>
                    api("/tax-invoices/extractions", { method: "POST", body }),
                "Memeriksa PDF…",
            );
            if (captured === revision) {
                extracted = true;
                $("#extraction-preview").innerHTML = extractionPreview(data);
            }
        } catch (error) {
            extracted = false;
            $("#extraction-preview").innerHTML = empty(
                "PDF e-Faktur tidak dapat diproses",
                "Periksa invoice dan berkas, lalu unggah PDF yang valid. Belum ada faktur yang tersimpan.",
            );
            notice(error.message);
        } finally {
            processing = false;
            canSave();
        }
    };
    form.onsubmit = async (event) => {
        event.preventDefault();
        if (
            processing ||
            (!replacesId && !extracted) ||
            (replacesId && !replacementReady)
        )
            return;
        clearNotice();
        try {
            validateFile(file);
            const body = new FormData(form);
            body.set("invoiceId", invoice.value);
            if (replacesId) body.set("replacesTaxInvoiceId", replacesId);
            processing = true;
            // Freeze the invoice and PDF whose extraction the user reviewed.
            const inputs = [invoice, file].map((input) => [
                input,
                input.disabled,
            ]);
            inputs.forEach(([input]) => {
                input.disabled = true;
            });
            picker?.sync();
            try {
                const data = await busy(
                    form,
                    () =>
                        api("/tax-invoices/outgoing", { method: "POST", body }),
                    "Menyimpan faktur…",
                );
                flash("Faktur berhasil disimpan.");
                location.assign(detailHref(data.taxInvoiceId));
            } finally {
                inputs.forEach(([input, disabled]) => {
                    input.disabled = disabled;
                });
            }
        } catch (error) {
            notice(error.message);
        } finally {
            processing = false;
            canSave();
        }
    };
    canSave();
}


export default createPage;
