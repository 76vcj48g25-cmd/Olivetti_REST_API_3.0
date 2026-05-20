// Percorso dell'API PHP. Il file api.php è nella stessa cartella di index.html.
const API_BASE = "api.php";

// Elementi HTML usati dal JavaScript.
const formGiocatore = document.getElementById("formGiocatore");
const listaGiocatori = document.getElementById("listaGiocatori");
const selectCategoria = document.getElementById("categoria");
const filtroCategoria = document.getElementById("filtroCategoria");
const messaggio = document.getElementById("messaggio");
const messaggioPaginazione = document.getElementById("messaggioPaginazione");
const btnMostraAltro = document.getElementById("btnMostraAltro");

// Giocatori caricati dal backend con le chiamate paginate.
let giocatoriDaServerCaricati = [];

// Giocatori inseriti dal form. Restano visibili finché la pagina rimane aperta.
let giocatoriInseritiLocalmente = [];

// Numero della prossima pagina da chiedere al backend.
let paginaCorrente = 1;

// Filtro attuale: "tutti" oppure una categoria specifica.
let categoriaCorrente = "tutti";

// Dice se il backend ha ancora altri giocatori da mandare.
let haAltriDaServer = true;

// Serve per evitare doppi click troppo veloci sul bottone.
let richiestaInCorso = false;

// Quando la pagina è pronta, carichiamo categorie e primi 3 giocatori.
document.addEventListener("DOMContentLoaded", () => {
    caricaCategorie();
    caricaProssimaPagina();
});

// Carica le categorie dal backend e le inserisce nelle due select.
async function caricaCategorie() {
    try {
        const risposta = await fetch(`${API_BASE}/categorie`);
        const dati = await risposta.json();

        if (dati.successo) {
            dati.categorie.forEach(categoria => {
                const optionForm = document.createElement("option");
                optionForm.value = categoria;
                optionForm.textContent = categoria;
                selectCategoria.appendChild(optionForm);

                const optionFiltro = document.createElement("option");
                optionFiltro.value = categoria;
                optionFiltro.textContent = categoria;
                filtroCategoria.appendChild(optionFiltro);
            });
        }
    } catch (errore) {
        mostraMessaggio("Errore nel caricamento delle categorie", "errore");
    }
}

// Carica dal backend altri 3 giocatori alla volta.
async function caricaProssimaPagina() {
    if (!haAltriDaServer || richiestaInCorso) {
        return;
    }

    richiestaInCorso = true;
    btnMostraAltro.disabled = true;
    btnMostraAltro.textContent = "Caricamento...";

    try {
        const risposta = await fetch(`${API_BASE}/giocatori/pagina/${paginaCorrente}`);
        const dati = await risposta.json();

        if (!risposta.ok) {
            mostraMessaggio(dati.messaggio || "Errore nel caricamento", "errore");
            return;
        }

        // Aggiungiamo i nuovi giocatori a quelli già visualizzati.
        giocatoriDaServerCaricati = giocatoriDaServerCaricati.concat(dati.giocatori);

        // Prepariamo il numero della prossima pagina.
        paginaCorrente++;

        // Il backend ci dice se esistono altre pagine.
        haAltriDaServer = dati.haAltri;

        aggiornaVisualizzazione();
        aggiornaStatoPaginazione();
    } catch (errore) {
        mostraMessaggio("Errore nel caricamento dei giocatori", "errore");
    } finally {
        richiestaInCorso = false;
        btnMostraAltro.disabled = false;
        btnMostraAltro.textContent = "Mostra altro";
    }
}

// Restituisce i giocatori inseriti dall'utente che rispettano il filtro scelto.
function giocatoriInseritiCompatibiliConFiltro() {
    if (categoriaCorrente === "tutti") {
        return giocatoriInseritiLocalmente;
    }

    return giocatoriInseritiLocalmente.filter(giocatore => {
        return giocatore.categoria === categoriaCorrente;
    });
}

// Aggiorna la lista mostrata a schermo.
function aggiornaVisualizzazione() {
    let giocatoriDaMostrare = giocatoriDaServerCaricati;

    if (categoriaCorrente !== "tutti") {
        giocatoriDaMostrare = giocatoriDaServerCaricati.filter(giocatore => {
            return giocatore.categoria === categoriaCorrente;
        });
    }

    // Aggiungiamo anche i giocatori inseriti dal form.
    giocatoriDaMostrare = giocatoriDaMostrare.concat(giocatoriInseritiCompatibiliConFiltro());

    mostraGiocatori(giocatoriDaMostrare);
}

// Crea le card HTML dei giocatori.
function mostraGiocatori(giocatori) {
    listaGiocatori.innerHTML = "";

    if (giocatori.length === 0) {
        listaGiocatori.innerHTML = "<p>Nessun giocatore trovato.</p>";
        return;
    }

    giocatori.forEach(giocatore => {
        const card = document.createElement("div");
        card.classList.add("card-giocatore");

        card.innerHTML = `
            <h3>${giocatore.nome} ${giocatore.cognome}</h3>
            <p><strong>Età:</strong> ${giocatore.eta}</p>
            <p><strong>Categoria:</strong> ${giocatore.categoria}</p>
        `;

        listaGiocatori.appendChild(card);
    });
}

// Gestione del form di inserimento.
formGiocatore.addEventListener("submit", async (event) => {
    event.preventDefault();

    const nuovoGiocatore = {
        nome: document.getElementById("nome").value,
        cognome: document.getElementById("cognome").value,
        eta: document.getElementById("eta").value,
        categoria: document.getElementById("categoria").value
    };

    try {
        const risposta = await fetch(`${API_BASE}/nuovo`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(nuovoGiocatore)
        });

        const dati = await risposta.json();

        if (!risposta.ok) {
            mostraMessaggio(dati.messaggio, "errore");
            return;
        }

        // Salviamo il nuovo giocatore anche nel frontend.
        // Così non sparisce quando cambio filtro e poi torno su "Tutti".
        const giocatoreLocale = {
            ...dati.giocatore,
            id: `locale-${Date.now()}`
        };

        giocatoriInseritiLocalmente.push(giocatoreLocale);

        formGiocatore.reset();
        mostraMessaggio(dati.messaggio, "successo");
        aggiornaVisualizzazione();
        aggiornaStatoPaginazione();
    } catch (errore) {
        mostraMessaggio("Errore durante l'inserimento del giocatore", "errore");
    }
});

// Cambia il filtro della lista.
filtroCategoria.addEventListener("change", () => {
    categoriaCorrente = filtroCategoria.value;
    aggiornaVisualizzazione();
    aggiornaStatoPaginazione();
});

// Mostra o nasconde il bottone "Mostra altro".
function aggiornaStatoPaginazione() {
    if (haAltriDaServer) {
        btnMostraAltro.style.display = "inline-block";
        messaggioPaginazione.textContent = "";
    } else {
        btnMostraAltro.style.display = "none";
        messaggioPaginazione.textContent = "Tutti i giocatori sono stati caricati.";
    }
}

// Click sul bottone che carica la pagina successiva.
btnMostraAltro.addEventListener("click", () => {
    caricaProssimaPagina();
});

// Scrive un messaggio sotto al form.
function mostraMessaggio(testo, tipo) {
    messaggio.textContent = testo;
    messaggio.className = tipo;
}
