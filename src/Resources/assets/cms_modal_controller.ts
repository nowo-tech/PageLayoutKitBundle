import { Controller } from '@hotwired/stimulus';

type CmsResource = 'page-block' | 'service' | 'case' | 'service-faq' | 'page-seo' | 'blog';

type Ckeditor5EditorApi = {
    initCkeditor5Root: (root: HTMLElement) => Promise<void>;
    runInit?: () => Promise<void>;
};

declare global {
    interface Window {
        NowoCkeditor5Editor?: Ckeditor5EditorApi;
    }
}

/**
 * Unified modal for inline CMS editing (page blocks, services, cases, service FAQs).
 */
export default class CmsModalController extends Controller {
    static targets = ['modal', 'body', 'title', 'submit'];

    declare readonly submitTarget: HTMLButtonElement;
    declare readonly hasSubmitTarget: boolean;

    static values = {
        pageBlockUrl: String,
        serviceEditUrl: String,
        serviceCreateUrl: String,
        serviceFaqCreateUrl: String,
        serviceFaqEditUrl: String,
        caseEditUrl: String,
        caseCreateUrl: String,
        pageSeoEditUrl: String,
        blogEditUrl: String,
    };

    declare readonly modalTarget: HTMLElement;
    declare readonly bodyTarget: HTMLElement;
    declare readonly hasTitleTarget: boolean;
    declare readonly titleTarget: HTMLElement;
    declare readonly pageBlockUrlValue: string;
    declare readonly serviceEditUrlValue: string;
    declare readonly serviceCreateUrlValue: string;
    declare readonly serviceFaqCreateUrlValue: string;
    declare readonly serviceFaqEditUrlValue: string;
    declare readonly caseEditUrlValue: string;
    declare readonly caseCreateUrlValue: string;
    declare readonly pageSeoEditUrlValue: string;
    declare readonly blogEditUrlValue: string;

    private previousActiveElement: HTMLElement | null = null;

    private static ckeditorAutoInitPatched = false;
    private static ckeditorInitRoot: ((root: HTMLElement) => Promise<void>) | null = null;
    private static readonly ckeditorMountLocks = new WeakSet<HTMLElement>();

    connect(): void {
        this.patchCkeditorAutoInit();
        this.onKeydown = (event: KeyboardEvent): void => {
            if (event.key === 'Escape' && !this.modalTarget.hidden) {
                this.close();
            }
        };
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect(): void {
        document.removeEventListener('keydown', this.onKeydown);
    }

    private onKeydown: (event: KeyboardEvent) => void = () => {};

    open(event: Event): void {
        event.preventDefault();
        event.stopPropagation();

        const button = event.currentTarget as HTMLElement;
        const resource = button.dataset.cmsModalResourceParam as CmsResource | undefined;
        const id = button.dataset.cmsModalIdParam;
        const blockType = button.dataset.cmsModalBlockTypeParam;
        const faqId = button.dataset.cmsModalFaqIdParam;
        const pageKey = button.dataset.cmsModalPageKeyParam;
        const mode = button.dataset.cmsModalModeParam ?? (id ? 'edit' : 'create');

        const url = this.resolveUrl(resource, mode, id, blockType, faqId, pageKey);
        if (!url) {
            return;
        }

        this.setTitle(button.dataset.cmsModalTitleParam ?? '');
        void this.loadUrl(url, resource, id, blockType);
        this.previousActiveElement = button;
    }

    close(): void {
        this.modalTarget.hidden = true;
        document.body.classList.remove('cms-modal-open');
        this.bodyTarget.innerHTML = '';
        this.setSubmitEnabled(false);
        this.previousActiveElement?.focus();
    }

    private resolveUrl(
        resource: CmsResource | undefined,
        mode: string,
        id?: string,
        blockType?: string,
        faqId?: string,
        pageKey?: string,
    ): string | null {
        if ('page-block' === resource && blockType && id) {
            return this.pageBlockUrlValue.replace('__TYPE__', blockType).replace('__ID__', id);
        }

        if ('service' === resource) {
            return 'create' === mode ? this.serviceCreateUrlValue : this.serviceEditUrlValue.replace('__ID__', id ?? '0');
        }

        if ('service-faq' === resource && id) {
            if ('create' === mode) {
                return this.serviceFaqCreateUrlValue.replace('__ID__', id);
            }

            return this.serviceFaqEditUrlValue
                .replace('__ID__', id)
                .replace('__FAQ_ID__', faqId ?? '0');
        }

        if ('case' === resource) {
            return 'create' === mode ? this.caseCreateUrlValue : this.caseEditUrlValue.replace('__ID__', id ?? '0');
        }

        if ('page-seo' === resource && pageKey) {
            return this.pageSeoEditUrlValue.replace('__PAGE_KEY__', pageKey);
        }

        if ('blog' === resource) {
            return this.blogEditUrlValue.replace('__ID__', id ?? '0');
        }

        return null;
    }

    private setTitle(title: string): void {
        if (this.hasTitleTarget && '' !== title) {
            this.titleTarget.textContent = title;
        }
    }

    private async loadUrl(
        url: string,
        resource?: CmsResource,
        id?: string,
        blockType?: string,
    ): Promise<void> {
        this.modalTarget.hidden = false;
        document.body.classList.add('cms-modal-open');
        this.setSubmitEnabled(false);
        this.bodyTarget.innerHTML = '<p class="cms-modal__loading">Loading…</p>';

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            await this.applyModalFormHtml(await response.text(), resource, id, blockType);
        } catch {
            this.bodyTarget.innerHTML = '<p class="cms-modal__loading cms-modal__loading--error">Could not load the form.</p>';
        }
    }

