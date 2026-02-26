import { Controller } from '@hotwired/stimulus';

/**
 * Reservation Form Controller
 *
 * Manages the dynamic "Selected Flowers" collection in the reservation form.
 * Handles adding/removing flower line items, computing per-row subtotals,
 * and keeping a running total.  Replaces the 150-line inline <script> that
 * was previously embedded in reservation/_form.html.twig.
 *
 * Targets:
 *   container   – the element holding all flower-item rows (has data-prototype)
 *   emptyState  – the "no flowers" placeholder
 *   total       – the total-amount display span
 *   addButton   – the "Add Flower" button
 *
 * How it works:
 *   1. On connect(), caches flower prices from <option> labels (₱123.45).
 *   2. Attaches change/input listeners to every existing row for subtotals.
 *   3. When "Add Flower" is clicked, stamps a new row from the Symfony
 *      form prototype, wires up events, and appends it.
 *   4. When a trash button is clicked, removes that row and recalculates.
 */
export default class extends Controller {
  static targets = ['container', 'emptyState', 'total', 'addButton'];

  /* ────── lifecycle ────── */

  connect() {
    this._index = parseInt(this.containerTarget.dataset.index) || 0;
    this._prices = {};

    // Cache prices from every flower <select> already in the DOM
    this.containerTarget.querySelectorAll('.flower-select').forEach(s => this._cachePrices(s));

    // Wire existing rows
    this.containerTarget.querySelectorAll('.flower-item').forEach(item => {
      this._attachItemEvents(item);
      this._calculateSubtotal(item);
    });

    this._calculateTotal();
    this._updateEmptyState();
  }

  /* ────── actions (called from data-action attributes) ────── */

  add() {
    const prototype = this.containerTarget.dataset.prototype;
    const html = prototype.replace(/__name__/g, this._index);

    // Parse the prototype into a temp wrapper
    const temp = document.createElement('div');
    temp.innerHTML = html;

    const flowerWidget = temp.querySelector('[id*="_flower"]')?.parentElement?.innerHTML || '';
    const quantityWidget = temp.querySelector('[id*="_quantity"]')?.parentElement?.innerHTML || '';

    // Build the row markup
    const row = document.createElement('div');
    row.classList.add('flower-item');
    row.style.cssText = 'border:1px solid var(--border);border-radius:10px;padding:16px;background:var(--bg);';
    row.innerHTML = `
      <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <div class="md:col-span-6">
          <label class="form-label">Flower</label>
          ${flowerWidget}
        </div>
        <div class="md:col-span-3">
          <label class="form-label">Quantity</label>
          ${quantityWidget}
        </div>
        <div class="md:col-span-2">
          <div class="form-label">Subtotal</div>
          <div class="subtotal-display" style="font-size:18px;font-weight:700;color:var(--navy);">₱0.00</div>
        </div>
        <div class="md:col-span-1 flex justify-end">
          <button type="button" class="remove-flower-btn" data-action="reservation-form#remove"
                  style="color:#ef4444;cursor:pointer;background:none;border:none;padding:8px;">
            <i class="fas fa-trash" style="font-size:16px;"></i>
          </button>
        </div>
      </div>
    `;

    // Apply consistent classes
    const select = row.querySelector('select');
    const input = row.querySelector('input[type="number"]');
    if (select) {
      select.classList.add('flower-select', 'form-select');
      this._cachePrices(select);
    }
    if (input) {
      input.classList.add('quantity-input', 'form-control');
      input.setAttribute('min', '1');
      input.value = 1;
    }

    this.containerTarget.appendChild(row);
    this._attachItemEvents(row);
    this._updateEmptyState();
    this._index++;
  }

  remove(event) {
    const row = event.currentTarget.closest('.flower-item');
    if (row) {
      row.remove();
      this._calculateTotal();
      this._updateEmptyState();
    }
  }

  /* ────── internal helpers ────── */

  /**
   * Parse "₱123.45" from each <option>'s text and store in a lookup by value.
   */
  _cachePrices(select) {
    select.querySelectorAll('option').forEach(opt => {
      if (opt.value) {
        const match = opt.textContent.match(/\u20B1([\d.]+)/);
        if (match) this._prices[opt.value] = parseFloat(match[1]);
      }
    });
  }

  _calculateSubtotal(item) {
    const select = item.querySelector('.flower-select');
    const qty = item.querySelector('.quantity-input');
    const display = item.querySelector('.subtotal-display');
    if (!select || !qty || !display) return;

    const price = this._prices[select.value] || 0;
    const quantity = parseInt(qty.value) || 0;
    display.textContent = `\u20B1${(price * quantity).toFixed(2)}`;
  }

  _calculateTotal() {
    let total = 0;
    this.containerTarget.querySelectorAll('.flower-item').forEach(item => {
      const select = item.querySelector('.flower-select');
      const qty = item.querySelector('.quantity-input');
      if (select && qty) {
        total += (this._prices[select.value] || 0) * (parseInt(qty.value) || 0);
      }
    });
    this.totalTarget.textContent = `\u20B1${total.toFixed(2)}`;
  }

  _updateEmptyState() {
    const hasItems = this.containerTarget.querySelectorAll('.flower-item').length > 0;
    this.emptyStateTarget.classList.toggle('hidden', hasItems);
  }

  /**
   * Wire change/input listeners on a single flower row so that selecting
   * a different flower or changing the quantity recalculates on the fly.
   */
  _attachItemEvents(item) {
    const select = item.querySelector('.flower-select');
    const qty = item.querySelector('.quantity-input');

    if (select) {
      this._cachePrices(select);
      select.addEventListener('change', () => { this._calculateSubtotal(item); this._calculateTotal(); });
    }
    if (qty) {
      qty.addEventListener('input', () => { this._calculateSubtotal(item); this._calculateTotal(); });
    }
  }
}
