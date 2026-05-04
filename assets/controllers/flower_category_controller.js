import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['name', 'category', 'categoryDisplay'];
  static values = {
    map: Object,
  };

  connect() {
    this._sync();
  }

  onNameChange() {
    this._sync();
  }

  _sync() {
    const name = this.hasNameTarget ? this.nameTarget.value : '';
    const map = this.hasMapValue ? this.mapValue : {};
    const category = (name && map && map[name]) ? map[name] : '';

    if (this.hasCategoryTarget) {
      this.categoryTarget.value = category;
    }

    if (this.hasCategoryDisplayTarget) {
      this.categoryDisplayTarget.value = category;
    }
  }
}
