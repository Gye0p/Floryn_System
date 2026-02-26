import { Controller } from '@hotwired/stimulus';

/**
 * Confirm Controller
 *
 * Shows a native confirm() dialog before allowing a form to submit.
 * This replaces inline `onsubmit="return confirm('...')"` handlers
 * with a declarative Stimulus approach.
 *
 * Usage:
 *   <form data-controller="confirm"
 *         data-confirm-message-value="Are you sure?"
 *         data-action="submit->confirm#ask">
 *     <button type="submit">Delete</button>
 *   </form>
 */
export default class extends Controller {
  static values = {
    message: { type: String, default: 'Are you sure? This action cannot be undone.' },
  };

  ask(event) {
    if (!confirm(this.messageValue)) {
      event.preventDefault();
    }
  }
}
