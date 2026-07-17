import { Modal } from 'bootstrap';
import L from 'leaflet';

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
let posUtilisateur = null;

function urlProspect(id, action) {
    const carte = document.querySelector('.kanban-card[data-id="' + id + '"]');
    return carte ? carte.dataset.url.replace(/\/statut$/, '/' + action) : null;
}

function iconeTexte(el, classesIcone, texte) {
    el.textContent = '';
    const icone = document.createElement('i');
    icone.className = classesIcone;
    el.appendChild(icone);
    el.appendChild(document.createTextNode(texte));
}

function majCompteurs() {
    document.querySelectorAll('.kanban-col-body').forEach((body) => {
        const compteur = document.querySelector('[data-compteur="' + body.dataset.statut + '"]');
        if (compteur) {
            compteur.textContent = body.querySelectorAll('.kanban-card').length;
        }
    });
}

function afficherErreur(message) {
    const toast = document.createElement('div');
    toast.className = 'toast-erreur alert alert-danger shadow';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

async function requeteJson(url, corps) {
    const reponse = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...corps, _token: csrfToken }),
    });
    const donnees = await reponse.json();
    if (!reponse.ok || !donnees.ok) {
        throw new Error(donnees.error || 'Erreur serveur');
    }
    return donnees;
}

/* --- Mise a jour des elements de carte (colonne kanban) --- */

function majDateContactCarte(carte, dateContact) {
    if (!dateContact) return;
    let span = carte.querySelector('.contact-date');
    if (!span) {
        span = document.createElement('span');
        span.className = 'contact-date';
        carte.querySelector('.meta').appendChild(span);
    }
    iconeTexte(span, 'fa fa-clock me-1', dateContact);
}

function majRelanceCarte(carte, fiche) {
    let badge = carte.querySelector('.badge-relance');
    if (!fiche.dateRelance) {
        if (badge) badge.remove();
        carte.classList.remove('relance-retard');
        return;
    }
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge-relance';
        carte.querySelector('.meta').prepend(badge);
    }
    iconeTexte(badge, 'fa fa-bell me-1', fiche.dateRelance.slice(0, 5));
    badge.classList.toggle('retard', fiche.relanceEnRetard);
    carte.classList.toggle('relance-retard', fiche.relanceEnRetard);
}

function majNotesCarte(carte, notes) {
    let bloc = carte.querySelector('.notes');
    if (!notes) {
        if (bloc) bloc.remove();
        return;
    }
    if (!bloc) {
        bloc = document.createElement('div');
        bloc.className = 'notes';
        carte.appendChild(bloc);
    }
    bloc.textContent = notes.length > 90 ? notes.slice(0, 90) + '…' : notes;
}

async function envoyerStatut(carte, nouveauStatut) {
    const donnees = await requeteJson(carte.dataset.url, { statut: nouveauStatut });

    carte.dataset.statut = nouveauStatut;
    const fiche = fiches[carte.dataset.id];
    if (fiche) {
        fiche.statutValue = donnees.statut;
        fiche.statutLabel = donnees.label;
        fiche.dateContact = donnees.dateContact;
        fiche.relanceEnRetard = donnees.relanceEnRetard;
        majDateContactCarte(carte, donnees.dateContact);
        majRelanceCarte(carte, fiche);
    }
    const cible = document.querySelector('.kanban-col-body[data-statut="' + nouveauStatut + '"]');
    if (cible) {
        cible.appendChild(carte);
    }
    majCompteurs();
    majCouleurMarqueur(carte.dataset.id);
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
    posUtilisateur = { lat, lng };

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
        if (carte.querySelector('.badge-distance')) return;
        const badge = document.createElement('span');
        badge.className = 'badge-distance';
        if (d !== undefined) {
            iconeTexte(badge, 'fa fa-location-arrow me-1', formatDistance(d));
        } else {
            badge.classList.add('non-localise');
            badge.title = 'Adresse non reconnue : modifiez la fiche et choisissez une adresse dans les suggestions';
            iconeTexte(badge, 'fa fa-location-pin-lock me-1', 'non localisé');
        }
        carte.querySelector('.meta').prepend(badge);
    });

    majMarqueurUtilisateur();
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

/* --- Vue carte (Leaflet) --- */

const carteConteneur = document.getElementById('carte-conteneur');
const vueToggle = document.getElementById('vue-toggle');
const board = document.querySelector('.kanban-board');
let carteLeaflet = null;
const marqueursParId = {};
let marqueurUtilisateur = null;

function majCouleurMarqueur(id) {
    const marqueur = marqueursParId[id];
    const fiche = fiches[id];
    if (marqueur && fiche) {
        marqueur.setStyle({ fillColor: couleursStatut[fiche.statutValue] || '#64748b' });
    }
}

function majMarqueurUtilisateur() {
    if (!carteLeaflet || !posUtilisateur) return;
    if (marqueurUtilisateur) {
        marqueurUtilisateur.setLatLng([posUtilisateur.lat, posUtilisateur.lng]);
        return;
    }
    marqueurUtilisateur = L.circleMarker([posUtilisateur.lat, posUtilisateur.lng], {
        radius: 8,
        color: '#fff',
        weight: 3,
        fillColor: '#1d4ed8',
        fillOpacity: 1,
    }).addTo(carteLeaflet).bindTooltip('Vous êtes ici');
}

