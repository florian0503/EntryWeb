import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['browser', 'iframe', 'blocked', 'deviceBtn'];

    switchDevice(event) {
        const device = event.currentTarget.dataset.device;

        this.deviceBtnTargets.forEach(btn => btn.classList.remove('is-active'));
        event.currentTarget.classList.add('is-active');

        this.browserTarget.className = 'preview-browser device-' + device;
    }

    iframeError() {
        this.iframeTarget.style.display = 'none';
        if (this.hasBlockedTarget) this.blockedTarget.style.display = 'flex';
    }
}
