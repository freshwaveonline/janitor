<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Foutteksten
|--------------------------------------------------------------------------
|
| Per statuscode:
|   title        — de kop
|   message      — één zin: wat er gebeurd is, in de taal van de bezoeker
|   reason       — waarom het gebeurd is
|   explanation  — extra context, alleen waar dat echt helpt
|   suggestions  — concrete vervolgstappen voor de bezoeker
|
| De lookup valt terug van `404` naar `4xx` naar `default`, dus je hoeft
| alleen te definiëren wat afwijkt. Placeholders: :status, :brand,
| :message_number, :support_email.
|
*/

return [

    'default' => [
        'title' => 'Er ging iets mis',
        'message' => 'We konden dit verzoek niet afronden.',
        'reason' => 'De server gaf status :status terug.',
        'suggestions' => [
            'Probeer het over een moment opnieuw.',
            'Blijft het gebeuren? Laat het ons weten en noem het meldingsnummer hieronder.',
        ],
    ],

    '4xx' => [
        'title' => 'Dit verzoek kon niet worden uitgevoerd',
        'message' => 'De pagina of actie die je probeerde is niet beschikbaar.',
        'reason' => 'De server kon het verzoek niet verwerken (status :status).',
        'suggestions' => [
            'Controleer het adres op typefouten.',
            'Ga terug en probeer het opnieuw vanaf de vorige pagina.',
        ],
    ],

    '5xx' => [
        'title' => 'Er ging iets mis aan onze kant',
        'message' => 'Dit ligt niet aan jou — er ging iets mis tijdens het verwerken van je verzoek.',
        'reason' => 'De server liep tegen een onverwacht probleem aan (status :status).',
        'explanation' => 'De fout is vastgelegd. Noem het meldingsnummer hieronder, dan zoeken we precies op wat er gebeurd is.',
        'suggestions' => [
            'Probeer het over een paar minuten opnieuw.',
            'Blijft het gebeuren? Neem contact op met het meldingsnummer hieronder.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 4xx — het verzoek kon niet worden ingewilligd
    |--------------------------------------------------------------------------
    */

    '400' => [
        'title' => 'Ongeldig verzoek',
        'message' => 'We konden dit verzoek niet lezen.',
        'reason' => 'Er zat iets onvolledigs of onjuists in het verzoek, waardoor de server het niet kon verwerken.',
        'explanation' => 'Dit gebeurt meestal na een afgebroken upload, een link die halverwege is afgekapt, of een browserextensie die de pagina aanpast.',
        'suggestions' => [
            'Ververs de pagina en probeer het opnieuw.',
            'Kwam je via een link? Controleer of het volledige adres is meegekomen.',
            'Schakel browserextensies voor deze site uit en probeer opnieuw.',
        ],
    ],

    '401' => [
        'title' => 'Je moet inloggen',
        'message' => 'Deze pagina is alleen beschikbaar als je bent ingelogd.',
        'reason' => 'We konden niet vaststellen wie je bent — je bent uitgelogd of je sessie is verlopen.',
        'explanation' => 'Sessies verlopen na een periode van inactiviteit. Daarom kan dit gebeuren terwijl je eerder wél was ingelogd.',
        'suggestions' => [
            'Log in en open deze pagina opnieuw.',
            'Was je al ingelogd? Log uit en weer in om je sessie te vernieuwen.',
        ],
    ],

    '402' => [
        'title' => 'Betaling vereist',
        'message' => 'Deze functie hoort niet bij je huidige abonnement.',
        'reason' => 'Toegang tot dit onderdeel vereist een actief abonnement of een voldane factuur.',
        'suggestions' => [
            'Controleer je abonnement en facturatiegegevens.',
            'Denk je dat je account wél toegang hoort te hebben? Mail ons op :support_email.',
        ],
    ],

    '403' => [
        'title' => 'Je hebt geen toegang',
        'message' => 'Je account mag deze pagina niet openen.',
        'reason' => 'Je bent ingelogd, maar deze pagina vraagt rechten die jouw account niet heeft.',
        'explanation' => 'Toegang wordt per rol toegekend. Heb je deze pagina nodig voor je werk, dan kan een beheerder je die rechten geven.',
        'suggestions' => [
            'Controleer of je met het juiste account bent ingelogd.',
            'Vraag een beheerder om je toegang te geven.',
            'Ga terug naar een pagina waar je wel bij kunt.',
        ],
    ],

    '404' => [
        'title' => 'We konden deze pagina niet vinden',
        'message' => 'De pagina die je zoekt bestaat niet, of is niet meer beschikbaar.',
        'reason' => 'Het adres is bij ons onbekend. Mogelijk is de pagina verplaatst, hernoemd of verwijderd.',
        'explanation' => 'Bladwijzers en oude links breken zodra pagina\'s verhuizen — dat is vrijwel altijd de oorzaak.',
        'suggestions' => [
            'Controleer het adres op typefouten.',
            'Ga terug naar de vorige pagina.',
            'Begin opnieuw vanaf de homepagina.',
        ],
    ],

    '405' => [
        'title' => 'Deze actie kan hier niet',
        'message' => 'Deze pagina accepteert de actie die je probeerde niet.',
        'reason' => 'Het adres bestaat wel, maar niet voor dit type verzoek.',
        'explanation' => 'Meestal is een formulier naar de verkeerde plek verstuurd, of is een pagina ververst na het versturen.',
        'suggestions' => [
            'Ga terug en open de pagina opnieuw in plaats van te verversen.',
            'Begin de actie helemaal opnieuw.',
        ],
    ],

    '408' => [
        'title' => 'Het verzoek duurde te lang',
        'message' => 'We hebben je verzoek niet op tijd ontvangen.',
        'reason' => 'De verbinding was te traag of werd onderbroken voordat het verzoek klaar was.',
        'suggestions' => [
            'Controleer je internetverbinding.',
            'Probeer het opnieuw — dit is vaak tijdelijk.',
        ],
    ],

    '409' => [
        'title' => 'Deze wijziging botst met een andere',
        'message' => 'Iemand anders heeft dit tegelijk met jou aangepast.',
        'reason' => 'De gegevens zijn gewijzigd tussen het moment dat je deze pagina opende en het moment dat je opsloeg. Jouw wijziging zou die van de ander overschrijven.',
        'suggestions' => [
            'Ververs de pagina om de actuele versie te zien.',
            'Voer je wijziging opnieuw door op de bijgewerkte gegevens.',
        ],
    ],

    '410' => [
        'title' => 'Deze pagina bestaat niet meer',
        'message' => 'Deze pagina heeft bestaan, maar is definitief verwijderd.',
        'reason' => 'Anders dan bij een kapotte link is dit adres bewust opgeheven — er is geen nieuwe locatie voor.',
        'suggestions' => [
            'Verwijder deze pagina uit je bladwijzers.',
            'Begin opnieuw vanaf de homepagina.',
        ],
    ],

    '413' => [
        'title' => 'Dat bestand is te groot',
        'message' => 'Het bestand dat je wilde uploaden is groter dan toegestaan.',
        'reason' => 'De server weigert uploads boven een vaste limiet, zodat de applicatie voor iedereen snel blijft.',
        'suggestions' => [
            'Comprimeer het bestand of splits het op in kleinere delen.',
            'Bij afbeeldingen: probeer een kleinere resolutie of JPEG in plaats van PNG.',
        ],
    ],

    '419' => [
        'title' => 'Je sessie is verlopen',
        'message' => 'Deze pagina stond te lang open, waardoor we je invoer niet konden verifiëren.',
        'reason' => 'Voor je veiligheid zijn formulieren maar beperkte tijd geldig. Die tijd was verstreken op het moment van versturen.',
        'explanation' => 'Dit beschermt je tegen andere sites die namens jou formulieren versturen. Je gegevens zijn niet weg — na verversen staat het formulier er weer.',
        'suggestions' => [
            'Ververs de pagina en vul het formulier opnieuw in.',
            'Kopieer wat je had ingetypt vóórdat je ververst, zodat je het niet kwijtraakt.',
        ],
    ],

    '423' => [
        'title' => 'Dit item is vergrendeld',
        'message' => 'Je kunt dit op dit moment niet wijzigen.',
        'reason' => 'Het item is vergrendeld, door een andere gebruiker die eraan werkt of door een proces dat nog loopt.',
        'suggestions' => [
            'Wacht even en probeer het opnieuw.',
            'Blijft het vergrendeld? Mail ons op :support_email.',
        ],
    ],

    '429' => [
        'title' => 'Te veel verzoeken',
        'message' => 'Je hebt in korte tijd te veel verzoeken gedaan.',
        'reason' => 'Een limiet beschermt de applicatie tegen overbelasting. Je hebt die limiet bereikt; hij wordt binnenkort weer vrijgegeven.',
        'explanation' => 'De limiet reset vanzelf — je hoeft niets te doen behalve wachten.',
        'suggestions' => [
            'Wacht tot het moment hierboven en probeer het dan opnieuw.',
            'Blijf niet verversen; daarmee verleng je de wachttijd.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 5xx — de server faalde
    |--------------------------------------------------------------------------
    */

    '500' => [
        'title' => 'Er ging iets mis aan onze kant',
        'message' => 'Dit ligt niet aan jou — er ging iets mis tijdens het verwerken van je verzoek.',
        'reason' => 'De applicatie liep tegen een onverwachte fout aan en kon het verzoek niet afronden.',
        'explanation' => 'De fout is automatisch vastgelegd. Noem het meldingsnummer hieronder, dan zoeken we precies op wat er gebeurd is.',
        'suggestions' => [
            'Probeer het over een paar minuten opnieuw.',
            'Blijft het gebeuren? Stuur ons het meldingsnummer hieronder.',
            'Wat je had ingevuld is niet verloren, tenzij de pagina anders aangeeft.',
        ],
    ],

    '501' => [
        'title' => 'Dit is er nog niet',
        'message' => 'De applicatie ondersteunt deze actie niet.',
        'reason' => 'Deze functionaliteit is op de server niet geïmplementeerd.',
        'suggestions' => [
            'Verwachtte je dat dit zou werken? Mail ons op :support_email.',
        ],
    ],

    '502' => [
        'title' => 'We konden de server niet bereiken',
        'message' => 'Een onderliggende dienst gaf geen geldig antwoord.',
        'reason' => 'Een dienst waarvan deze applicatie afhankelijk is, gaf een ongeldig antwoord terug.',
        'explanation' => 'Dit is meestal van korte duur en lost zichzelf op zonder dat jij iets hoeft te doen.',
        'suggestions' => [
            'Probeer het over een minuut opnieuw.',
            'Bekijk de statuspagina voor bekende storingen.',
        ],
    ],

    '503' => [
        'title' => 'We zijn tijdelijk niet bereikbaar',
        'message' => 'De applicatie is in onderhoud of heeft het op dit moment erg druk.',
        'reason' => 'De server accepteert op dit moment bewust geen verzoeken.',
        'explanation' => 'Onderhoudsmomenten zijn kort. Staat er hierboven een tijd, dan verwachten we op dat moment weer online te zijn.',
        'suggestions' => [
            'Probeer het opnieuw op het tijdstip hierboven.',
            'Bekijk de statuspagina voor de laatste informatie.',
        ],
    ],

    '504' => [
        'title' => 'De server deed er te lang over',
        'message' => 'Je verzoek is niet binnen de toegestane tijd afgerond.',
        'reason' => 'Een dienst waarvan deze applicatie afhankelijk is, reageerde te traag, waardoor het verzoek is afgebroken.',
        'explanation' => 'Grote rapportages en exports zijn de gebruikelijke oorzaak. Een kleinere selectie lukt meestal wel.',
        'suggestions' => [
            'Probeer het opnieuw met een kleinere periode of selectie.',
            'Probeer het over een paar minuten opnieuw.',
        ],
    ],
];
