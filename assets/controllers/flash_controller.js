import { Controller } from '@hotwired/stimulus';

/**
 * Auto-dismiss flash messages after a configurable delay.
 * Usage: <div data-controller="flash" data-flash-delay-value="5000"> ... </div>
 */
export default class extends Controller {
  static values = {
    delay: { type: Number, default: 5000 },
  };

  connect() {
    this._timeout = setTimeout(() => this.dismiss(), this.delayValue);
  }

  disconnect() {
    clearTimeout(this._timeout);
  }

  dismiss() {
    this.element.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    this.element.style.opacity = '0';
    this.element.style.transform = 'translateY(-8px)';
    setTimeout(() => this.element.remove(), 400);
  }
}
