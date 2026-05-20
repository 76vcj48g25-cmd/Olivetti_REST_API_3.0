<?php

// Diciamo al browser che la risposta sarà in formato JSON.
header("Content-Type: application/json; charset=UTF-8");

// Header utili per permettere le chiamate fetch dal frontend.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Se arriva una richiesta OPTIONS, la chiudiamo subito.
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}

// Ogni pagina deve mostrare 3 giocatori alla volta.
$elementiPerPagina = 3;

// Categorie disponibili nella select.
$categorie = [
    "Serie A1",
    "Serie A2",
    "Serie A3",
    "Serie B",
    "Serie C",
    "Serie D",
    "Prima Divisione",
    "Seconda Divisione"
];

// Giocatori iniziali salvati direttamente nell'array, senza database.
$giocatori = [
    [
        "id" => 1,
        "nome" => "Simone",
        "cognome" => "Giannelli",
        "eta" => 29,
        "categoria" => "Serie A2"
    ],
    [
        "id" => 2,
        "nome" => "Alessandro",
        "cognome" => "Olivetti",
        "eta" => 20,
        "categoria" => "Serie A1"
    ],
    [
        "id" => 3,
        "nome" => "Andrea",
        "cognome" => "Lolli",
        "eta" => 24,
        "categoria" => "Seconda Divisione"
    ],
    [
        "id" => 4,
        "nome" => "Francesco",
        "cognome" => "Lolli",
        "eta" => 27,
        "categoria" => "Serie A2"
    ],
    [
        "id" => 5,
        "nome" => "Marco",
        "cognome" => "Pistacchio",
        "eta" => 21,
        "categoria" => "Seconda Divisione"
    ],
    [
        "id" => 6,
        "nome" => "Yuki",
        "cognome" => "ishikawa",
        "eta" => 25,
        "categoria" => "Prima Divisione"
    ],
    [
        "id" => 7,
        "nome" => "Alessio",
        "cognome" => "Pistola",
        "eta" => 16,
        "categoria" => "Seconda Divisione"
    ],
    [
        "id" => 8,
        "nome" => "Alex",
        "cognome" => "Nikolov",
        "eta" => 22,
        "categoria" => "Prima Divisione"
    ],
    [
        "id" => 9,
        "nome" => "Mirco",
        "cognome" => "Ricci",
        "eta" => 40,
        "categoria" => "Serie A3"
    ]
];

// Funzione comoda per mandare una risposta JSON e fermare il file.
function rispostaJSON($dati, $codice = 200) {
    http_response_code($codice);
    echo json_encode($dati, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Restituisce solo i giocatori della pagina richiesta.
function paginaGiocatori($listaGiocatori, $pagina, $elementiPerPagina) {
    $totaleGiocatori = count($listaGiocatori);
    $totalePagine = ceil($totaleGiocatori / $elementiPerPagina);

    if ($pagina < 1) {
        rispostaJSON([
            "successo" => false,
            "messaggio" => "Numero di pagina non valido"
        ], 400);
    }

    // Calcola da quale posizione dell'array partire.
    $offset = ($pagina - 1) * $elementiPerPagina;

    // Prende solo 3 giocatori alla volta.
    $giocatoriPagina = array_slice($listaGiocatori, $offset, $elementiPerPagina);

    return [
        "successo" => true,
        "pagina" => $pagina,
        "elementiPerPagina" => $elementiPerPagina,
        "totaleGiocatori" => $totaleGiocatori,
        "totalePagine" => $totalePagine,
        "haAltri" => $pagina < $totalePagine,
        "giocatori" => $giocatoriPagina
    ];
}

// Leggiamo la rotta scritta dopo api.php, ad esempio /giocatori/pagina/1.
$path = $_SERVER["PATH_INFO"] ?? "";

// Fallback utile con UniServer/Apache se PATH_INFO è vuoto.
if ($path === "") {
    $uri = $_SERVER["REQUEST_URI"];
    $scriptName = $_SERVER["SCRIPT_NAME"];

    $path = str_replace($scriptName, "", $uri);
    $path = strtok($path, "?");
}

// Dividiamo la rotta in pezzi.
$segmenti = array_values(array_filter(explode("/", $path)));

$metodo = $_SERVER["REQUEST_METHOD"];
$rotta = $segmenti[0] ?? "";

// GET api.php/categorie
if ($metodo === "GET" && $rotta === "categorie") {
    rispostaJSON([
        "successo" => true,
        "categorie" => $categorie
    ]);
}

// GET api.php/tutti: utile per testare e vedere tutti i giocatori insieme.
if ($metodo === "GET" && $rotta === "tutti") {
    rispostaJSON([
        "successo" => true,
        "giocatori" => $giocatori
    ]);
}

// GET api.php/giocatori/pagina/1, /2, /3
if ($metodo === "GET" && $rotta === "giocatori" && ($segmenti[1] ?? "") === "pagina") {
    $pagina = intval($segmenti[2] ?? 1);
    rispostaJSON(paginaGiocatori($giocatori, $pagina, $elementiPerPagina));
}

// GET api.php/categoria/Serie%20A1
if ($metodo === "GET" && $rotta === "categoria") {
    $categoriaRichiesta = urldecode($segmenti[1] ?? "");

    if ($categoriaRichiesta === "") {
        rispostaJSON([
            "successo" => false,
            "messaggio" => "Categoria non specificata"
        ], 400);
    }

    $filtrati = array_values(array_filter($giocatori, function ($giocatore) use ($categoriaRichiesta) {
        return $giocatore["categoria"] === $categoriaRichiesta;
    }));

    rispostaJSON([
        "successo" => true,
        "categoria" => $categoriaRichiesta,
        "giocatori" => $filtrati
    ]);
}

// POST api.php/nuovo: riceve i dati del form e crea un nuovo giocatore.
if ($metodo === "POST" && $rotta === "nuovo") {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        rispostaJSON([
            "successo" => false,
            "messaggio" => "Dati JSON non validi"
        ], 400);
    }

    $nome = trim($input["nome"] ?? "");
    $cognome = trim($input["cognome"] ?? "");
    $eta = intval($input["eta"] ?? 0);
    $categoria = trim($input["categoria"] ?? "");

    // Controllo semplice dei campi obbligatori.
    if ($nome === "" || $cognome === "" || $eta === 0 || $categoria === "") {
        rispostaJSON([
            "successo" => false,
            "messaggio" => "Tutti i campi sono obbligatori"
        ], 400);
    }

    // L'età deve stare tra 12 e 40.
    if ($eta < 12 || $eta > 40) {
        rispostaJSON([
            "successo" => false,
            "messaggio" => "L'età deve essere compresa tra 12 e 40 anni"
        ], 400);
    }

    // La categoria deve essere una di quelle previste.
    if (!in_array($categoria, $categorie)) {
        rispostaJSON([
            "successo" => false,
            "messaggio" => "Categoria non valida"
        ], 400);
    }

    $nuovoGiocatore = [
        "id" => count($giocatori) + 1,
        "nome" => $nome,
        "cognome" => $cognome,
        "eta" => $eta,
        "categoria" => $categoria
    ];

    rispostaJSON([
        "successo" => true,
        "messaggio" => "Giocatore inserito correttamente",
        "giocatore" => $nuovoGiocatore
    ], 201);
}

// Se nessuna rotta corrisponde, rispondiamo con errore 404.
rispostaJSON([
    "successo" => false,
    "messaggio" => "Rotta non trovata"
], 404);
