import { Modal } from 'bootstrap';

const donneesEl = document.getElementById('kanban-donnees');
const csrfToken = donneesEl ? donneesEl.dataset.csrf : '';
const fiches = donneesEl ? JSON.parse(donneesEl.dataset.fiches) : {};
const statuts = donneesEl ? JSON.parse(donneesEl.dataset.statuts) : [];

const couleursStatut = {
    a_contacter: '#64748b',
    contacte: '#2563eb',
    a_relancer: '#f59e0b',
    interesse: '#8b5cf6',
    client: '#16a34a',
    pas_interesse: '#dc2626',
};
const distances = {};
let carteEnCours = null;

function majCompteurs() {
    document.querySelectorAll('.kanban-col-body').forEach((body) => {
        const compteur = document.querySelector('[data-compteur="' + body.dataset.statut + '"]');
        if (compteur) {
            compteur.textContent = body.querySelectorAll('.kanban-card').length;
        }
    });
}

function iconeTexte(el, classesIcone, texte) {
    el.textContent = '';
    const icone = document.createElement('i');
    icone.className = classesIcone;
    el.appendChild(icone);
    el.appendChild(document.createTextNode(texte));
}

function afficherErreur(message) {
    const toast = document.createElement('div');
    toast.className = 'toast-erreur alert alert-danger shadow';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

async function envoyerStatut(carte, nouveauStatut) {
    const reponse = await fetch(carte.dataset.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ statut: nouveauStatut, _token: csrfToken }),
    });
    const donnees = await reponse.json();
    if (!reponse.ok || !donnees.ok) {
        throw new Error(donnees.error || 'Erreur serveur');
    }

    carte.dataset.statut = nouveauStatut;
    const fiche = fiches[carte.dataset.id];
    if (fiche) {
        fiche.statutValue = donnees.statut;
        fiche.statutLabel = donnees.label;
    }
    const cible = document.querySelector('.kanban-col-body[data-statut="' + nouveauStatut + '"]');
    if (cible) {
        cible.appendChild(carte);
    }
    majCompteurs();
}

/* --- Drag & drop (desktop) --- */

document.addEventListener('dragstart', (e) => {
    const carte = e.target.closest('.kanban-card');
    if (!carte) return;
    carteEnCours = carte;
    carte.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
});

document.addEventListener('dragend', () => {
    if (carteEnCours) carteEnCours.classList.remove('dragging');
    carteEnCours = null;
    document.querySelectorAll('.drag-over').forEach((el) => el.classList.remove('drag-over'));
});

document.querySelectorAll('.kanban-col-body').forEach((body) => {
    body.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        body.classList.add('drag-over');
    });

    body.addEventListener('dragleave', (e) => {
        if (!body.contains(e.relatedTarget)) {
            body.classList.remove('drag-over');
        }
    });

    body.addEventListener('drop', async (e) => {
        e.preventDefault();
        body.classList.remove('drag-over');
        if (!carteEnCours) return;

        const carte = carteEnCours;
        const nouveauStatut = body.dataset.statut;
        if (carte.dataset.statut === nouveauStatut) return;

        try {
            await envoyerStatut(carte, nouveauStatut);
        } catch (erreur) {
            afficherErreur('Impossible de déplacer le prospect : ' + erreur.message);
        }
    });
});

/* --- Géolocalisation et tri par proximité --- */

