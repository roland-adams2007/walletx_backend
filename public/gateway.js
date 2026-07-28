(function (window) {
    const CSS_ID = "wx-styles";
    const CSS_HREF = "gateway.css";
    const DEFAULT_LOGO = "/logo.png";

    function ensureStyles() {
        if (document.getElementById(CSS_ID)) return;
        const existing = document.querySelector(`link[href$="${CSS_HREF}"]`);
        if (existing) return;
        const link = document.createElement("link");
        link.id = CSS_ID;
        link.rel = "stylesheet";
        link.href = resolveCssPath();
        document.head.appendChild(link);
    }

    // Both the stylesheet and the API base URL live wherever gateway.js
    // itself was loaded from — the <script src="..."> tag already tells us
    // that, so callers never need to pass a baseUrl in.
    function resolveScriptTag() {
        return (
            document.currentScript ||
            document.querySelector('script[src*="gateway.js"]')
        );
    }

    function resolveCssPath() {
        const script = resolveScriptTag();
        if (!script) return CSS_HREF;
        return script.src.replace(/gateway\.js(\?.*)?$/, CSS_HREF);
    }

    function resolveBaseUrl() {
        const script = resolveScriptTag();
        if (!script) return "";
        return script.src.replace(/\/gateway\.js(\?.*)?$/, "");
    }

    const ICONS = {
        close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        card: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/></svg>',
        transfer:
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10 3 6l4-4M3 6h13a4 4 0 0 1 4 4v1M17 14l4 4-4 4M21 18H8a4 4 0 0 1-4-4v-1"/></svg>',
        check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
        alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
    };

    function formatAmount(kobo) {
        const naira = kobo / 100;
        const formatted = naira.toLocaleString("en-NG", {
            minimumFractionDigits: naira % 1 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        });
        return `NGN ${formatted}`;
    }

    function detectCardBrand(digits) {
        if (/^4/.test(digits)) return "VISA";
        if (/^5[1-5]/.test(digits) || /^2(2[2-9]|[3-6]\d|7[01])/.test(digits))
            return "MASTERCARD";
        if (/^506(0|1)|^5078/.test(digits)) return "VERVE";
        return "";
    }

    function WalletXGateway(config) {
        if (!config.reference) {
            throw new Error(
                "WalletXGateway.setup() requires a reference — generate it on your own page and pass it in.",
            );
        }

        this.publicKey = config.key;
        this.email = config.email;
        this.firstname = config.firstname || "";
        this.lastname = config.lastname || "";
        // Provisional amount — openModal() calls your server's `initialise`
        // endpoint and overwrites this with the confirmed amount (and
        // merchant name/logo) before anything is rendered. The reference
        // always comes from the caller, never generated in here.
        this.amount = config.amount;
        this.reference = config.reference;
        this.merchant = { name: "Merchant", logo: DEFAULT_LOGO };
        this.onSuccess = config.callback;
        this.onClose = config.onClose || function () {};
        this.onError =
            config.onError ||
            function (message) {
                alert(message);
            };
        // Resolved from the <script src="..."> tag, not from config —
        // gateway.js always knows where it was loaded from.
        this.baseUrl = resolveBaseUrl();
    }

    // One round-trip: resolves the business from `key`, creates the pending
    // transaction for `ref`, and hands back merchant name/logo in the same
    // response (see InlineController::initialise) — the caller never has
    // to fetch or pass merchant details itself.
    WalletXGateway.prototype.initialiseTransaction = async function () {
        const res = await fetch(
            this.baseUrl + "/api/transaction/initialize/inline",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    key: this.publicKey,
                    email: this.email,
                    amount: this.amount,
                    firstname: this.firstname,
                    lastname: this.lastname,
                    ref: this.reference,
                }),
            },
        );

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || "Could not start the transaction.");
        }

        return data.data; // { reference, amount, access_code, merchant }
    };

    WalletXGateway.prototype.openModal = async function () {
        ensureStyles();

        const self = this;

        try {
            const initData = await self.initialiseTransaction();
            self.amount = initData.amount;
            self.reference = initData.reference;
            self.merchant = {
                name:
                    (initData.merchant && initData.merchant.name) || "Merchant",
                logo:
                    (initData.merchant && initData.merchant.logo) ||
                    DEFAULT_LOGO,
            };
        } catch (err) {
            self.onError(err.message || "Could not start the transaction.");
            return;
        }

        self.renderModal();
    };

    WalletXGateway.prototype.renderModal = function () {
        const self = this;
        const merchant = self.merchant;

        const overlay = document.createElement("div");
        overlay.className = "wx-overlay";

        const modal = document.createElement("div");
        modal.className = "wx-modal";

        modal.innerHTML = `
      <div class="wx-grabber"></div>
      <button class="wx-close" id="wx-close-btn" aria-label="Close">${ICONS.close}</button>

      <div class="wx-sidebar">
        <p class="wx-sidebar-label">PAY WITH</p>
        <div class="wx-method active" data-tab="card">${ICONS.card}<span>Card</span></div>
        <div class="wx-method" data-tab="transfer">${ICONS.transfer}<span>Transfer</span></div>
      </div>

      <div class="wx-content">
        <div class="wx-topbar">
          <div class="wx-brandmark-row">
            <img class="wx-brandmark" id="wx-brandmark" src="${merchant.logo}" alt="${merchant.name}" />
            <span class="wx-brand-name">${merchant.name}</span>
          </div>
          <div class="wx-topbar-right">
            <p class="wx-email">${self.email}</p>
            <p class="wx-pay-line">Pay <strong>${formatAmount(self.amount)}</strong></p>
          </div>
        </div>

        <div class="wx-panel" data-panel="card">
          <p class="wx-panel-title">Pay with card</p>
          <p class="wx-transfer-note">Test mode: a demo card is prefilled, no real card is charged.</p>
          <div class="wx-field">
            <label for="wx-card">Card number</label>
            <div class="wx-input-wrap">
              <input id="wx-card" placeholder="0000 0000 0000 0000" maxlength="19" inputmode="numeric" autocomplete="cc-number" value="4242 4242 4242 4242" />
              <span class="wx-card-brand" id="wx-card-brand">VISA</span>
            </div>
          </div>
          <div class="wx-row">
            <div class="wx-field">
              <label for="wx-exp-m">Expiry</label>
              <div style="display:flex; gap:6px;">
                <input id="wx-exp-m" placeholder="MM" maxlength="2" inputmode="numeric" autocomplete="cc-exp-month" value="12" />
                <input id="wx-exp-y" placeholder="YY" maxlength="2" inputmode="numeric" autocomplete="cc-exp-year" value="30" />
              </div>
            </div>
            <div class="wx-field">
              <label for="wx-cvv">CVV</label>
              <input id="wx-cvv" placeholder="123" maxlength="3" inputmode="numeric" autocomplete="cc-csc" value="123" />
            </div>
          </div>
          <p class="wx-error" id="wx-error"></p>

          <div id="wx-card-form-actions">
            <button class="wx-btn" id="wx-pay-btn"><span id="wx-pay-btn-text">Pay <span class="wx-amount">${formatAmount(self.amount)}</span></span></button>
          </div>

          <div id="wx-card-outcome" style="display:none;">
            <p class="wx-panel-title" style="font-size:14px;">Choose an outcome to simulate</p>
            <div style="display:flex; gap:8px; margin:10px 0;">
              <button class="wx-outcome-btn" data-outcome="success" style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #16a34a;color:#16a34a;background:transparent;font-weight:600;cursor:pointer;">Success</button>
              <button class="wx-outcome-btn" data-outcome="pending" style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #d97706;color:#d97706;background:transparent;font-weight:600;cursor:pointer;">Pending</button>
              <button class="wx-outcome-btn" data-outcome="failed" style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #dc2626;color:#dc2626;background:transparent;font-weight:600;cursor:pointer;">Failed</button>
            </div>
          </div>
        </div>

        <div class="wx-panel" data-panel="transfer" style="display:none;">
          <p class="wx-panel-title">Transfer ${formatAmount(self.amount)} to ${merchant.name}</p>
          <p class="wx-transfer-note">Test mode: no real account number is generated. Choose an outcome to simulate the transfer.</p>

          <div id="wx-transfer-outcome" style="display:flex; gap:8px; margin:16px 0;">
            <button class="wx-outcome-btn" data-outcome="success" style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #16a34a;color:#16a34a;background:transparent;font-weight:600;cursor:pointer;">Success</button>
            <button class="wx-outcome-btn" data-outcome="pending" style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #d97706;color:#d97706;background:transparent;font-weight:600;cursor:pointer;">Pending</button>
            <button class="wx-outcome-btn" data-outcome="failed" style="flex:1;padding:10px;border-radius:8px;border:1.5px solid #dc2626;color:#dc2626;background:transparent;font-weight:600;cursor:pointer;">Failed</button>
          </div>

          <p class="wx-error" id="wx-transfer-error"></p>
        </div>

        <p class="wx-secure">${ICONS.lock}Secured by <strong>WalletX</strong></p>
      </div>
    `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // If the business's own logo fails to load (bad S3 url, CORS, etc.)
        // fall back to the platform logo rather than showing a broken image.
        const brandmark = modal.querySelector("#wx-brandmark");
        brandmark.onerror = function () {
            brandmark.onerror = null;
            brandmark.src = self.baseUrl + DEFAULT_LOGO;
        };

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

        const cardInput = modal.querySelector("#wx-card");
        const brandEl = modal.querySelector("#wx-card-brand");
        cardInput.oninput = function () {
            const digits = cardInput.value.replace(/\D/g, "").slice(0, 16);
            cardInput.value = digits.replace(/(.{4})/g, "$1 ").trim();
            brandEl.textContent = detectCardBrand(digits);
        };
        modal.querySelector("#wx-exp-m").oninput = function (e) {
            e.target.value = e.target.value.replace(/\D/g, "").slice(0, 2);
        };
        modal.querySelector("#wx-exp-y").oninput = function (e) {
            e.target.value = e.target.value.replace(/\D/g, "").slice(0, 2);
        };
        modal.querySelector("#wx-cvv").oninput = function (e) {
            e.target.value = e.target.value.replace(/\D/g, "").slice(0, 3);
        };

        function setError(el, message) {
            if (!message) {
                el.classList.remove("wx-error-visible");
                el.innerHTML = "";
                return;
            }
            el.innerHTML = ICONS.alert + "<span>" + message + "</span>";
            el.classList.add("wx-error-visible");
        }

        // Card: clicking Pay doesn't charge anything yet, it just reveals
        // the outcome picker so the tester can choose what happens.
        modal.querySelector("#wx-pay-btn").onclick = function () {
            const card_number = cardInput.value.replace(/\s/g, "");
            const expiry_month = modal.querySelector("#wx-exp-m").value;
            const expiry_year = modal.querySelector("#wx-exp-y").value;
            const cvv = modal.querySelector("#wx-cvv").value;
            const errorEl = modal.querySelector("#wx-error");

            setError(errorEl, "");

            if (
                card_number.length < 12 ||
                !expiry_month ||
                !expiry_year ||
                cvv.length < 3
            ) {
                setError(errorEl, "Please fill in all card fields.");
                return;
            }

            modal.querySelector("#wx-card-form-actions").style.display = "none";
            modal.querySelector("#wx-card-outcome").style.display = "";
        };

        // Both card and transfer post to the same /payments/charge endpoint.
        // `channel` tells the backend which path to run, `simulate` drives
        // the outcome. Nothing else needs to be resent — the backend
        // already has amount/email tied to this reference from initialise.
        async function submitOutcome(outcome, channel, buttons) {
            const isCard = channel === "card";
            const errorEl = modal.querySelector(
                isCard ? "#wx-error" : "#wx-transfer-error",
            );

            setError(errorEl, "");
            buttons.forEach(function (b) {
                b.disabled = true;
            });

            const body = isCard
                ? {
                      public_key: self.publicKey,
                      reference: self.reference,
                      channel: "card",
                      card_number: cardInput.value.replace(/\s/g, ""),
                      expiry_month: modal.querySelector("#wx-exp-m").value,
                      expiry_year: modal.querySelector("#wx-exp-y").value,
                      cvv: modal.querySelector("#wx-cvv").value,
                      simulate: outcome,
                  }
                : {
                      public_key: self.publicKey,
                      reference: self.reference,
                      channel: "bank_transfer",
                      simulate: outcome,
                  };

            try {
                const res = await fetch(self.baseUrl + "/api/payments/charge", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                    },
                    body: JSON.stringify(body),
                });

                const data = await res.json();

                if (!data.success) {
                    setError(errorEl, data.message || "Payment failed.");
                    buttons.forEach(function (b) {
                        b.disabled = false;
                    });
                    return;
                }

                document.body.removeChild(overlay);
                self.onSuccess(data);
            } catch (err) {
                setError(errorEl, "Something went wrong.");
                buttons.forEach(function (b) {
                    b.disabled = false;
                });
            }
        }

        const cardOutcomeButtons = modal.querySelectorAll(
            "#wx-card-outcome .wx-outcome-btn",
        );
        cardOutcomeButtons.forEach(function (btn) {
            btn.onclick = function () {
                submitOutcome(btn.dataset.outcome, "card", cardOutcomeButtons);
            };
        });

        const transferOutcomeButtons = modal.querySelectorAll(
            "#wx-transfer-outcome .wx-outcome-btn",
        );
        transferOutcomeButtons.forEach(function (btn) {
            btn.onclick = function () {
                submitOutcome(
                    btn.dataset.outcome,
                    "bank_transfer",
                    transferOutcomeButtons,
                );
            };
        });

        modal.querySelector("#wx-close-btn").onclick = function () {
            document.body.removeChild(overlay);
            self.onClose();
        };
    };

    window.WalletXGateway = {
        setup: function (config) {
            return new WalletXGateway(config);
        },
    };
})(window);
