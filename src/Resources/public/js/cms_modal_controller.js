import { Controller } from "@hotwired/stimulus";
// src/Resources/assets/src/cms_modal_controller.ts
/**
* Unified modal for inline CMS editing (page blocks, services, cases, service FAQs).
*
* Register it under the `cms-modal` identifier used by the bundle templates:
* `application.register('cms-modal', CmsModalController)`.
*/
var CmsModalController = class CmsModalController extends Controller {
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
	static ckeditorAutoInitPatched = false;
	static ckeditorInitRoot = null;
	static ckeditorMountLocks = /* @__PURE__ */ new WeakSet();
	connect() {
		this.patchCkeditorAutoInit();
		document.addEventListener("keydown", this.onKeydown);
	}
	disconnect() {
		document.removeEventListener("keydown", this.onKeydown);
	}
	/**
	* Closes the open modal on Escape (bound once per instance so disconnect() can remove it).
	*/
	onKeydown = (event) => {
		if (event.key === "Escape" && !this.modalTarget.hidden) this.close();
	};
	open(event) {
		event.preventDefault();
		event.stopPropagation();
		const button = event.currentTarget;
		const resource = button.dataset.cmsModalResourceParam;
		const id = button.dataset.cmsModalIdParam;
		const blockType = button.dataset.cmsModalBlockTypeParam;
		const faqId = button.dataset.cmsModalFaqIdParam;
		const pageKey = button.dataset.cmsModalPageKeyParam;
		const mode = button.dataset.cmsModalModeParam ?? (id ? "edit" : "create");
		const url = this.resolveUrl(resource, mode, id, blockType, faqId, pageKey);
		if (!url) return;
		this.setTitle(button.dataset.cmsModalTitleParam ?? "");
		this.loadUrl(url, resource, id, blockType);
		this.previousActiveElement = button;
	}
	close() {
		this.modalTarget.hidden = true;
		document.body.classList.remove("cms-modal-open");
		this.bodyTarget.innerHTML = "";
		this.setSubmitEnabled(false);
		this.previousActiveElement?.focus();
	}
	resolveUrl(resource, mode, id, blockType, faqId, pageKey) {
		if ("page-block" === resource && blockType && id) return this.pageBlockUrlValue.replace("__TYPE__", blockType).replace("__ID__", id);
		if ("service" === resource) return "create" === mode ? this.serviceCreateUrlValue : this.serviceEditUrlValue.replace("__ID__", id ?? "0");
		if ("service-faq" === resource && id) {
			if ("create" === mode) return this.serviceFaqCreateUrlValue.replace("__ID__", id);
			return this.serviceFaqEditUrlValue.replace("__ID__", id).replace("__FAQ_ID__", faqId ?? "0");
		}
		if ("case" === resource) return "create" === mode ? this.caseCreateUrlValue : this.caseEditUrlValue.replace("__ID__", id ?? "0");
		if ("page-seo" === resource && pageKey) return this.pageSeoEditUrlValue.replace("__PAGE_KEY__", pageKey);
		if ("blog" === resource) return this.blogEditUrlValue.replace("__ID__", id ?? "0");
		return null;
	}
	setTitle(title) {
		if (this.hasTitleTarget && "" !== title) this.titleTarget.textContent = title;
	}
	async loadUrl(url, resource, id, blockType) {
		this.modalTarget.hidden = false;
		document.body.classList.add("cms-modal-open");
		this.setSubmitEnabled(false);
		this.bodyTarget.innerHTML = "<p class=\"cms-modal__loading\">Loading…</p>";
		try {
			const response = await fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } });
			await this.applyModalFormHtml(await response.text(), resource, id, blockType);
		} catch {
			this.bodyTarget.innerHTML = "<p class=\"cms-modal__loading cms-modal__loading--error\">Could not load the form.</p>";
		}
	}
	async applyModalFormHtml(html, resource, id, blockType) {
		await this.ensureCkeditorReady();
		this.bodyTarget.innerHTML = html;
		this.excludeModalCkeditorRootsFromGlobalObserver(this.bodyTarget);
		this.bindLocaleTabCkeditorRefresh(this.bodyTarget);
		this.bindFormSubmit(resource, id, blockType);
		this.setSubmitEnabled(Boolean(this.bodyTarget.querySelector("#cms-modal-active-form")));
		await this.mountRichTextEditorsForActivePanels();
	}
	/**
	* Mount CKEditor in the panel a locale tab opens (UiKit `nowo-ui:tabs:change`, `detail.panel`).
	*/
	bindLocaleTabCkeditorRefresh(container) {
		container.addEventListener("nowo-ui:tabs:change", (event) => {
			const panel = event.detail?.panel ?? null;
			this.mountRichTextEditorsForActivePanels(panel);
		});
	}
	/**
	* Mount CKEditor only on visible (active) locale panels to avoid tripling cost.
	*/
	async mountRichTextEditorsForActivePanels(scope = null) {
		const initRoot = CmsModalController.ckeditorInitRoot;
		if (!initRoot) return;
		const roots = (scope ?? this.bodyTarget).querySelectorAll(".ckeditor5-editor-widget");
		for (const root of roots) {
			const panel = root.closest("[data-nowo-ui-tabs-panel]");
			if (scope === null && panel && (panel.hidden || !panel.hasAttribute("data-active"))) continue;
			if (CmsModalController.ckeditorMountLocks.has(root)) continue;
			const existingEditors = root.querySelectorAll(".ckeditor5-editor-chrome > .ck-editor").length;
			if ("1" === root.dataset.ckeditor5Initialized && existingEditors <= 1) continue;
			CmsModalController.ckeditorMountLocks.add(root);
			root.removeAttribute("data-ckeditor5-root");
			this.teardownCkeditorRoot(root);
			try {
				await initRoot(root);
			} finally {
				CmsModalController.ckeditorMountLocks.delete(root);
			}
		}
	}
	/**
	* The bundle MutationObserver calls `up()` directly (not only the public API).
	* Modal fields are excluded from auto-discovery and mounted once manually.
	*/
	patchCkeditorAutoInit() {
		if (CmsModalController.ckeditorAutoInitPatched) return;
		const api = window.NowoCkeditor5Editor;
		if (!api) return;
		const originalInit = api.initCkeditor5Root.bind(api);
		CmsModalController.ckeditorInitRoot = originalInit;
		const isModalRoot = (root) => {
			return null !== root.closest("[data-cms-modal-target=\"body\"]");
		};
		const guardedInit = async (root) => {
			if (CmsModalController.ckeditorMountLocks.has(root)) return;
			if ("1" === root.dataset.ckeditor5Initialized) return;
			if (isModalRoot(root)) return;
			CmsModalController.ckeditorMountLocks.add(root);
			try {
				await originalInit(root);
			} finally {
				CmsModalController.ckeditorMountLocks.delete(root);
			}
		};
		api.initCkeditor5Root = guardedInit;
		if (api.runInit) api.runInit = async () => {
			const roots = document.querySelectorAll("[data-ckeditor5-root=\"1\"]");
			for (const root of roots) await guardedInit(root);
		};
		CmsModalController.ckeditorAutoInitPatched = true;
	}
	/**
	* Removes the auto-discovery marker before the global observer can init modal fields.
	*/
	excludeModalCkeditorRootsFromGlobalObserver(container) {
		container.querySelectorAll("[data-ckeditor5-root]").forEach((root) => {
			root.removeAttribute("data-ckeditor5-root");
		});
	}
	teardownCkeditorRoot(root) {
		root.querySelectorAll(".ckeditor5-editor-chrome > .ck-editor").forEach((editor) => {
			editor.remove();
		});
		const mount = root.querySelector("[data-ckeditor5-mount]");
		if (mount) {
			mount.innerHTML = "";
			mount.style.removeProperty("display");
		}
		delete root.dataset.ckeditor5Initialized;
	}
	async ensureCkeditorReady() {
		for (let attempt = 0; attempt < 40; attempt += 1) {
			this.patchCkeditorAutoInit();
			if (CmsModalController.ckeditorInitRoot) return;
			await new Promise((resolve) => {
				window.setTimeout(resolve, 50);
			});
		}
	}
	setSubmitEnabled(enabled) {
		if (!this.hasSubmitTarget) return;
		this.submitTarget.disabled = !enabled;
	}
	bindFormSubmit(resource, id, blockType) {
		const form = this.bodyTarget.querySelector("form");
		if (!form) return;
		form.addEventListener("submit", async (event) => {
			event.preventDefault();
			const response = await fetch(form.action, {
				method: "POST",
				body: new FormData(form),
				headers: { "X-Requested-With": "XMLHttpRequest" }
			});
			if (response.redirected || response.ok) {
				this.reloadPage();
				return;
			}
			await this.applyModalFormHtml(await response.text(), resource, id, blockType);
		});
	}
	/**
	* Reloads the host page after a successful save so the edited block re-renders server-side.
	*/
	reloadPage() {
		window.location.reload();
	}
};
export { CmsModalController as default };
