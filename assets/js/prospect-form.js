const champAdresse = document.querySelector('input[name$="[adresse]"]');

if (champAdresse) {
    const style = document.createElement('style');
    style.textContent = `
        .ban-conteneur { position: relative; }
        .ban-suggestions { position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; background: #fff; border: 1px solid #cbd5e1; border-radius: 0 0 8px 8px; box-shadow: 0 8px 16px rgba(15,23,42,0.12); max-height: 260px; overflow-y: auto; }
        .ban-suggestion { padding: 10px 14px; font-size: 14px; color: #1e293b; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
        .ban-suggestion:last-child { border-bottom: none; }
        .ban-suggestion:hover, .ban-suggestion.actif { background: #eff6ff; color: #1d4ed8; }
    `;
    document.head.appendChild(style);

    champAdresse.setAttribute('autocomplete', 'off');
    const parent = champAdresse.parentElement;
    parent.classList.add('ban-conteneur');

    let conteneur = null;
    let indexActif = -1;
    let timer = null;

    function fermer() {
        if (conteneur) {
            conteneur.remove();
            conteneur = null;
        }
        indexActif = -1;
    }

    function choisir(label) {
        champAdresse.value = label;
        fermer();
        champAdresse.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function afficher(labels) {
        fermer();
        if (0 === labels.length) return;

        conteneur = document.createElement('div');
        conteneur.className = 'ban-suggestions';
        labels.forEach((label) => {
            const item = document.createElement('div');
            item.className = 'ban-suggestion';
            item.textContent = label;
            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                choisir(label);
            });
            conteneur.appendChild(item);
        });
        parent.appendChild(conteneur);
    }

    async function chercher(recherche) {
        if (recherche.trim().length < 3) {
            fermer();
            return;
        }
        try {
            const reponse = await fetch(
                'https://api-adresse.data.gouv.fr/search/?q=' + encodeURIComponent(recherche) + '&limit=5'
            );
            const donnees = await reponse.json();
            afficher((donnees.features || []).map((f) => f.properties.label));
        } catch {
            fermer();
        }
    }

    champAdresse.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => chercher(champAdresse.value), 300);
    });

    champAdresse.addEventListener('keydown', (e) => {
        if (!conteneur) return;
        const items = [...conteneur.querySelectorAll('.ban-suggestion')];

        if ('ArrowDown' === e.key || 'ArrowUp' === e.key) {
            e.preventDefault();
            indexActif = 'ArrowDown' === e.key
                ? Math.min(indexActif + 1, items.length - 1)
                : Math.max(indexActif - 1, 0);
            items.forEach((item, i) => item.classList.toggle('actif', i === indexActif));
        } else if ('Enter' === e.key && indexActif >= 0) {
            e.preventDefault();
            choisir(items[indexActif].textContent);
        } else if ('Escape' === e.key) {
            fermer();
        }
    });

    champAdresse.addEventListener('blur', () => {
        setTimeout(fermer, 150);
    });
}
