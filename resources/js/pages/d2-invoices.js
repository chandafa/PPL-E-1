import {
    e,
    money,
    dateLabel,
    $,
    table,
    td,
    loading,
    empty,
    notice,
    clearNotice,
    api,
} from "../shared.js";

function d2StatusBadge(status) {
    const labels = {
        TERTAGIH: ["Tertagih", "status-normal"],
        TERBAYAR: ["Terbayar", "status-confirmed"],
    };
    const [label, style] = labels[status] || [String(status), "status-normal"];
    return `<span class="status status-${style}">${e(label)}</span>`;
}

function d2InvoiceRows(items) {
    return items.map(
        (item) =>
            `<tr>${td(e(item.invoiceId), "id-text")}${td(e(item.invoiceNumber) || "-", "whitespace-nowrap")}${td(e(dateLabel(item.invoiceDate)), "whitespace-nowrap")}${td(money(item.dppAmount ?? "0.00"), "money")}${td(money(item.ppnAmount ?? "0.00"), "money")}${td(d2StatusBadge(item.status))}${td(e(item.projectId) || "-", "id-text")}${td(e(item.projectName) || "-")}${td(e(item.clientId) || "-", "id-text")}${td(e(item.clientName) || "-")}</tr>`,
    );
}

async function d2InvoicesPage() {
    const list = $("#d2-invoice-list");
    const count = $("#d2-invoice-count");
    const alert = $("#d2-invoices-alert");
    clearNotice("#d2-invoices-alert");
    list.innerHTML = loading("Memuat daftar invoice D-2…");
    try {
        const data = await api("/api/ui/d2/invoices/all");
        const items = data.invoices || [];
        count.textContent = `${items.length} invoice`;
        list.innerHTML = items.length
            ? table(
                  [
                      "ID Invoice",
                      "Nomor Invoice",
                      "Tanggal",
                      "DPP",
                      "PPN",
                      "Status",
                      "Proyek",
                      "Nama Proyek",
                      "Klien",
                      "Nama Klien",
                  ],
                  d2InvoiceRows(items),
                  "Daftar invoice D-2",
              )
            : empty(
                  "Data tidak ditemukan",
                  "Belum ada invoice D-2 yang tersedia. Muat ulang setelah invoice diterbitkan di D-2.",
              );
    } catch (error) {
        list.innerHTML = empty(
            "Daftar invoice gagal dimuat",
            "Periksa koneksi ke modul D-2 lalu coba kembali.",
        );
        notice(error.message, "error", "#d2-invoices-alert");
    }
}


export default d2InvoicesPage;
