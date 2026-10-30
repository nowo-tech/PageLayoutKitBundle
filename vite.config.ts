import { defineConfig } from 'vite';

/**
 * Builds the inline CMS modal Stimulus controller from `src/Resources/assets/src` into `src/Resources/public/js`.
 *
 * - `cms_modal_controller.js`: ES module (imports `@hotwired/stimulus`) for AssetMapper / importmap hosts.
 * - `cms_modal_controller.iife.js`: IIFE for hosts without a module loader; reads the Stimulus UMD global
 *   `window.Stimulus` and exposes the controller class as `window.NowoPageLayoutKitCmsModalController`.
 *
 * Stimulus stays external in both files so the controller extends the host's Stimulus copy. The output is
 * deterministic (no timestamps), so rebuilding unchanged sources leaves the committed files byte-identical.
 */
export default defineConfig({
  build: {
    outDir: 'src/Resources/public',
    emptyOutDir: false,
    minify: false,
    sourcemap: false,
    lib: {
      entry: 'src/Resources/assets/src/cms_modal_controller.ts',
      name: 'NowoPageLayoutKitCmsModalController',
      formats: ['es', 'iife'],
      fileName: (format) => (format === 'es' ? 'js/cms_modal_controller.js' : 'js/cms_modal_controller.iife.js'),
    },
    rollupOptions: {
      external: ['@hotwired/stimulus'],
      output: {
        globals: { '@hotwired/stimulus': 'Stimulus' },
      },
    },
  },
  resolve: {
    extensions: ['.ts'],
  },
});
