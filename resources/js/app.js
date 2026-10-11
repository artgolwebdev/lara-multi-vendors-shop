/**
 * First we will load all of this project's JavaScript dependencies which
 * includes React and other helpers. It's a great starting point while
 * building robust, powerful web applications using React + Laravel.
 */

import './bootstrap';

/**
 * Next, we will create a fresh React component instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

import '../css/tokens.css';
import '../css/ui.css';
import '../css/auth.css';
import '../css/admin.css';
import '../css/store.css';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import AuthApp from './components/AuthApp';
import AdminApp from './components/AdminApp';
import StoreApp from './components/store/StoreApp';

const storeRootElement = document.getElementById('store-app');

if (storeRootElement) {
	createRoot(storeRootElement).render(createElement(StoreApp));
}

const rootElement = document.getElementById('auth-app');

if (rootElement) {
	createRoot(rootElement).render(createElement(AuthApp));
}

const adminRootElement = document.getElementById('admin-app');

if (adminRootElement) {
	createRoot(adminRootElement).render(createElement(AdminApp));
}

