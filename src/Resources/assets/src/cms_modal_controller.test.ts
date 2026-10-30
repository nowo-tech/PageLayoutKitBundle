import type { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

type ControllerModule = typeof import('./cms_modal_controller');

type FakeResponse = { ok: boolean; redirected: boolean; text: () => Promise<string> };

const URL_VALUES = {
    'page-block-url': '/admin/page-blocks/__TYPE__/__ID__/edit-modal',
    'service-edit-url': '/admin/services/__ID__/edit',
    'service-create-url': '/admin/services/new',
    'service-faq-create-url': '/admin/services/__ID__/faqs/new',
    'service-faq-edit-url': '/admin/services/__ID__/faqs/__FAQ_ID__/edit',
    'case-edit-url': '/admin/cases/__ID__/edit',
    'case-create-url': '/admin/cases/new',
    'page-seo-edit-url': '/admin/pages/__PAGE_KEY__/seo',
    'blog-edit-url': '/admin/blog/__ID__/edit',
};

const FORM_HTML = '<form id="cms-modal-active-form" action="/admin/page-blocks/hero/5"><input name="title" value="Hero"></form>';

let application: Application;
let fetchMock: ReturnType<typeof vi.fn>;

function response(html: string, ok = true, redirected = false): FakeResponse {
    return { ok, redirected, text: async () => html };
}

async function flush(): Promise<void> {
    for (let i = 0; i < 10; i += 1) {
        await Promise.resolve();
    }
    await new Promise<void>((resolve) => {
        window.setTimeout(resolve, 0);
    });
}

function fixture(options: { submit?: boolean; title?: boolean } = {}): string {
    const values = Object.entries(URL_VALUES)
        .map(([name, value]) => `data-cms-modal-${name}-value="${value}"`)
        .join(' ');

    return `
        <div id="root" data-controller="cms-modal" ${values}>
            <div class="cms-modal" data-cms-modal-target="modal" hidden>
                ${options.title === false ? '' : '<h2 data-cms-modal-target="title">Edit block</h2>'}
                <div data-cms-modal-target="body"></div>
                ${options.submit === false ? '' : '<button type="submit" data-cms-modal-target="submit" disabled>Save</button>'}
            </div>
        </div>`;
}

function addButton(params: Record<string, string>): HTMLButtonElement {
    const button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('data-action', 'cms-modal#open');
    for (const [name, value] of Object.entries(params)) {
        button.setAttribute(`data-cms-modal-${name}-param`, value);
    }
    document.getElementById('root')?.appendChild(button);

    return button;
}

async function mount(html = fixture()): Promise<ControllerModule['default']> {
    // Fresh modules reset the controller's static CKEditor state; Stimulus must come from the same module graph.
    vi.resetModules();
    const stimulus = await import('@hotwired/stimulus');
    const module: ControllerModule = await import('./cms_modal_controller');
    document.body.innerHTML = html;
    application = new stimulus.Application();
    await application.start();
    application.register('cms-modal', module.default);
    await flush();

    return module.default;
}

function modal(): HTMLElement {
    return document.querySelector<HTMLElement>('[data-cms-modal-target="modal"]') as HTMLElement;
}

function body(): HTMLElement {
    return document.querySelector<HTMLElement>('[data-cms-modal-target="body"]') as HTMLElement;
}

function submitButton(): HTMLButtonElement {
    return document.querySelector<HTMLButtonElement>('[data-cms-modal-target="submit"]') as HTMLButtonElement;
}

async function openWith(params: Record<string, string>): Promise<HTMLButtonElement> {
    const button = addButton(params);
    await flush(); // Stimulus binds data-action through a MutationObserver.
    button.click();
    await flush();

    return button;
}

/**
 * Installs a fake `window.NowoCkeditor5Editor` and returns the spy behind `initCkeditor5Root` (the controller
 * replaces the API method with its guarded wrapper, so tests assert on this original spy).
 */
function ckeditorApi(withRunInit = true): ReturnType<typeof vi.fn> {
    const init = vi.fn(async (root: HTMLElement) => {
        root.dataset.ckeditor5Initialized = '1';
    });
    const api: { initCkeditor5Root: typeof init; runInit?: () => Promise<void> } = { initCkeditor5Root: init };
    if (withRunInit) {
        api.runInit = async () => {};
    }
    window.NowoCkeditor5Editor = api as unknown as NonNullable<typeof window.NowoCkeditor5Editor>;

    return init;
}

beforeEach(() => {
    fetchMock = vi.fn(async () => response(FORM_HTML));
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    application?.stop();
    document.body.innerHTML = '';
    document.body.className = '';
    delete window.NowoCkeditor5Editor;
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    vi.useRealTimers();
});

describe('cms_modal_controller: URL resolution', () => {
    it.each([
        [{ resource: 'page-block', 'block-type': 'hero', id: '5' }, '/admin/page-blocks/hero/5/edit-modal'],
        [{ resource: 'service' }, '/admin/services/new'],
        [{ resource: 'service', id: '3' }, '/admin/services/3/edit'],
        [{ resource: 'service', mode: 'edit' }, '/admin/services/0/edit'],
        [{ resource: 'service-faq', id: '4', mode: 'create' }, '/admin/services/4/faqs/new'],
        [{ resource: 'service-faq', id: '4', 'faq-id': '8' }, '/admin/services/4/faqs/8/edit'],
        [{ resource: 'service-faq', id: '4', mode: 'edit' }, '/admin/services/4/faqs/0/edit'],
        [{ resource: 'case' }, '/admin/cases/new'],
        [{ resource: 'case', id: '6' }, '/admin/cases/6/edit'],
        [{ resource: 'case', mode: 'edit' }, '/admin/cases/0/edit'],
        [{ resource: 'page-seo', 'page-key': 'home' }, '/admin/pages/home/seo'],
        // Interpolated values are encoded path segments: they cannot climb the path or inject a query / fragment.
        [{ resource: 'page-block', 'block-type': '../../users', id: '5?x=1#y' }, '/admin/page-blocks/..%2F..%2Fusers/5%3Fx%3D1%23y/edit-modal'],
        [{ resource: 'service-faq', id: '4/../9', 'faq-id': '8&a=b' }, '/admin/services/4%2F..%2F9/faqs/8%26a%3Db/edit'],
        [{ resource: 'page-seo', 'page-key': 'home/../../x' }, '/admin/pages/home%2F..%2F..%2Fx/seo'],
        [{ resource: 'blog', id: '2' }, '/admin/blog/2/edit'],
        [{ resource: 'blog' }, '/admin/blog/0/edit'],
    ])('loads %o from %s', async (params, expectedUrl) => {
        ckeditorApi();
        await mount();
        await openWith(params);

        expect(fetchMock).toHaveBeenCalledWith(expectedUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        expect(modal().hidden).toBe(false);
        expect(document.body.classList.contains('cms-modal-open')).toBe(true);
    });

    it.each([
        [{ resource: 'page-block', 'block-type': 'hero' }],
        [{ resource: 'page-block', id: '5' }],
        [{ resource: 'service-faq' }],
        [{ resource: 'page-seo' }],
        [{ resource: 'unknown' }],
        [{}],
    ])('does nothing when %o cannot be resolved', async (params) => {
        await mount();
        await openWith(params);

        expect(fetchMock).not.toHaveBeenCalled();
        expect(modal().hidden).toBe(true);
    });
});

describe('cms_modal_controller: modal lifecycle', () => {
    it('sets the title, injects the form and enables submit', async () => {
        ckeditorApi();
        await mount();
        await openWith({ resource: 'page-block', 'block-type': 'hero', id: '5', title: 'Edit hero' });

        expect(document.querySelector('[data-cms-modal-target="title"]')?.textContent).toBe('Edit hero');
        expect(body().querySelector('#cms-modal-active-form')).not.toBeNull();
        expect(submitButton().disabled).toBe(false);
    });

    it('keeps the default title when no title param is given and disables submit without the active form', async () => {
        ckeditorApi();
        fetchMock.mockResolvedValueOnce(response('<p>No form here</p>'));
        await mount();
        await openWith({ resource: 'blog', id: '1' });

        expect(document.querySelector('[data-cms-modal-target="title"]')?.textContent).toBe('Edit block');
        expect(submitButton().disabled).toBe(true);
    });

    it('works without title and submit targets', async () => {
        ckeditorApi();
        await mount(fixture({ submit: false, title: false }));
        await openWith({ resource: 'blog', id: '1', title: 'Ignored' });

        expect(body().querySelector('#cms-modal-active-form')).not.toBeNull();

        const controller = application.getControllerForElementAndIdentifier(
            document.getElementById('root') as HTMLElement,
            'cms-modal',
        ) as unknown as { close: () => void };
        controller.close();
        expect(modal().hidden).toBe(true);
    });

    it('shows an error message when the form cannot be loaded', async () => {
        ckeditorApi();
        fetchMock.mockRejectedValueOnce(new Error('offline'));
        await mount();
        await openWith({ resource: 'blog', id: '1' });

        expect(body().textContent).toBe('Could not load the form.');
        expect(body().querySelector('.cms-modal__loading--error')).not.toBeNull();
    });

    it('closes on Escape, restores focus and clears the body', async () => {
        ckeditorApi();
        await mount();
        const button = await openWith({ resource: 'blog', id: '1' });
        const focus = vi.spyOn(button, 'focus');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
        expect(modal().hidden).toBe(false);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(modal().hidden).toBe(true);
        expect(body().innerHTML).toBe('');
        expect(submitButton().disabled).toBe(true);
        expect(document.body.classList.contains('cms-modal-open')).toBe(false);
        expect(focus).toHaveBeenCalled();
    });

    it('ignores Escape while the modal is hidden and closes without a previous element', async () => {
        await mount();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(modal().hidden).toBe(true);
    });

    it('stops listening to keydown after disconnect', async () => {
        ckeditorApi();
        await mount();
        await openWith({ resource: 'blog', id: '1' });

        document.getElementById('root')?.removeAttribute('data-controller');
        await flush();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(modal().hidden).toBe(false);
    });
});

describe('cms_modal_controller: form submit', () => {
    it.each([
        ['ok', response('', true, false)],
        ['redirected', response('', false, true)],
    ])('reloads the page when the save response is %s', async (_label, saveResponse) => {
        ckeditorApi();
        const Controller = await mount();
        const reload = vi.spyOn(Controller.prototype as unknown as { reloadPage: () => void }, 'reloadPage')
            .mockImplementation(() => {});
        await openWith({ resource: 'page-block', 'block-type': 'hero', id: '5' });

        fetchMock.mockResolvedValueOnce(saveResponse);
        body().querySelector('form')?.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();

        expect(fetchMock).toHaveBeenLastCalledWith(
            expect.stringContaining('/admin/page-blocks/hero/5'),
            expect.objectContaining({ method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
        );
        expect(reload).toHaveBeenCalledOnce();
    });

    it('re-renders the returned form when validation fails', async () => {
        ckeditorApi();
        await mount();
        await openWith({ resource: 'page-block', 'block-type': 'hero', id: '5' });

        fetchMock.mockResolvedValueOnce(response('<form id="cms-modal-active-form"><p class="error">Required</p></form>', false));
        body().querySelector('form')?.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();

        expect(body().querySelector('.error')?.textContent).toBe('Required');
        expect(submitButton().disabled).toBe(false);
    });

    it('reloadPage delegates to window.location.reload', async () => {
        const Controller = await mount();
        const errors = vi.spyOn(console, 'error').mockImplementation(() => {});
        const controller = application.getControllerForElementAndIdentifier(
            document.getElementById('root') as HTMLElement,
            'cms-modal',
        ) as unknown as InstanceType<typeof Controller> & { reloadPage: () => void };

        expect(() => controller.reloadPage()).not.toThrow();
        errors.mockRestore();
    });
});

describe('cms_modal_controller: CKEditor integration', () => {
    it('waits for the CKEditor API and gives up after 40 attempts', async () => {
        await mount();
        const button = addButton({ resource: 'blog', id: '1' });
        await flush();
        vi.useFakeTimers();
        button.click();
        await vi.advanceTimersByTimeAsync(40 * 50 + 10);
        vi.useRealTimers();
        await flush();

        expect(body().querySelector('#cms-modal-active-form')).not.toBeNull();
    });

    it('patches the API once and mounts editors in the open panel only', async () => {
        const original = ckeditorApi();
        fetchMock.mockResolvedValueOnce(response(`
            <form id="cms-modal-active-form">
                <div data-nowo-ui-tabs-panel data-active><div class="ckeditor5-editor-widget" id="active" data-ckeditor5-root="1"></div></div>
                <div data-nowo-ui-tabs-panel><div class="ckeditor5-editor-widget" id="inactive"></div></div>
                <div data-nowo-ui-tabs-panel data-active hidden><div class="ckeditor5-editor-widget" id="hidden"></div></div>
                <div class="ckeditor5-editor-widget" id="loose"></div>
            </form>`));
        await mount();

        expect(window.NowoCkeditor5Editor?.initCkeditor5Root).not.toBe(original);

        await openWith({ resource: 'blog', id: '1' });

        const mounted = original.mock.calls.map(([root]) => (root as HTMLElement).id);
        expect(mounted).toEqual(['active', 'loose']);
        expect(body().querySelector('[data-ckeditor5-root]')).toBeNull();
    });

    it('mounts the editor of the panel a locale tab opens', async () => {
        const init = ckeditorApi();
        fetchMock.mockResolvedValueOnce(response(`
            <form id="cms-modal-active-form">
                <div data-nowo-ui-tabs-panel id="panel-en" hidden><div class="ckeditor5-editor-widget" id="en"></div></div>
            </form>`));
        await mount();
        await openWith({ resource: 'blog', id: '1' });
        expect(init).not.toHaveBeenCalled();

        const panel = document.getElementById('panel-en') as HTMLElement;
        panel.dispatchEvent(new CustomEvent('nowo-ui:tabs:change', { bubbles: true, detail: { panel } }));
        await flush();
        expect(init).toHaveBeenCalledTimes(1);

        body().dispatchEvent(new CustomEvent('nowo-ui:tabs:change', { detail: {} }));
        body().dispatchEvent(new Event('nowo-ui:tabs:change'));
        await flush();
        expect(init).toHaveBeenCalledTimes(1);
    });

    it('skips initialised editors, remounts duplicated ones and ignores concurrent mounts', async () => {
        let release: () => void = () => {};
        const init = ckeditorApi();
        init.mockImplementation(async (root: HTMLElement) => {
            if (root.id === 'slow') {
                await new Promise<void>((resolve) => {
                    release = resolve;
                });
            }
            root.dataset.ckeditor5Initialized = '1';
        });
        fetchMock.mockResolvedValueOnce(response(`
            <form id="cms-modal-active-form">
                <div class="ckeditor5-editor-widget" id="single" data-ckeditor5-initialized="1">
                    <div class="ckeditor5-editor-chrome"><div class="ck-editor"></div></div>
                </div>
                <div class="ckeditor5-editor-widget" id="double" data-ckeditor5-initialized="1">
                    <div class="ckeditor5-editor-chrome"><div class="ck-editor"></div><div class="ck-editor"></div></div>
                    <div data-ckeditor5-mount style="display: none">stale</div>
                </div>
                <div class="ckeditor5-editor-widget" id="bare"></div>
            </form>`));
        await mount();
        await openWith({ resource: 'blog', id: '1' });

        const mounted = init.mock.calls.map(([root]) => (root as HTMLElement).id);
        expect(mounted).toEqual(['double', 'bare']);
        const double = document.getElementById('double') as HTMLElement;
        expect(double.querySelectorAll('.ck-editor')).toHaveLength(0);
        const mountPoint = double.querySelector<HTMLElement>('[data-ckeditor5-mount]') as HTMLElement;
        expect(mountPoint.innerHTML).toBe('');
        expect(mountPoint.style.display).toBe('');

        const slow = document.createElement('div');
        slow.className = 'ckeditor5-editor-widget';
        slow.id = 'slow';
        body().appendChild(slow);
        const event = (): CustomEvent => new CustomEvent('nowo-ui:tabs:change', { detail: { panel: body() } });
        body().dispatchEvent(event());
        await flush();
        body().dispatchEvent(event());
        await flush();
        release();
        await flush();

        expect(init.mock.calls.filter(([root]) => (root as HTMLElement).id === 'slow')).toHaveLength(1);
    });

    it('guards the global auto-init against modal, initialised and locked roots', async () => {
        let release: () => void = () => {};
        const original = ckeditorApi();
        original.mockImplementation(
            () =>
                new Promise<void>((resolve) => {
                    release = resolve;
                }),
        );
        await mount();
        const guarded = window.NowoCkeditor5Editor?.initCkeditor5Root as (root: HTMLElement) => Promise<void>;

        const inModal = document.createElement('div');
        body().appendChild(inModal);
        await guarded(inModal);

        const initialised = document.createElement('div');
        initialised.dataset.ckeditor5Initialized = '1';
        document.body.appendChild(initialised);
        await guarded(initialised);

        expect(original).not.toHaveBeenCalled();

        const page = document.createElement('div');
        page.setAttribute('data-ckeditor5-root', '1');
        document.body.appendChild(page);
        const first = guarded(page);
        await guarded(page);
        release();
        await first;
        expect(original).toHaveBeenCalledTimes(1);

        original.mockImplementation(async () => {});
        await window.NowoCkeditor5Editor?.runInit?.();
        expect(original).toHaveBeenCalledTimes(2);
        expect(original).toHaveBeenLastCalledWith(page);
    });

    it('patches an API without runInit and only once per page', async () => {
        const original = ckeditorApi(false);
        await mount(fixture() + fixture());

        expect(window.NowoCkeditor5Editor?.runInit).toBeUndefined();
        expect(window.NowoCkeditor5Editor?.initCkeditor5Root).not.toBe(original);
        const patched = window.NowoCkeditor5Editor?.initCkeditor5Root;

        await openWith({ resource: 'blog', id: '1' });
        expect(window.NowoCkeditor5Editor?.initCkeditor5Root).toBe(patched);
    });
});
