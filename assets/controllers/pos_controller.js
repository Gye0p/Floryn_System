import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = [
    'flowerSearch', 'categoryFilter', 'flowerGrid', 'emptyFlowers',
    'customerSearch', 'customerResults', 'selectedCustomer', 'selectedCustomerName', 'selectedCustomerInfo',
    'existingCustomerSection', 'walkinSection', 'customerToggle',
    'walkinName', 'walkinPhone', 'walkinEmail',
    'cartItems', 'emptyCart', 'cartTotals', 'cartCount',
    'subtotalDisplay', 'totalDisplay',
    'paymentBtn', 'checkoutBtn',
    'processingOverlay', 'successModal',
    'receiptRef', 'receiptCustomer', 'receiptTotal', 'receiptViewLink'
  ];

  static values = {
    flowersUrl: String,
    customersUrl: String,
    checkoutUrl: String,
    csrf: String,
    flowers: Array,
    customers: Array
  };

  connect() {
    this.cart = []; // { flowerId, name, price, quantity, maxStock }
    this.selectedCustomerId = null;
    this.selectedPaymentMethod = null;
    this.isWalkin = true; // Default to walk-in mode
    this._allFlowerCards = Array.from(this.flowerGridTarget.querySelectorAll('.pos-flower-card'));
    this._searchTimeout = null;
  }

  // ── Flower Filtering ──

  filterFlowers() {
    const query = this.flowerSearchTarget.value.toLowerCase().trim();
    const category = this.categoryFilterTarget.value;
    let visibleCount = 0;

    this._allFlowerCards.forEach(card => {
      const name = (card.dataset.flowerName || '').toLowerCase();
      const cat = card.dataset.flowerCategory || '';
      const matchesQuery = !query || name.includes(query) || cat.toLowerCase().includes(query);
      const matchesCategory = !category || cat === category;
      const show = matchesQuery && matchesCategory;
      card.style.display = show ? '' : 'none';
      if (show) visibleCount++;
    });

    this.emptyFlowersTarget.classList.toggle('hidden', visibleCount > 0);
  }

  // ── Customer Search ──

  toggleCustomerMode() {
    this.isWalkin = !this.isWalkin;
    if (this.isWalkin) {
      this.customerToggleTarget.textContent = 'Walk-in';
      this.customerToggleTarget.style.background = '#dbeafe';
      this.customerToggleTarget.style.color = '#1e40af';
      this.existingCustomerSectionTarget.classList.add('hidden');
      this.walkinSectionTarget.classList.remove('hidden');
      this.clearCustomer();
    } else {
      this.customerToggleTarget.textContent = 'Returning';
      this.customerToggleTarget.style.background = '#dcfce7';
      this.customerToggleTarget.style.color = '#166534';
      this.existingCustomerSectionTarget.classList.remove('hidden');
      this.walkinSectionTarget.classList.add('hidden');
    }
  }

  searchCustomers() {
    const q = this.customerSearchTarget.value.trim();
    if (q.length < 2) {
      this.customerResultsTarget.classList.add('hidden');
      return;
    }

    // Filter from preloaded data
    const matches = this.customersValue.filter(c =>
      c.fullName.toLowerCase().includes(q.toLowerCase()) ||
      (c.phone || '').includes(q) ||
      (c.email || '').toLowerCase().includes(q.toLowerCase())
    ).slice(0, 8);

    this._renderCustomerResults(matches);
  }

  _renderCustomerResults(customers) {
    const container = this.customerResultsTarget;
    if (customers.length === 0) {
      container.classList.add('hidden');
      return;
    }

    container.innerHTML = customers.map(c => `
      <button type="button"
              class="w-full text-left p-2.5 rounded-lg hover:bg-blue-50 transition-colors flex items-center gap-3"
              data-action="click->pos#pickCustomer"
              data-customer-id="${c.id}"
              data-customer-name="${this._escHtml(c.fullName)}"
              data-customer-phone="${this._escHtml(c.phone || '')}"
              data-customer-email="${this._escHtml(c.email || '')}">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:#4265d6;">
          ${c.fullName.charAt(0).toUpperCase()}
        </div>
        <div>
          <div class="text-sm font-medium text-gray-900">${this._escHtml(c.fullName)}</div>
          <div class="text-[11px] text-gray-500">${this._escHtml(c.phone || '')} · ${this._escHtml(c.email || '')}</div>
        </div>
      </button>
    `).join('');
    container.classList.remove('hidden');
  }

  pickCustomer(event) {
    const btn = event.currentTarget;
    this.selectedCustomerId = parseInt(btn.dataset.customerId);
    this.selectedCustomerNameTarget.textContent = btn.dataset.customerName;
    this.selectedCustomerInfoTarget.textContent = `${btn.dataset.customerPhone} · ${btn.dataset.customerEmail}`;
    this.selectedCustomerTarget.classList.remove('hidden');
    this.customerResultsTarget.classList.add('hidden');
    this.customerSearchTarget.value = '';
    this._updateCheckoutState();
  }

  clearCustomer() {
    this.selectedCustomerId = null;
    this.selectedCustomerTarget.classList.add('hidden');
    this.customerSearchTarget.value = '';
    this.customerResultsTarget.classList.add('hidden');
    this._updateCheckoutState();
  }

  // ── Cart Management ──

  addToCart(event) {
    const card = event.currentTarget;
    const flowerId = parseInt(card.dataset.flowerId);
    const name = card.dataset.flowerName;
    const price = parseFloat(card.dataset.flowerEffective);
    const maxStock = parseInt(card.dataset.flowerStock);

    const existing = this.cart.find(i => i.flowerId === flowerId);
    if (existing) {
      if (existing.quantity >= maxStock) {
        this._showToast(`Maximum stock reached for "${name}" (${maxStock} available)`, 'warning');
        return;
      }
      existing.quantity++;
    } else {
      this.cart.push({ flowerId, name, price, quantity: 1, maxStock });
    }

    this._renderCart();
    this._pulseCard(card);
  }

  changeQty(event) {
    const btn = event.currentTarget;
    const flowerId = parseInt(btn.dataset.flowerId);
    const delta = parseInt(btn.dataset.delta);
    const item = this.cart.find(i => i.flowerId === flowerId);

    if (!item) return;

    item.quantity += delta;
    if (item.quantity <= 0) {
      this.cart = this.cart.filter(i => i.flowerId !== flowerId);
    } else if (item.quantity > item.maxStock) {
      item.quantity = item.maxStock;
      this._showToast(`Max ${item.maxStock} available`, 'warning');
    }

    this._renderCart();
  }

  removeItem(event) {
    const flowerId = parseInt(event.currentTarget.dataset.flowerId);
    this.cart = this.cart.filter(i => i.flowerId !== flowerId);
    this._renderCart();
  }

  _renderCart() {
    const container = this.cartItemsTarget;

    if (this.cart.length === 0) {
      container.innerHTML = '';
      this.emptyCartTarget.classList.remove('hidden');
      this.cartTotalsTarget.classList.add('hidden');
      this.cartCountTarget.textContent = '0 items';
      this._updateCheckoutState();
      return;
    }

    this.emptyCartTarget.classList.add('hidden');
    this.cartTotalsTarget.classList.remove('hidden');

    const totalItems = this.cart.reduce((sum, i) => sum + i.quantity, 0);
    this.cartCountTarget.textContent = `${totalItems} item${totalItems > 1 ? 's' : ''}`;

    container.innerHTML = this.cart.map(item => `
      <div class="flex items-center gap-3 px-4 py-3">
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium text-gray-900 truncate">${this._escHtml(item.name)}</div>
          <div class="text-[11px] text-gray-500">₱${item.price.toFixed(2)} each</div>
        </div>
        <div class="flex items-center gap-1.5">
          <button type="button" data-action="click->pos#changeQty" data-flower-id="${item.flowerId}" data-delta="-1"
                  class="w-7 h-7 rounded-md flex items-center justify-center text-xs transition-colors"
                  style="border:1px solid #e8edf2;color:#6b7a8d;">
            <i class="fas fa-minus" style="font-size:9px;"></i>
          </button>
          <span class="w-8 text-center text-sm font-semibold text-gray-900">${item.quantity}</span>
          <button type="button" data-action="click->pos#changeQty" data-flower-id="${item.flowerId}" data-delta="1"
                  class="w-7 h-7 rounded-md flex items-center justify-center text-xs transition-colors"
                  style="border:1px solid #e8edf2;color:#6b7a8d;">
            <i class="fas fa-plus" style="font-size:9px;"></i>
          </button>
        </div>
        <div class="text-sm font-bold text-gray-900 w-20 text-right">₱${(item.price * item.quantity).toFixed(2)}</div>
        <button type="button" data-action="click->pos#removeItem" data-flower-id="${item.flowerId}"
                class="text-red-400 hover:text-red-600 ml-1">
          <i class="fas fa-trash-alt text-xs"></i>
        </button>
      </div>
    `).join('');

    const total = this.cart.reduce((sum, i) => sum + i.price * i.quantity, 0);
    this.subtotalDisplayTarget.textContent = `₱${total.toFixed(2)}`;
    this.totalDisplayTarget.textContent = `₱${total.toFixed(2)}`;

    this._updateCheckoutState();
  }

  // ── Payment ──

  selectPayment(event) {
    const btn = event.currentTarget;
    this.selectedPaymentMethod = btn.dataset.paymentMethod;

    // Toggle active state on all payment buttons
    this.paymentBtnTargets.forEach(b => {
      b.style.background = 'white';
      b.style.borderColor = '#e8edf2';
      b.style.color = '#6b7a8d';
    });
    btn.style.background = '#4265d6';
    btn.style.borderColor = '#4265d6';
    btn.style.color = '#ffffff';

    this._updateCheckoutState();
  }

  _updateCheckoutState() {
    const hasItems = this.cart.length > 0;
    const hasPayment = !!this.selectedPaymentMethod;
    this.checkoutBtnTarget.disabled = !(hasItems && hasPayment);
  }

  // ── Checkout ──

  async checkout() {
    if (this.cart.length === 0 || !this.selectedPaymentMethod) return;

    this.checkoutBtnTarget.disabled = true;
    this.processingOverlayTarget.classList.remove('hidden');

    const payload = {
      _token: this.csrfValue,
      items: this.cart.map(i => ({ flowerId: i.flowerId, quantity: i.quantity })),
      paymentMethod: this.selectedPaymentMethod,
      customerId: this.selectedCustomerId,
      customerName: this.isWalkin ? (this.walkinNameTarget.value || 'Walk-in Customer') : null,
      customerPhone: this.isWalkin ? this.walkinPhoneTarget.value : null,
      customerEmail: this.isWalkin ? this.walkinEmailTarget.value : null,
    };

    try {
      const response = await fetch(this.checkoutUrlValue, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload),
      });

      const result = await response.json();
      this.processingOverlayTarget.classList.add('hidden');

      if (result.success) {
        this.receiptRefTarget.textContent = result.referenceNo;
        this.receiptCustomerTarget.textContent = result.customerName;
        this.receiptTotalTarget.textContent = `₱${parseFloat(result.totalAmount).toFixed(2)}`;
        this.receiptViewLinkTarget.href = `/reservation/${result.reservationId}`;
        this.successModalTarget.classList.remove('hidden');
      } else {
        this._showToast(result.error || 'Checkout failed. Please try again.', 'error');
        this.checkoutBtnTarget.disabled = false;
      }
    } catch (error) {
      this.processingOverlayTarget.classList.add('hidden');
      this._showToast('Network error. Please check your connection and try again.', 'error');
      this.checkoutBtnTarget.disabled = false;
    }
  }

  // ── Reset ──

  resetAll() {
    this.cart = [];
    this.selectedCustomerId = null;
    this.selectedPaymentMethod = null;

    // Reset UI
    this._renderCart();
    this.flowerSearchTarget.value = '';
    this.categoryFilterTarget.value = '';
    this.filterFlowers();

    // Reset customer
    this.clearCustomer();
    if (this.hasWalkinNameTarget) this.walkinNameTarget.value = '';
    if (this.hasWalkinPhoneTarget) this.walkinPhoneTarget.value = '';
    if (this.hasWalkinEmailTarget) this.walkinEmailTarget.value = '';

    // Reset to walk-in mode
    if (!this.isWalkin) this.toggleCustomerMode();

    // Reset payment buttons
    this.paymentBtnTargets.forEach(b => {
      b.style.background = 'white';
      b.style.borderColor = '#e8edf2';
      b.style.color = '#6b7a8d';
    });

    // Hide modals
    this.successModalTarget.classList.add('hidden');
    this.processingOverlayTarget.classList.add('hidden');

    // Reload page to refresh stock counts
    window.location.reload();
  }

  // ── Helpers ──

  _pulseCard(card) {
    card.style.transform = 'scale(0.95)';
    card.style.boxShadow = '0 0 0 2px #4265d6';
    setTimeout(() => {
      card.style.transform = '';
      card.style.boxShadow = '';
    }, 200);
  }

  _showToast(message, type = 'info') {
    const colors = {
      success: 'background:#dcfce7;color:#166534;border-color:#bbf7d0',
      error: 'background:#fee2e2;color:#991b1b;border-color:#fecaca',
      warning: 'background:#fef9c3;color:#854d0e;border-color:#fde68a',
      info: 'background:#dbeafe;color:#1e40af;border-color:#bfdbfe',
    };
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };

    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-[9999] flex items-center gap-3 px-5 py-3 rounded-xl shadow-lg border animate-fade-in';
    toast.style.cssText = colors[type] || colors.info;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i><span class="text-sm font-medium">${this._escHtml(message)}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }

  _escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
}
