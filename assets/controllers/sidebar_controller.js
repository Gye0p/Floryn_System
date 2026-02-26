import { Controller } from '@hotwired/stimulus';

/**
 * Mobile sidebar drawer toggle.
 * Placed on the <body> element to coordinate sidebar + overlay.
 *
 * Usage:
 *   <body data-controller="sidebar">
 *     <aside data-sidebar-target="drawer" class="...">
 *     <div data-sidebar-target="overlay" class="...">
 *     <button data-action="sidebar#toggle">
 */
export default class extends Controller {
  static targets = ['drawer', 'overlay'];

  toggle() {
    const isOpen = this.drawerTarget.classList.contains('translate-x-0');
    if (isOpen) {
      this.close();
    } else {
      this.open();
    }
  }

  open() {
    this.drawerTarget.classList.remove('-translate-x-full');
    this.drawerTarget.classList.add('translate-x-0');
    this.overlayTarget.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  close() {
    this.drawerTarget.classList.remove('translate-x-0');
    this.drawerTarget.classList.add('-translate-x-full');
    this.overlayTarget.classList.add('hidden');
    document.body.style.overflow = '';
  }
}
