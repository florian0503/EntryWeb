import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track'];

    connect() {
        this.isHovered = false;
        this.onWheel = this.onWheel.bind(this);
        this.onEnter = () => { this.isHovered = true; };
        this.onLeave = () => { this.isHovered = false; };

        this.element.addEventListener('wheel', this.onWheel, { passive: false });
        this.element.addEventListener('mouseenter', this.onEnter);
        this.element.addEventListener('mouseleave', this.onLeave);
    }

    disconnect() {
        this.element.removeEventListener('wheel', this.onWheel);
        this.element.removeEventListener('mouseenter', this.onEnter);
        this.element.removeEventListener('mouseleave', this.onLeave);
    }

    onWheel(e) {
        if (!this.isHovered) return;

        const track = this.trackTarget;
        const maxScroll = track.scrollWidth - track.clientWidth;
        const atStart = track.scrollLeft <= 0;
        const atEnd = track.scrollLeft >= maxScroll - 1;

        if (e.deltaY > 0 && !atEnd) {
            e.preventDefault();
            track.scrollLeft += Math.abs(e.deltaY);
        } else if (e.deltaY < 0 && !atStart) {
            e.preventDefault();
            track.scrollLeft -= Math.abs(e.deltaY);
        }
    }
}
