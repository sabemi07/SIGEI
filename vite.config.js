
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => ({
  plugins: [
    vue(),
    ...(mode === 'laravel'
      ? [laravel({
          input: ['resources/css/app.css', 'resources/js/app.js'],
          refresh: true,
        })]
      : []),
  ],
}));
