import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './admin.css';

async function mountAdmin() {
  const host = document.getElementById('simple-lms-admin-root');
  if (!host) return;

  const response = await fetch(window.simpleLMSAdmin.cssUrl, { credentials: 'same-origin' });
  if (!response.ok) throw new Error('SimpleLMS admin styles could not be loaded.');
  const adminCss = await response.text();
  const shadow = host.attachShadow({ mode: 'open' });
  const style = document.createElement('style');
  style.textContent = adminCss;
  const mount = document.createElement('div');
  mount.className = 'simple-lms-admin';
  shadow.append(style, mount);
  createRoot(mount).render(<App />);
}

mountAdmin().catch((error) => {
  console.error('[SimpleLMS]', error);
  const host = document.getElementById('simple-lms-admin-root');
  if (host) host.textContent = error.message;
});
