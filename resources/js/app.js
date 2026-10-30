
import { createApp } from 'vue';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap';
import '../css/app.css';

import App from './components/App.vue';
import LiveApp from './components/LiveApp.vue';

const root = document.getElementById('app');
createApp(root?.dataset.runtime === 'laravel' ? LiveApp : App).mount(root);
