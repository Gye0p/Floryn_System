import { Controller } from '@hotwired/stimulus';

/**
 * Clock Controller
 *
 * Displays a live-updating clock inside the connected element.
 * Replaces the inline <script> that was in the POS page.
 *
 * Usage:
 *   <span data-controller="clock">--</span>
 *
 * The clock updates every second showing the current date/time
 * formatted as "Jan 1, 2025, 02:30:15 PM".
 */
export default class extends Controller {
  connect() {
    this._tick();
    this._interval = setInterval(() => this._tick(), 1000);
  }

  disconnect() {
    clearInterval(this._interval);
  }

  _tick() {
    const now = new Date();
    this.element.textContent = now.toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  }
}
