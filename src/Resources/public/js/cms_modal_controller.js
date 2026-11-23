import { Controller as e } from "@hotwired/stimulus";
// src/Resources/assets/src/logger.ts
function t() {
	try {
		return globalThis.NOWO_CONSOLE === "quiet" || typeof document < "u" && document.documentElement.dataset.nowoConsole === "quiet";
	} catch {
		return !1;
	}
}
var n = {
	script: "color:#0ea5e9;font-weight:bold",
	debug: "color:#6b7280",
	info: "color:#2563eb",
	warn: "color:#d97706",
	error: "color:#dc2626;font-weight:bold"
}, r = {
	script: "📦",
	debug: "🔍",
	info: "ℹ️",
	warn: "⚠️",
	error: "❌"
};
function i(e) {
	return e.map((e) => typeof e == "object" && e && !(e instanceof Error) ? JSON.stringify(e) : e);
}
function a(e, a = {}) {
	let o = `[${e}]`, { buildTime: s } = a, c = !1;
	return {
		scriptLoaded() {
			t() || (s !== void 0 && s !== "" ? console.log(`%c${r.script} ${o} script loaded, build: %c${s}`, n.script, "color:#059669") : console.log(`%c${r.script} ${o} script loaded`, n.script));
		},
		setDebug(e) {
			c = e;
		},
		debug(...e) {
			c && (e.length > 0 ? console.debug(`%c${r.debug} ${o}`, n.debug, ...i(e)) : console.debug(`%c${r.debug} ${o}`, n.debug));
		},
		info(...e) {
			c && (e.length > 0 ? console.info(`%c${r.info} ${o}`, n.info, ...i(e)) : console.info(`%c${r.info} ${o}`, n.info));
		},
		warn(...e) {
			c && (e.length > 0 ? console.warn(`%c${r.warn} ${o}`, n.warn, ...i(e)) : console.warn(`%c${r.warn} ${o}`, n.warn));
		},
		error(...e) {
			c && (e.length > 0 ? console.error(`%c${r.error} ${o}`, n.error, ...i(e)) : console.error(`%c${r.error} ${o}`, n.error));
		}
	};
}
// src/Resources/assets/src/cms_modal_controller.ts
a("page-layout-kit", { buildTime: "2026-11-23 13:58 UTC" }).scriptLoaded();
var o = (e) => encodeURIComponent(e), s = class t extends e {
	static targets = [
		"modal",
		"body",
		"title",
		"submit"
	];
	static values = {
		pageBlockUrl: String,
		serviceEditUrl: String,
		serviceCreateUrl: String,
		serviceFaqCreateUrl: String,
		serviceFaqEditUrl: String,
		caseEditUrl: String,
		caseCreateUrl: String,
		pageSeoEditUrl: String,
		blogEditUrl: String
	};
	previousActiveElement = null;
	observedModal = null;
	static ckeditorAutoInitPatched = !1;
	static ckeditorInitRoot = null;
	static ckeditorMountLocks = /* @__PURE__ */ new WeakSet();
	connect() {
		this.patchCkeditorAutoInit(), document.addEventListener("keydown", this.onKeydown), this.observedModal = this.modalTarget, this.observedModal.addEventListener("close", this.onDialogClose);
	}
	disconnect() {
		document.removeEventListener("keydown", this.onKeydown), this.observedModal?.removeEventListener("close", this.onDialogClose), this.observedModal = null;
	}
	onKeydown = (e) => {
		e.key === "Escape" && !this.isDialog() && !this.modalTarget.hidden && this.close();
	};
	onDialogClose = () => {
		this.reset();
	};
	open(e) {
		e.preventDefault(), e.stopPropagation();
		let t = e.currentTarget, n = t.dataset.cmsModalResourceParam, r = t.dataset.cmsModalIdParam, i = t.dataset.cmsModalBlockTypeParam, a = t.dataset.cmsModalFaqIdParam, o = t.dataset.cmsModalPageKeyParam, s = t.dataset.cmsModalModeParam ?? (r ? "edit" : "create"), c = this.resolveUrl(n, s, r, i, a, o);
		c && (this.setTitle(t.dataset.cmsModalTitleParam ?? ""), this.previousActiveElement = t, this.loadUrl(c, n, r, i));
	}
	close() {
		let e = this.modalTarget;
		if (e instanceof HTMLDialogElement) {
			if (e.open && typeof e.close == "function") {
				e.close();
				return;
			}
			e.removeAttribute("open"), this.reset();
		} else e.hidden = !0, this.reset();
	}
	isDialog() {
		return this.modalTarget instanceof HTMLDialogElement;
	}
	show() {
		let e = this.modalTarget;
		e instanceof HTMLDialogElement ? e.open || (typeof e.showModal == "function" ? e.showModal() : e.setAttribute("open", "")) : e.hidden = !1;
	}
	reset() {
		document.body.classList.remove("cms-modal-open"), this.bodyTarget.innerHTML = "", this.setSubmitEnabled(!1);
		let e = this.previousActiveElement;
		this.previousActiveElement = null, e?.focus();
	}
	resolveUrl(e, t, n, r, i, a) {
		return e === "page-block" && r && n ? this.pageBlockUrlValue.replace("__TYPE__", o(r)).replace("__ID__", o(n)) : e === "service" ? t === "create" ? this.serviceCreateUrlValue : this.serviceEditUrlValue.replace("__ID__", o(n ?? "0")) : e === "service-faq" && n ? t === "create" ? this.serviceFaqCreateUrlValue.replace("__ID__", o(n)) : this.serviceFaqEditUrlValue.replace("__ID__", o(n)).replace("__FAQ_ID__", o(i ?? "0")) : e === "case" ? t === "create" ? this.caseCreateUrlValue : this.caseEditUrlValue.replace("__ID__", o(n ?? "0")) : e === "page-seo" && a ? this.pageSeoEditUrlValue.replace("__PAGE_KEY__", o(a)) : e === "blog" ? this.blogEditUrlValue.replace("__ID__", o(n ?? "0")) : null;
	}
	setTitle(e) {
		this.hasTitleTarget && e !== "" && (this.titleTarget.textContent = e);
	}
	async loadUrl(e, t, n, r) {
		this.show(), document.body.classList.add("cms-modal-open"), this.setSubmitEnabled(!1), this.bodyTarget.innerHTML = "<p class=\"cms-modal__loading\">Loading…</p>";
		try {
			let i = await fetch(e, { headers: { "X-Requested-With": "XMLHttpRequest" } });
			await this.applyModalFormHtml(await i.text(), t, n, r);
		} catch {
			this.bodyTarget.innerHTML = "<p class=\"cms-modal__loading cms-modal__loading--error\">Could not load the form.</p>";
		}
	}
	async applyModalFormHtml(e, t, n, r) {
		await this.ensureCkeditorReady(), this.bodyTarget.innerHTML = e, this.excludeModalCkeditorRootsFromGlobalObserver(this.bodyTarget), this.bindLocaleTabCkeditorRefresh(this.bodyTarget), this.bindFormSubmit(t, n, r), this.setSubmitEnabled(!!this.bodyTarget.querySelector("#cms-modal-active-form")), await this.mountRichTextEditorsForActivePanels();
	}
	bindLocaleTabCkeditorRefresh(e) {
		e.addEventListener("nowo-ui:tabs:change", (e) => {
			let t = e.detail?.panel ?? null;
			this.mountRichTextEditorsForActivePanels(t);
		});
	}
	async mountRichTextEditorsForActivePanels(e = null) {
		let n = t.ckeditorInitRoot;
		if (!n) return;
		let r = (e ?? this.bodyTarget).querySelectorAll(".ckeditor5-editor-widget");
		for (let i of r) {
			let r = i.closest("[data-nowo-ui-tabs-panel]");
			if (e === null && r && (r.hidden || !r.hasAttribute("data-active")) || t.ckeditorMountLocks.has(i)) continue;
			let a = i.querySelectorAll(".ckeditor5-editor-chrome > .ck-editor").length;
			if (!(i.dataset.ckeditor5Initialized === "1" && a <= 1)) {
				t.ckeditorMountLocks.add(i), i.removeAttribute("data-ckeditor5-root"), this.teardownCkeditorRoot(i);
				try {
					await n(i);
				} finally {
					t.ckeditorMountLocks.delete(i);
				}
			}
		}
	}
	patchCkeditorAutoInit() {
		if (t.ckeditorAutoInitPatched) return;
		let e = window.NowoCkeditor5Editor;
		if (!e) return;
		let n = e.initCkeditor5Root.bind(e);
		t.ckeditorInitRoot = n;
		let r = (e) => e.closest("[data-cms-modal-target=\"body\"]") !== null, i = async (e) => {
			if (!t.ckeditorMountLocks.has(e) && e.dataset.ckeditor5Initialized !== "1" && !r(e)) {
				t.ckeditorMountLocks.add(e);
				try {
					await n(e);
				} finally {
					t.ckeditorMountLocks.delete(e);
				}
			}
		};
		e.initCkeditor5Root = i, e.runInit &&= async () => {
			let e = document.querySelectorAll("[data-ckeditor5-root=\"1\"]");
			for (let t of e) await i(t);
		}, t.ckeditorAutoInitPatched = !0;
	}
	excludeModalCkeditorRootsFromGlobalObserver(e) {
		e.querySelectorAll("[data-ckeditor5-root]").forEach((e) => {
			e.removeAttribute("data-ckeditor5-root");
		});
	}
	teardownCkeditorRoot(e) {
		e.querySelectorAll(".ckeditor5-editor-chrome > .ck-editor").forEach((e) => {
			e.remove();
		});
		let t = e.querySelector("[data-ckeditor5-mount]");
		t && (t.innerHTML = "", t.style.removeProperty("display")), delete e.dataset.ckeditor5Initialized;
	}
	async ensureCkeditorReady() {
		for (let e = 0; e < 40; e += 1) {
			if (this.patchCkeditorAutoInit(), t.ckeditorInitRoot) return;
			await new Promise((e) => {
				window.setTimeout(e, 50);
			});
		}
	}
	setSubmitEnabled(e) {
		this.hasSubmitTarget && (this.submitTarget.disabled = !e);
	}
	bindFormSubmit(e, t, n) {
		let r = this.bodyTarget.querySelector("form");
		r && r.addEventListener("submit", async (i) => {
			i.preventDefault();
			let a = await fetch(r.action, {
				method: "POST",
				body: new FormData(r),
				headers: { "X-Requested-With": "XMLHttpRequest" }
			});
			a.redirected || a.ok ? this.reloadPage() : await this.applyModalFormHtml(await a.text(), e, t, n);
		});
	}
	reloadPage() {
		window.location.reload();
	}
};
export { s as default };
