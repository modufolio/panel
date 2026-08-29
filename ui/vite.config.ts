import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import dts from 'vite-plugin-dts'
import { fileURLToPath } from 'url'

export default defineConfig({
  plugins: [
    vue(),
    // Bundles declarations into dist/ and rewrites the internal '@' alias so
    // consumers get resolvable types.
    dts({ tsconfigPath: './tsconfig.build.json', rollupTypes: false }),
  ],
  resolve: {
    alias: {
      // Internal alias: '@' is the package src root. Moved modules keep their
      // '@/Utils/...' / '@/Composables/...' import style unchanged.
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    lib: {
      entry: fileURLToPath(new URL('./src/index.ts', import.meta.url)),
      formats: ['es'],
      fileName: 'index',
    },
    rollupOptions: {
      // The field registry loads built-in field components through import(),
      // which is not about code splitting: it is what keeps RepeaterField and
      // fieldRegistry from importing each other statically, and it gives
      // registerFieldType() one loader shape for built-in and app-specific
      // types alike. Since index.ts also exports every field component, Rollup
      // cannot move them out of the entry chunk anyway — left to split, it
      // emitted thirteen ~130-byte chunks that only re-export from index.js.
      output: { inlineDynamicImports: true },
      // Everything a consumer must provide stays external — the library
      // bundles none of its peers.
      external: [
        'vue',
        '@inertiajs/vue3',
        '@vueuse/core',
        '@floating-ui/vue',
        '@heroicons/vue',
        /^@heroicons\/vue\//,
        'vuedraggable',
        /^lodash\//,
      ],
    },
    sourcemap: true,
    emptyOutDir: true,
  },
})