function initCarte() {
    carteLeaflet = L.map(carteConteneur);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(carteLeaflet);

    const points = [];
    Object.entries(fiches).forEach(([id, fiche]) => {
        if (null === fiche.lat || null === fiche.lng) return;
        const marqueur = L.circleMarker([fiche.lat, fiche.lng], {
            radius: 9,
            color: '#fff',
            weight: 2,
            fillColor: couleursStatut[fiche.statutValue] || '#64748b',
            fillOpacity: 1,
        }).addTo(carteLeaflet);
        marqueur.bindTooltip(fiche.nomBoite);
        marqueur.on('click', () => ouvrirFiche(id));
        marqueursParId[id] = marqueur;
        points.push([fiche.lat, fiche.lng]);
    });

    if (points.length > 0) {
        carteLeaflet.fitBounds(points, { padding: [30, 30], maxZoom: 16 });
    } else if (posUtilisateur) {
        carteLeaflet.setView([posUtilisateur.lat, posUtilisateur.lng], 14);
    } else {
        carteLeaflet.setView([45.75, 4.85], 11);
    }

    majMarqueurUtilisateur();
}

if (vueToggle && carteConteneur && board) {
    vueToggle.addEventListener('click', () => {
        const carteVisible = !carteConteneur.classList.contains('d-none');
        if (carteVisible) {
            carteConteneur.classList.add('d-none');
            board.classList.remove('d-none');
            iconeTexte(vueToggle, 'fa fa-map me-1', 'Carte');
        } else {
            board.classList.add('d-none');
            carteConteneur.classList.remove('d-none');
            iconeTexte(vueToggle, 'fa fa-table-columns me-1', 'Kanban');
            if (!carteLeaflet) {
                initCarte();
            } else {
                carteLeaflet.invalidateSize();
            }
        }
    });
}

/* --- Fiche résumé au clic sur une carte --- */

const ficheModalEl = document.getElementById('fiche-modal');
const ficheModal = ficheModalEl ? new Modal(ficheModalEl) : null;
let ficheIdCourante = null;

function remplirLigne(idLigne, visible) {
    document.getElementById(idLigne).style.display = visible ? '' : 'none';
}

function majEnTeteFiche(fiche) {
    const statut = document.getElementById('fiche-statut');
    statut.textContent = fiche.statutLabel;
    statut.style.background = couleursStatut[fiche.statutValue] || '#64748b';
}

function majNotesFiche(fiche) {
    const notes = document.getElementById('fiche-notes');
    notes.textContent = fiche.notes || 'Aucune note pour le moment';
    notes.classList.toggle('text-muted', !fiche.notes);
}

function majContactFiche(fiche) {
    remplirLigne('ligne-contact', !!fiche.dateContact);
    if (fiche.dateContact) document.getElementById('fiche-date-contact').textContent = fiche.dateContact;
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
                majContactFiche(fiches[id]);
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
    ficheIdCourante = id;

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

    majContactFiche(fiche);

    document.getElementById('fiche-relance').value = fiche.dateRelanceIso || '';

    majNotesFiche(fiche);
    document.getElementById('fiche-note-rapide').value = '';

    rendreBoutonsStatut(id);

    document.getElementById('fiche-modifier').href = fiche.editUrl;

    ficheModal.show();
}

document.addEventListener('click', (e) => {
    const carte = e.target.closest('.kanban-card');
    if (!carte || e.target.closest('a')) return;
    ouvrirFiche(carte.dataset.id);
});

/* --- Date de relance depuis la fiche --- */

const champRelance = document.getElementById('fiche-relance');
if (champRelance) {
    champRelance.addEventListener('change', async () => {
        const id = ficheIdCourante;
        if (!id) return;
        try {
            const donnees = await requeteJson(urlProspect(id, 'relance'), { date: champRelance.value });
            const fiche = fiches[id];
            fiche.dateRelance = donnees.dateRelance;
            fiche.dateRelanceIso = donnees.dateRelanceIso;
            fiche.relanceEnRetard = donnees.relanceEnRetard;
            const carte = document.querySelector('.kanban-card[data-id="' + id + '"]');
            if (carte) majRelanceCarte(carte, fiche);
        } catch (erreur) {
            afficherErreur('Impossible d\'enregistrer la relance : ' + erreur.message);
        }
    });
}

/* --- Note rapide depuis la fiche --- */

const champNote = document.getElementById('fiche-note-rapide');
const boutonNote = document.getElementById('fiche-note-ajouter');

async function ajouterNoteRapide() {
    const id = ficheIdCourante;
    if (!id || !champNote.value.trim()) return;
    try {
        const donnees = await requeteJson(urlProspect(id, 'note'), { note: champNote.value.trim() });
        fiches[id].notes = donnees.notes;
        champNote.value = '';
        majNotesFiche(fiches[id]);
        const carte = document.querySelector('.kanban-card[data-id="' + id + '"]');
        if (carte) majNotesCarte(carte, donnees.notes);
    } catch (erreur) {
        afficherErreur('Impossible d\'ajouter la note : ' + erreur.message);
    }
}

if (boutonNote && champNote) {
    boutonNote.addEventListener('click', ajouterNoteRapide);
    champNote.addEventListener('keydown', (e) => {
        if ('Enter' === e.key) {
            e.preventDefault();
            ajouterNoteRapide();
        }
    });
}
