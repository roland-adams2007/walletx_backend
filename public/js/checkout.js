(function () {
    const modal = document.getElementById("wx-card");
    if (!modal) return;

    const reference = modal.dataset.reference;
    const accessCode = modal.dataset.accessCode;
    const expiresAt = modal.dataset.expiresAt
        ? new Date(modal.dataset.expiresAt)
        : null;
    const cancelUrl = modal.dataset.cancelUrl;
    const chargeUrl = modal.dataset.chargeUrl;
    const callbackUrl = modal.dataset.callbackUrl || "";

    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') || {}).content || "";

    const ICONS = {
        alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
        check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        xmark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
    };

    function formatAmount(kobo) {
        const naira = kobo / 100;
        return (
            "₦" +
            naira.toLocaleString("en-NG", {
                minimumFractionDigits: naira % 1 === 0 ? 0 : 2,
                maximumFractionDigits: 2,
            })
        );
    }

    function detectCardBrand(digits) {
        if (/^4/.test(digits)) return "VISA";
        if (/^5[1-5]/.test(digits) || /^2(2[2-9]|[3-6]\d|7[01])/.test(digits))
            return "MASTERCARD";
        if (/^506(0|1)|^5078/.test(digits)) return "VERVE";
        return "";
    }

    function setError(el, message) {
        if (!el) return;
        if (!message) {
            el.classList.remove("wx-error-visible");
            el.innerHTML = "";
            return;
        }
        el.innerHTML = ICONS.alert + "<span>" + message + "</span>";
        el.classList.add("wx-error-visible");
    }

    function buildCallbackUrl(status) {
        if (!callbackUrl) return "";
        const url = new URL(callbackUrl, window.location.href);
        url.searchParams.set("reference", reference);
        url.searchParams.set("status", status);
        return url.toString();
    }

    function goToCallback(status) {
        const url = buildCallbackUrl(status);
        if (url) window.location.href = url;
    }

    let countdownInterval = null;
    let redirectTimeout = null;

    function clearCountdown() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    function handleExpiry() {
        const payBtn = document.getElementById("wx-pay-btn");
        if (payBtn) payBtn.disabled = true;

        document.querySelectorAll(".wx-outcome-btn").forEach(function (b) {
            b.disabled = true;
        });

        const message =
            "This transaction has expired. Please close this window and start again.";
        setError(document.getElementById("wx-card-error"), message);
        setError(document.getElementById("wx-transfer-error"), message);
    }

    function startCountdown() {
        const timerEl = document.getElementById("wx-timer");
        const timerText = document.getElementById("wx-timer-text");
        if (!expiresAt || !timerText) return;

        function tick() {
            const msLeft = expiresAt.getTime() - Date.now();

            if (msLeft <= 0) {
                clearCountdown();
                timerText.textContent = "Expired";
                timerEl.classList.add("wx-timer-expired");
                handleExpiry();
                return;
            }

            const totalSeconds = Math.floor(msLeft / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            timerText.textContent =
                "Expires in " +
                String(minutes).padStart(2, "0") +
                ":" +
                String(seconds).padStart(2, "0");
        }

        tick();
        countdownInterval = setInterval(tick, 1000);
    }

    startCountdown();

    const brandmark = document.getElementById("wx-brandmark");
    if (brandmark) {
        brandmark.onerror = function () {
            brandmark.onerror = null;
            brandmark.style.display = "none";
        };
    }

    const tabs = modal.querySelectorAll(".wx-method");
    const panels = modal.querySelectorAll(".wx-panel");
    tabs.forEach(function (tab) {
        tab.onclick = function () {
            tabs.forEach(function (t) {
                t.classList.remove("active");
            });
            tab.classList.add("active");
            const target = tab.dataset.tab;
            panels.forEach(function (p) {
                p.style.display = p.dataset.panel === target ? "" : "none";
            });
        };
    });

    const cardInput = document.getElementById("wx-card-number");
    const brandEl = document.getElementById("wx-card-brand");
    if (cardInput) {
        cardInput.oninput = function () {
            const digits = cardInput.value.replace(/\D/g, "").slice(0, 16);
            cardInput.value = digits.replace(/(.{4})/g, "$1 ").trim();
            brandEl.textContent = detectCardBrand(digits);
        };
    }
    ["wx-exp-m", "wx-exp-y"].forEach(function (id) {
        const el = document.getElementById(id);
        if (el)
            el.oninput = function (e) {
                e.target.value = e.target.value.replace(/\D/g, "").slice(0, 2);
            };
    });
    const cvvInput = document.getElementById("wx-cvv");
    if (cvvInput)
        cvvInput.oninput = function (e) {
            e.target.value = e.target.value.replace(/\D/g, "").slice(0, 3);
        };

    function showFormWrap() {
        const sidebar = document.getElementById("wx-sidebar");
        if (sidebar) sidebar.style.display = "";
        document.getElementById("wx-form-wrap").style.display = "";
        document.getElementById("wx-result-wrap").style.display = "none";
        const cardActions = document.getElementById("wx-card-form-actions");
        const cardOutcome = document.getElementById("wx-card-outcome");
        if (cardActions) cardActions.style.display = "";
        if (cardOutcome) cardOutcome.style.display = "none";
    }

    function showResult(state) {
        const sidebar = document.getElementById("wx-sidebar");
        const formWrap = document.getElementById("wx-form-wrap");
        const wrap = document.getElementById("wx-result-wrap");

        if (sidebar) sidebar.style.display = "none";
        formWrap.style.display = "none";
        wrap.style.display = "";

        if (state === "success") {
            wrap.innerHTML =
                '<div class="wx-result">' +
                '<div class="wx-result-icon wx-icon-success">' +
                ICONS.check +
                "</div>" +
                '<p class="wx-result-title">Payment Successful</p>' +
                '<p class="wx-result-subtitle">Paid ' +
                formatAmount(modal.dataset.amount) +
                "</p>" +
                "</div>";

            clearCountdown();

            if (callbackUrl) {
                redirectTimeout = setTimeout(function () {
                    goToCallback("success");
                }, 1800);
            }
        } else if (state === "pending") {
            wrap.innerHTML =
                '<div class="wx-result">' +
                '<div class="wx-result-icon wx-icon-pending">' +
                ICONS.clock +
                "</div>" +
                '<p class="wx-result-title">Payment Pending</p>' +
                '<p class="wx-result-subtitle">We\'re waiting on confirmation for this payment. This can take a few minutes — you can safely leave this page.</p>' +
                "</div>";
        } else {
            wrap.innerHTML =
                '<div class="wx-result">' +
                '<div class="wx-result-icon wx-icon-failed">' +
                ICONS.xmark +
                "</div>" +
                '<p class="wx-result-title">Payment Failed</p>' +
                '<p class="wx-result-subtitle">We couldn\'t complete this payment. Please check your details and try again.</p>' +
                '<div class="wx-result-actions">' +
                '<button class="wx-btn" id="wx-retry-btn">Try Again</button>' +
                (callbackUrl
                    ? '<button class="wx-btn wx-btn-ghost" id="wx-cancel-result-btn">Cancel</button>'
                    : "") +
                "</div>" +
                "</div>";

            document.getElementById("wx-retry-btn").onclick = function () {
                if (redirectTimeout) {
                    clearTimeout(redirectTimeout);
                    redirectTimeout = null;
                }
                showFormWrap();
            };

            const cancelResultBtn = document.getElementById(
                "wx-cancel-result-btn",
            );
            if (cancelResultBtn) {
                cancelResultBtn.onclick = function () {
                    if (redirectTimeout) clearTimeout(redirectTimeout);
                    goToCallback("failed");
                };
            }

            if (callbackUrl) {
                redirectTimeout = setTimeout(function () {
                    goToCallback("failed");
                }, 4000);
            }
        }
    }

    async function submitCharge(outcome, channel, buttons) {
        const isCard = channel === "card";
        const errorEl = document.getElementById(
            isCard ? "wx-card-error" : "wx-transfer-error",
        );

        setError(errorEl, "");

        const body = {
            reference: reference,
            access_code: accessCode,
            channel: channel,
            simulate: outcome,
        };

        if (isCard) {
            body.card_number = cardInput.value.replace(/\s/g, "");
            body.expiry_month = document.getElementById("wx-exp-m").value;
            body.expiry_year = document.getElementById("wx-exp-y").value;
            body.cvv = cvvInput.value;
        }

        buttons.forEach(function (b) {
            b.disabled = true;
        });

        try {
            const res = await fetch(chargeUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
                body: JSON.stringify(body),
            });
            const data = await res.json();

            buttons.forEach(function (b) {
                b.disabled = false;
            });

            if (!data.success) {
                setError(
                    errorEl,
                    data.message || "We couldn't process that payment.",
                );
                showResult("failed");
                return;
            }

            if (data.data && data.data.status === "pending") {
                showResult("pending");
                return;
            }

            showResult("success");
        } catch (err) {
            buttons.forEach(function (b) {
                b.disabled = false;
            });
            setError(errorEl, "A network error occurred. Please try again.");
        }
    }

    const payBtn = document.getElementById("wx-pay-btn");
    if (payBtn)
        payBtn.onclick = function () {
            const errorEl = document.getElementById("wx-card-error");
            setError(errorEl, "");

            const cardNumber = cardInput.value.replace(/\s/g, "");
            const expiryMonth = document.getElementById("wx-exp-m").value;
            const expiryYear = document.getElementById("wx-exp-y").value;
            const cvv = cvvInput.value;

            if (
                cardNumber.length < 12 ||
                !expiryMonth ||
                !expiryYear ||
                cvv.length < 3
            ) {
                setError(errorEl, "Please fill in all card fields.");
                return;
            }

            document.getElementById("wx-card-form-actions").style.display =
                "none";
            document.getElementById("wx-card-outcome").style.display = "";
        };

    const cardOutcomeButtons = Array.from(
        modal.querySelectorAll("#wx-card-outcome .wx-outcome-btn"),
    );
    cardOutcomeButtons.forEach(function (btn) {
        btn.onclick = function () {
            submitCharge(btn.dataset.outcome, "card", cardOutcomeButtons);
        };
    });

    const transferOutcomeButtons = Array.from(
        modal.querySelectorAll("#wx-transfer-outcome .wx-outcome-btn"),
    );
    transferOutcomeButtons.forEach(function (btn) {
        btn.onclick = function () {
            submitCharge(
                btn.dataset.outcome,
                "bank_transfer",
                transferOutcomeButtons,
            );
        };
    });

    async function cancelTransaction() {
        if (!confirm("Are you sure you want to cancel this transaction?"))
            return;

        clearCountdown();

        try {
            const res = await fetch(cancelUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
            });
            const data = await res.json();

            const redirectUrl = data.redirect_url || callbackUrl;
            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }

            const sidebar = document.getElementById("wx-sidebar");
            if (sidebar) sidebar.style.display = "none";
            document.getElementById("wx-form-wrap").style.display = "none";
            const wrap = document.getElementById("wx-result-wrap");
            wrap.style.display = "";
            wrap.innerHTML =
                '<div class="wx-result">' +
                '<div class="wx-result-icon wx-icon-failed">' +
                ICONS.xmark +
                "</div>" +
                '<p class="wx-result-title">Transaction Cancelled</p>' +
                '<p class="wx-result-subtitle">This transaction has been cancelled. You can safely close this page.</p>' +
                "</div>";
        } catch (err) {
            alert("Could not cancel the transaction. Please try again.");
        }
    }

    const cancelBtn = document.getElementById("wx-cancel-btn");
    if (cancelBtn) cancelBtn.onclick = cancelTransaction;
})();
