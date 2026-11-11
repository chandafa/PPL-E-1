import {
    $,
    $$,
    page,
    tokenKey,
    empty,
    notice,
    api,
    configureMenu,
} from "./shared.js";

async function start() {
    if (page === "login") {
        const { default: loginPage } = await import("./pages/login.js");
        await loginPage();
        return;
    }
    configureMenu();
    const token = sessionStorage.getItem(tokenKey);
    if (!token) {
        location.replace(
            `/login?next=${encodeURIComponent(location.pathname + location.search)}`,
        );
        return;
    }
    try {
        const identity = await api("/api/ui/profile");
        $("[data-account]").textContent = identity.name;
        $("[data-account]").title = identity.name;
        $("[data-role]").textContent = identity.role;
        $("#page-content").hidden = false;
        const message = sessionStorage.getItem("e1.flash");
        if (message) {
            sessionStorage.removeItem("e1.flash");
            notice(message, "success");
        }
        const pageHandlers = {
            "invoices": () => import("./pages/invoices.js"),
            "d2-invoices": () => import("./pages/d2-invoices.js"),
            "create": () => import("./pages/create.js"),
            "detail": () => import("./pages/detail.js"),
            "recap": () => import("./pages/recap.js"),
            "payment": () => import("./pages/payment.js"),
        };
        const pageHandler = pageHandlers[page];
        if (typeof pageHandler !== "function") {
            throw new Error("Halaman tidak tersedia untuk sesi saat ini.");
        }
        const { default: initialize } = await pageHandler();
        await initialize();
    } catch (error) {
        notice(error.message);
        if (![401, 403].includes(error.status)) {
            $$('#page-content [role="status"]').forEach((element) => {
                element.innerHTML = empty(
                    "Data gagal dimuat",
                    "Muat ulang halaman untuk mencoba kembali.",
                );
            });
            const retry = document.createElement("button");
            retry.className = "btn btn-secondary mt-4";
            retry.textContent = "Coba Lagi";
            retry.onclick = () => location.reload();
            $("#app-alert").append(document.createElement("br"), retry);
        }
    } finally {
        $("#auth-pending").hidden = true;
    }
}

start().catch((error) => notice(error.message));
