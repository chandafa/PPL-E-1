import {
    e,
    cents,
    money,
    decimal,
    statusBadge,
    $,
    config,
    period,
    icon,
    notice,
    flash,
    clearNotice,
    api,
    busy,
    validateFile,
    details,
} from "../shared.js";

async function paymentPage() {
    const paymentId = new URLSearchParams(location.search).get("paymentId");
    const data = await api(`/tax-recaps/${period}`);
    const existing = paymentId
        ? data.payments.find((payment) => payment.taxPaymentId === paymentId)
        : null;
    if (paymentId && (!existing || existing.status !== "INCOMPLETE"))
        throw new Error(
            "Pembayaran Draft tidak ditemukan pada periode ini. Kembali ke rekap untuk memeriksa status terbaru.",
        );
    $("#payment-summary").innerHTML = details([
        ["Total PPN", money(data.recap.totalPpn)],
        ["Jumlah setor tercatat", money(data.recap.amountPaid)],
        ["Status pembayaran", statusBadge(data.recap.paymentStatus)],
    ]);
    const projects = [
        ...new Set(data.invoices.map((item) => item.projectId).filter(Boolean)),
    ];
    $("#payment-projects").innerHTML = projects.length
        ? `<ul class="space-y-3">${projects.map((project) => `<li class="break-all">${e(project)}</li>`).join("")}</ul>`
        : "<p>Belum ada proyek pada periode ini.</p>";
    $("#payment-upload-limit").textContent =
        `Berkas PDF, maksimal ${config.uploadMaxKb / 1024} MB per berkas.`;
    const form = $("#payment-form");
    function newDifference() {
        const amount = existing ? 0n : cents($("#amountPaid").value || "0");
        return (
            cents(data.recap.amountPaid) + amount - cents(data.recap.totalPpn)
        );
    }
    function updateDifference() {
        const output = $("#payment-difference");
        const warning = $("#payment-overpaid");
        try {
            const difference = newDifference();
            const overpaid = !existing && difference > 0n;
            output.textContent = money(decimal(difference));
            output.classList.toggle("text-[#942722]", overpaid);
            warning.hidden = !overpaid;
            if (overpaid) {
                const remaining =
                    cents(data.recap.totalPpn) - cents(data.recap.amountPaid);
                warning.textContent = `Jumlah setor melebihi total PPN. Maksimal yang boleh disetor: ${money(decimal(remaining > 0n ? remaining : 0n))}.`;
            }
            $("#save-payment").disabled = overpaid;
        } catch {
            output.textContent = "Periksa format jumlah setor";
            output.classList.remove("text-[#942722]");
            warning.hidden = true;
            $("#save-payment").disabled = false;
        }
    }
    $("#amountPaid").oninput = updateDifference;
    if (existing) {
        $("#payment-title").textContent = "Lengkapi Pembayaran Pajak";
        $("#payment-form-title").textContent =
            "Lengkapi NTPN dan bukti pembayaran";
        $("#amountPaid").value = existing.amountPaid;
        $("#amountPaid").readOnly = true;
        $("#billingId").value = existing.billingId;
        $("#billingId").readOnly = true;
        $("#ntpn").value = existing.ntpn || "";
        $("#ntpn").required = true;
        $("#paymentProofFile").required = true;
        $("#eBillingFile").required = false;
        $("#ebilling-field").hidden = true;
        $("#save-payment").innerHTML = `${icon("check")}Konfirmasi Pembayaran`;
    }
    const billingInput = $("#billingId");
    billingInput.addEventListener("input", () => clearNotice());
    const ntpnInput = $("#ntpn");
    function clearNtpnError() {
        if (!ntpnInput) return;
        ntpnInput.removeAttribute("aria-invalid");
        clearNotice();
    }
    if (ntpnInput) ntpnInput.addEventListener("input", clearNtpnError);
    $("#payment-fields").disabled = !data.invoices.length;
    if (!data.invoices.length)
        notice(
            "Belum ada faktur pada masa pajak ini. Catat faktur atau pilih periode lain melalui rekap.",
            "info",
        );
    updateDifference();
    form.onsubmit = async (event) => {
        event.preventDefault();
        clearNotice();
        clearNtpnError();
        try {
            if (!existing && newDifference() > 0n)
                throw new Error(
                    "Pembayaran tidak dapat disimpan karena jumlah setor melebihi total PPN (selisih plus). Sesuaikan jumlah setor agar selisih menjadi 0 atau minus.",
                );
            if (!existing) validateFile($("#eBillingFile"));
            validateFile($("#paymentProofFile"), !!existing);
            const body = existing ? new FormData() : new FormData(form);
            if (!existing) body.set("taxPeriod", period);
            if (existing) {
                body.set("ntpn", $("#ntpn").value);
                body.set("paymentProofFile", $("#paymentProofFile").files[0]);
            } else if (!$("#paymentProofFile").files.length)
                body.delete("paymentProofFile");
            await busy(
                form,
                () =>
                    api(
                        existing
                            ? `/tax-payments/${encodeURIComponent(paymentId)}`
                            : "/tax-payments",
                        { method: existing ? "PATCH" : "POST", body },
                    ),
                "Menyimpan pembayaran…",
            );
            flash(
                existing
                    ? "Pembayaran berhasil dikonfirmasi."
                    : "Pembayaran berhasil disimpan.",
            );
            location.assign(`/tax-recaps/${period}`);
        } catch (error) {
            notice(error.message);
            if (error.code === "DUPLICATE_NTPN") {
                if (ntpnInput) {
                    ntpnInput.setAttribute("aria-invalid", "true");
                    ntpnInput.focus();
                }
                return;
            }
        }
    };
}


export default paymentPage;
