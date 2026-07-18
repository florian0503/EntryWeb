import { Controller } from '@hotwired/stimulus';

const DUREE_CONVERGENCE = 1400;
const DEBUT_EXPLOSION = 2600;
const DEBUT_REVELATION = 2850;
const DUREE_TOTALE = 3600;
const COULEURS = ['#ffffff', '#ffffff', '#cfe1ff', '#9ec5ff', '#66a3ff', '#0066ff'];

export default class extends Controller {
    static targets = ['canvas'];

    connect() {
        document.cookie = 'intro_vue=1; path=/; SameSite=Lax';

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.element.remove();

            return;
        }

        document.body.classList.add('intro-active');
        this.demarrer();
        this.garde = setTimeout(() => this.terminer(), DUREE_TOTALE + 1500);
    }

    demarrer() {
        const canvas = this.canvasTarget;
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const largeur = window.innerWidth;
        const hauteur = window.innerHeight;
        canvas.width = largeur * dpr;
        canvas.height = hauteur * dpr;
        this.ctx = canvas.getContext('2d');
        this.ctx.scale(dpr, dpr);
        this.largeur = largeur;
        this.hauteur = hauteur;

        this.particules = this.creerParticules(this.echantillonnerTexte('ENTRYWEB'));
        this.revele = false;
        this.depart = null;
        this.rafId = requestAnimationFrame((t) => this.boucle(t));
    }

    echantillonnerTexte(texte) {
        const tampon = document.createElement('canvas');
        tampon.width = this.largeur;
        tampon.height = this.hauteur;
        const ctx = tampon.getContext('2d');
        const police = "800 100px -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif";
        ctx.font = police;
        const echelle = Math.min((this.largeur * 0.88) / ctx.measureText(texte).width, 1.8);
        const taille = Math.min(100 * echelle, 190);
        ctx.font = police.replace('100px', taille + 'px');
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(texte, this.largeur / 2, this.hauteur / 2);

        const donnees = ctx.getImageData(0, 0, this.largeur, this.hauteur).data;
        const pas = this.largeur < 700 ? 2 : 4;
        this.finesse = pas;
        const points = [];
        for (let y = 0; y < this.hauteur; y += pas) {
            for (let x = 0; x < this.largeur; x += pas) {
                if (donnees[(y * this.largeur + x) * 4 + 3] > 128) {
                    points.push({ x, y });
                }
            }
        }

        return points;
    }

    creerParticules(cibles) {
        const centreX = this.largeur / 2;
        const centreY = this.hauteur / 2;

        return cibles.map((cible) => {
            const angle = Math.random() * Math.PI * 2;
            const rayon = Math.max(this.largeur, this.hauteur) * (0.55 + Math.random() * 0.35);

            const departX = centreX + Math.cos(angle) * rayon;
            const departY = centreY + Math.sin(angle) * rayon;

            return {
                x: departX,
                y: departY,
                departX,
                departY,
                cibleX: cible.x,
                cibleY: cible.y,
                delai: Math.random() * 280,
                duree: 650 + Math.random() * 450,
                taille: 2 === this.finesse ? 0.55 + Math.random() * 0.85 : 0.9 + Math.random() * 1.5,
                couleur: COULEURS[Math.floor(Math.random() * COULEURS.length)],
                phase: Math.random() * Math.PI * 2,
                vitesseX: 0,
                vitesseY: 0,
                alpha: 1,
            };
        });
    }

    boucle(maintenant) {
        if (null === this.depart) {
            this.depart = maintenant;
        }
        const temps = maintenant - this.depart;
        const ctx = this.ctx;
        const centreX = this.largeur / 2;
        const centreY = this.hauteur / 2;

        ctx.globalCompositeOperation = 'source-over';
        ctx.clearRect(0, 0, this.largeur, this.hauteur);
        ctx.globalCompositeOperation = 'lighter';

        for (const p of this.particules) {
            if (temps < DEBUT_EXPLOSION) {
                const progression = Math.min(Math.max((temps - p.delai) / p.duree, 0), 1);
                const ease = 1 - Math.pow(1 - progression, 3);
                p.x = p.departX + (p.cibleX - p.departX) * ease + Math.sin(temps / 320 + p.phase) * ease * 1.2;
                p.y = p.departY + (p.cibleY - p.departY) * ease + Math.cos(temps / 320 + p.phase) * ease * 1.2;
            } else {
                if (0 === p.vitesseX && 0 === p.vitesseY) {
                    const angle = Math.atan2(p.y - centreY, p.x - centreX) + (Math.random() - 0.5) * 0.9;
                    const force = 3 + Math.random() * 9;
                    p.vitesseX = Math.cos(angle) * force;
                    p.vitesseY = Math.sin(angle) * force;
                }
                p.vitesseX *= 1.045;
                p.vitesseY *= 1.045;
                p.x += p.vitesseX;
                p.y += p.vitesseY;
                p.alpha = Math.max(p.alpha - 0.022, 0);
            }

            if (p.alpha <= 0) continue;
            ctx.globalAlpha = p.alpha;
            ctx.fillStyle = p.couleur;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.taille, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.globalAlpha = 1;

        this.element.dataset.phase = temps < DUREE_CONVERGENCE ? 'convergence' : (temps < DEBUT_EXPLOSION ? 'pause' : 'explosion');

        if (temps >= DEBUT_REVELATION && !this.revele) {
            this.revele = true;
            this.element.classList.add('intro-revele');
        }

        if (temps >= DUREE_TOTALE) {
            this.terminer();

            return;
        }

        this.rafId = requestAnimationFrame((t) => this.boucle(t));
    }

    passer() {
        this.terminer();
    }

    terminer() {
        cancelAnimationFrame(this.rafId);
        clearTimeout(this.garde);
        document.body.classList.remove('intro-active');
        this.element.remove();
    }

    disconnect() {
        cancelAnimationFrame(this.rafId);
        clearTimeout(this.garde);
        document.body.classList.remove('intro-active');
    }
}