function distanceKm(lat1, lng1, lat2, lng2) {
    const r = Math.PI / 180;
    const a = Math.sin(((lat2 - lat1) * r) / 2) ** 2
        + Math.cos(lat1 * r) * Math.cos(lat2 * r) * Math.sin(((lng2 - lng1) * r) / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function formatDistance(km) {
    return km < 1 ? Math.round(km * 1000) + ' m' : km.toFixed(1).replace('.', ',') + ' km';
}

function appliquerPosition(lat, lng) {
    document.querySelectorAll('.kanban-card').forEach((carte) => {
        if (carte.dataset.lat) {
            distances[carte.dataset.id] = distanceKm(lat, lng, Number(carte.dataset.lat), Number(carte.dataset.lng));
        }
    });

    const body = document.querySelector('.kanban-col-body[data-statut="a_contacter"]');
    if (!body) return;

    const cartes = [...body.querySelectorAll('.kanban-card')];
    cartes.sort((a, b) => (distances[a.dataset.id] ?? Infinity) - (distances[b.dataset.id] ?? Infinity));
    cartes.forEach((carte) => {
        body.appendChild(carte);
        const d = distances[carte.dataset.id];
        if (d !== undefined && !carte.querySelector('.badge-distance')) {
            const badge = document.createElement('span');
            badge.className = 'badge-distance';
            iconeTexte(badge, 'fa fa-location-arrow me-1', formatDistance(d));
            carte.querySelector('.meta').prepend(badge);
        }
    });
}

const geoStatut = document.getElementById('geo-statut');
if (geoStatut && navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            appliquerPosition(pos.coords.latitude, pos.coords.longitude);
            geoStatut.classList.add('actif');
            iconeTexte(geoStatut, 'fa fa-location-crosshairs me-1', 'Tri par proximité actif');
        },
        () => {
            geoStatut.classList.add('erreur');
            iconeTexte(geoStatut, 'fa fa-location-crosshairs me-1', 'Localisation refusée');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
} else if (geoStatut) {
    geoStatut.classList.add('erreur');
    iconeTexte(geoStatut, 'fa fa-location-crosshairs me-1', 'Localisation indisponible');
}

/* --- Fiche résumé au clic sur une carte --- */

const ficheModalEl = document.getElementById('fiche-modal');
const ficheModal = ficheModalEl ? new Modal(ficheModalEl) : null;

function remplirLigne(idLigne, visible) {
    document.getElementById(idLigne).style.display = visible ? '' : 'none';
}

function majEnTeteFiche(fiche) {
    const statut = document.getElementById('fiche-statut');
    statut.textContent = fiche.statutLabel;
    statut.style.background = couleursStatut[fiche.statutValue] || '#64748b';
}

function rendreBoutonsStatut(id) {
    const conteneur = document.getElementById('fiche-statuts');
    conteneur.textContent = '';

    statuts.forEach((s) => {
        const bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.className = 'btn-statut' + (s.value === fiches[id].statutValue ? ' actif' : '');
        if (s.value === fiches[id].statutValue) {
            bouton.style.background = couleursStatut[s.value];
        }
        bouton.textContent = s.label;
        bouton.addEventListener('click', async () => {
            if (s.value === fiches[id].statutValue) return;
            const carte = document.querySelector('.kanban-card[data-id="' + id + '"]');
            if (!carte) return;
            try {
                await envoyerStatut(carte, s.value);
                majEnTeteFiche(fiches[id]);
                rendreBoutonsStatut(id);
            } catch (erreur) {
                afficherErreur('Impossible de changer le statut : ' + erreur.message);
            }
        });
        conteneur.appendChild(bouton);
    });
}

function ouvrirFiche(id) {
    const fiche = fiches[id];
    if (!fiche || !ficheModal) return;

    document.getElementById('fiche-nom').textContent = fiche.nomBoite;
    majEnTeteFiche(fiche);

    const distance = document.getElementById('fiche-distance');
    if (distances[id] !== undefined) {
        distance.textContent = 'à ' + formatDistance(distances[id]);
        distance.classList.remove('d-none');
    } else {
        distance.classList.add('d-none');
    }

    document.getElementById('fiche-adresse').textContent = fiche.adresse;
    document.getElementById('fiche-waze').href = fiche.wazeUrl;

    remplirLigne('ligne-telephone', !!fiche.telephone);
    if (fiche.telephone) {
        const tel = document.getElementById('fiche-telephone');
        tel.textContent = fiche.telephone;
        tel.href = 'tel:' + fiche.telephone;
    }

    remplirLigne('ligne-horaires', !!fiche.horaires);
    if (fiche.horaires) document.getElementById('fiche-horaires').textContent = fiche.horaires;

    const site = document.getElementById('fiche-site');
    if (fiche.siteWebActuel) {
        site.textContent = fiche.siteWebActuel;
        site.href = fiche.siteWebActuel;
        site.classList.remove('text-muted');
    } else {
        site.textContent = 'Pas de site web renseigné';
        site.removeAttribute('href');
        site.classList.add('text-muted');
    }

    remplirLigne('ligne-contact', !!fiche.dateContact);
    if (fiche.dateContact) document.getElementById('fiche-date-contact').textContent = fiche.dateContact;

    const notes = document.getElementById('fiche-notes');
    notes.textContent = fiche.notes || 'Aucune note pour le moment';
    notes.classList.toggle('text-muted', !fiche.notes);

    rendreBoutonsStatut(id);

    document.getElementById('fiche-modifier').href = fiche.editUrl;

    ficheModal.show();
}

document.addEventListener('click', (e) => {
    const carte = e.target.closest('.kanban-card');
    if (!carte || e.target.closest('a')) return;
    ouvrirFiche(carte.dataset.id);
});
