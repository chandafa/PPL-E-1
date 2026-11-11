import {
    e,
    money,
    dateLabel,
    taxPeriodLabel,
    statusBadge,
    $,
    $$,
    table,
    td,
    link,
    detailHref,
    loading,
    empty,
    notice,
    clearNotice,
    api,
    busy,
} from "../shared.js";

function invoiceRows(items) {
    return items.map(
        (item) =>
            `<tr>${td(link(detailHref(item.taxInvoiceId), item.taxInvoiceNumber, "whitespace-nowrap"))}${td(e(dateLabel(item.taxInvoiceDate)), "whitespace-nowrap")}${td(e(item.invoiceId), "id-text")}${td(e(item.projectId) || "Belum tersedia", "id-text")}${td(e(item.clientId) || "Belum tersedia", "id-text")}${td(e(taxPeriodLabel(item.taxPeriod)), "whitespace-nowrap")}${td(money(item.dpp), "money")}${td(money(item.ppn), "money")}${td(statusBadge(item.status, !!item.replacedByTaxInvoiceId))}</tr>`,
    );
}

async function invoicePage() {
    const form = $("#filters");
    const params = new URLSearchParams(location.search);
    const { periods } = await api("/api/ui/tax-recap-periods");
    const selectedPeriod = params.get("taxPeriod");
    if (
        selectedPeriod &&
        /^[0-9]{4}-(0[1-9]|1[0-2])$/.test(selectedPeriod) &&
        !periods.includes(selectedPeriod)
    )
        periods.push(selectedPeriod);
    for (const value of periods.sort().reverse())
        $("#taxPeriod", form).add(new Option(taxPeriodLabel(value), value));
    for (const field of $$("input, select", form))
        field.value = params.get(field.name) || "";
    let current = Number(params.get("page") || 1);
    if (!Number.isSafeInteger(current) || current < 1) current = 1;
    let request = 0;
    async function load() {
        const generation = ++request;
        clearNotice();
        const query = new URLSearchParams();
        for (const [name, value] of new FormData(form))
            if (value.trim()) query.set(name, value.trim());
        query.set("page", String(current));
        query.set("pageSize", "20");
        history.replaceState(null, "", `${location.pathname}?${query}`);
        $("#invoice-list").innerHTML = loading("Memuat daftar faktur…");
        try {
            const data = await api(`/tax-invoices?${query}`);
            if (generation !== request) return;
            $("#invoice-count").textContent =
                `${data.pagination.totalItems} faktur`;
            $("#invoice-list").innerHTML = data.items.length
                ? table(
                      [
                          "NSFP",
                          "Tanggal e-Faktur",
                          "Invoice",
                          "Proyek",
                          "Klien",
                          "Masa pajak",
                          "DPP",
                          "PPN",
                          "Status",
                      ],
                      invoiceRows(data.items),
                      "Daftar faktur keluaran",
                  )
                : empty(
                      "Data tidak ditemukan",
                      "Tidak ada faktur yang sesuai. Ubah filter atau catat e-Faktur keluaran.",
                  );
            $("[data-page-info]").textContent =
                `Halaman ${data.pagination.page} dari ${Math.max(1, Math.ceil(data.pagination.totalItems / data.pagination.pageSize))} · ${data.pagination.totalItems} faktur`;
            $("[data-prev]").disabled = current <= 1;
            $("[data-next]").disabled =
                current * data.pagination.pageSize >=
                data.pagination.totalItems;
        } catch (error) {
            if (generation !== request) return;
            $("#invoice-list").innerHTML = empty(
                "Daftar faktur gagal dimuat",
                "Filter tetap tersimpan. Tekan Cari untuk mencoba kembali.",
            );
            $("[data-prev]").disabled = $("[data-next]").disabled = true;
            notice(error.message);
        }
    }
    form.onsubmit = async (event) => {
        event.preventDefault();
        current = 1;
        await busy(form, load, "Mencari…");
    };
    $("#reset-filters").onclick = () => {
        form.reset();
        $$("input, select", form).forEach((field) => {
            field.value = "";
        });
        current = 1;
        load();
    };
    $("[data-prev]").onclick = () => {
        current--;
        load();
    };
    $("[data-next]").onclick = () => {
        current++;
        load();
    };
    await load();
}


export default invoicePage;