    private async applyModalFormHtml(
        html: string,
        resource?: CmsResource,
        id?: string,
        blockType?: string,
    ): Promise<void> {
        await this.ensureCkeditorReady();
        this.bodyTarget.innerHTML = html;
        this.excludeModalCkeditorRootsFromGlobalObserver(this.bodyTarget);
        this.bindLocaleTabCkeditorRefresh(this.bodyTarget);
        this.bindFormSubmit(resource, id, blockType);
        this.setSubmitEnabled(Boolean(this.bodyTarget.querySelector('#cms-modal-active-form')));
        await this.mountRichTextEditorsForActivePanels();
    }

    /**
     * Remount CKEditor when locale tabs switch inside the modal body.
     */
    private bindLocaleTabCkeditorRefresh(container: HTMLElement): void {
        container.addEventListener('locale-tabs:changed', () => {
            void this.mountRichTextEditorsForActivePanels();
        });
    }

    /**
     * Mount CKEditor only on visible (active) locale panels to avoid tripling cost.
     */
    private async mountRichTextEditorsForActivePanels(): Promise<void> {
        const initRoot = CmsModalController.ckeditorInitRoot;
        if (!initRoot) {
            return;
        }

        const roots = this.bodyTarget.querySelectorAll<HTMLElement>('.ckeditor5-editor-widget');
        for (const root of roots) {
            const panel = root.closest<HTMLElement>('[data-locale-panel]');
            if (panel && (panel.hidden || !panel.classList.contains('is-active'))) {
                continue;
            }

            if (CmsModalController.ckeditorMountLocks.has(root)) {
                continue;
            }

            const existingEditors = root.querySelectorAll('.ckeditor5-editor-chrome > .ck-editor').length;
            if ('1' === root.dataset.ckeditor5Initialized && existingEditors <= 1) {
                continue;
            }

            CmsModalController.ckeditorMountLocks.add(root);
            root.removeAttribute('data-ckeditor5-root');
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
    private patchCkeditorAutoInit(): void {
        if (CmsModalController.ckeditorAutoInitPatched) {
            return;
        }

        const api = window.NowoCkeditor5Editor;
        if (!api) {
            return;
        }

        const originalInit = api.initCkeditor5Root.bind(api);
        CmsModalController.ckeditorInitRoot = originalInit;

        const isModalRoot = (root: HTMLElement): boolean => {
            return null !== root.closest('[data-cms-modal-target="body"]');
        };

        const guardedInit = async (root: HTMLElement): Promise<void> => {
            if (CmsModalController.ckeditorMountLocks.has(root)) {
                return;
            }

            if ('1' === root.dataset.ckeditor5Initialized) {
                return;
            }

            if (isModalRoot(root)) {
                return;
            }

            CmsModalController.ckeditorMountLocks.add(root);
            try {
                await originalInit(root);
            } finally {
                CmsModalController.ckeditorMountLocks.delete(root);
            }
        };

        api.initCkeditor5Root = guardedInit;
        if (api.runInit) {
            api.runInit = async (): Promise<void> => {
                const roots = document.querySelectorAll<HTMLElement>('[data-ckeditor5-root="1"]');
                for (const root of roots) {
                    await guardedInit(root);
                }
            };
        }

        CmsModalController.ckeditorAutoInitPatched = true;
    }

    /**
     * Removes the auto-discovery marker before the global observer can init modal fields.
     */
    private excludeModalCkeditorRootsFromGlobalObserver(container: HTMLElement): void {
        container.querySelectorAll<HTMLElement>('[data-ckeditor5-root]').forEach((root) => {
            root.removeAttribute('data-ckeditor5-root');
        });
    }

    private teardownCkeditorRoot(root: HTMLElement): void {
        root.querySelectorAll('.ckeditor5-editor-chrome > .ck-editor').forEach((editor) => {
            editor.remove();
        });

        const mount = root.querySelector<HTMLElement>('[data-ckeditor5-mount]');
        if (mount) {
            mount.innerHTML = '';
            mount.style.removeProperty('display');
        }

        delete root.dataset.ckeditor5Initialized;
    }

    private async ensureCkeditorReady(): Promise<void> {
        for (let attempt = 0; attempt < 40; attempt += 1) {
            this.patchCkeditorAutoInit();
            if (CmsModalController.ckeditorInitRoot) {
                return;
            }

            await new Promise<void>((resolve) => {
                window.setTimeout(resolve, 50);
            });
        }
    }

    private setSubmitEnabled(enabled: boolean): void {
        if (!this.hasSubmitTarget) {
            return;
        }

        this.submitTarget.disabled = !enabled;
    }

    private bindFormSubmit(resource?: CmsResource, id?: string, blockType?: string): void {
        const form = this.bodyTarget.querySelector('form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.redirected || response.ok) {
                window.location.reload();
                return;
            }

            await this.applyModalFormHtml(await response.text(), resource, id, blockType);
        });
    }
}
