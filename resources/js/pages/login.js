import {
    safeNext,
    $,
    config,
    tokenKey,
    notice,
    clearNotice,
    api,
    busy,
} from "../shared.js";

async function loginPage() {
    async function enter(token) {
        sessionStorage.setItem(
            tokenKey,
            token.trim().replace(/^Bearer\s+/i, ""),
        );
        try {
            await api("/tax-invoices?pageSize=1");
        } catch (error) {
            sessionStorage.removeItem(tokenKey);
            throw error;
        }
        location.assign(
            safeNext(new URLSearchParams(location.search).get("next")),
        );
    }
    if ($("#f1-login"))
        $("#f1-login").onsubmit = async (event) => {
            event.preventDefault();
            clearNotice();
            try {
                await busy(event.currentTarget, async () => {
                    const form = $("#f1-login");
                    const payload = Object.fromEntries(new FormData(form));
                    const data = await api("/api/auth/f1/login", { method: "POST", body: payload });
                    form.elements.password.value = "";
                    await enter(data.accessToken);
                }, "Memeriksa akses…");
            } catch (error) {
                $("#f1-login").elements.password.value = "";
                notice(error.message);
            }
        };
    if (config.demoLogin && $("#demo-login"))
        $("#demo-login").onsubmit = async (event) => {
            event.preventDefault();
            clearNotice();
            try {
                await busy(
                    event.currentTarget,
                    async () => {
                        const payload = Object.fromEntries(
                            new FormData($("#demo-login")),
                        );
                        const data = await api("/api/dev/f1/login", {
                            method: "POST",
                            body: payload,
                        });
                        await enter(data.accessToken);
                    },
                    "Memeriksa akses…",
                );
            } catch (error) {
                notice(error.message);
            }
        };
}


export default loginPage;
