<?php
function legal_pages(array $settings): array {
    $shop  = $settings['shop_name']     ?? 'ABJ Store';
    $email = $settings['contact_email'] ?? 'kontakt@example.com';
    return [
        'impressum' => [
            'title' => 'Impressum',
            'sections' => [
                ['h' => 'Anbieter',                     'body' => "$shop\n[Vor- und Nachname / Firma]\n[Straße und Hausnummer]\n[PLZ und Ort]\n[Land]"],
                ['h' => 'Kontakt',                      'body' => "E-Mail: $email\nTelefon: [Telefonnummer]"],
                ['h' => 'Vertretungsberechtigte Person', 'body' => '[Name der vertretungsberechtigten Person]'],
                ['h' => 'Umsatzsteuer-ID',              'body' => 'USt-IdNr. gemäß § 27a UStG: [falls vorhanden]'],
                ['h' => 'Verantwortlich für den Inhalt', 'body' => '[Name, Anschrift]'],
                ['h' => 'Streitschlichtung',            'body' => 'Die EU-Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: https://ec.europa.eu/consumers/odr. Wir sind nicht verpflichtet, an Streitbeilegungsverfahren teilzunehmen.'],
            ],
        ],
        'datenschutz' => [
            'title' => 'Datenschutzerklärung',
            'sections' => [
                ['h' => 'Verantwortlicher',         'body' => "$shop\n[Anschrift]\nE-Mail: $email"],
                ['h' => 'Welche Daten wir verarbeiten', 'body' => 'Beim Besuch werden technisch notwendige Daten verarbeitet. Bei einer Bestellung verarbeiten wir Name, E-Mail und Lieferadresse zur Vertragsabwicklung.'],
                ['h' => 'Cookies',                  'body' => 'Wir verwenden ausschließlich technisch notwendige Cookies (Session). Es werden keine Analyse- oder Marketing-Cookies gesetzt.'],
                ['h' => 'Newsletter',               'body' => 'Wenn du dich anmeldest, speichern wir deine E-Mail-Adresse. Du kannst dich jederzeit abmelden.'],
                ['h' => 'Deine Rechte',             'body' => "Du hast das Recht auf Auskunft, Berichtigung, Löschung und Widerspruch. Wende dich dazu an $email."],
                ['h' => 'Speicherdauer',            'body' => 'Wir speichern personenbezogene Daten nur so lange wie gesetzlich erforderlich.'],
            ],
        ],
        'agb' => [
            'title' => 'Allgemeine Geschäftsbedingungen',
            'sections' => [
                ['h' => '1. Geltungsbereich', 'body' => "Diese AGB gelten für alle Bestellungen über den Shop $shop."],
                ['h' => '2. Vertragsschluss', 'body' => 'Die Darstellung der Produkte stellt kein bindendes Angebot dar. Mit Absenden der Bestellung gibst du ein verbindliches Angebot ab.'],
                ['h' => '3. Preise und Versand', 'body' => 'Alle Preise verstehen sich inkl. gesetzlicher MwSt. Versandkosten werden vor Abschluss angezeigt.'],
                ['h' => '4. Zahlung',    'body' => 'Die akzeptierten Zahlungsarten werden im Bestellprozess angezeigt.'],
                ['h' => '5. Lieferung', 'body' => 'Die Lieferung erfolgt innerhalb der angegebenen Fristen.'],
                ['h' => '6. Widerrufsrecht', 'body' => 'Verbraucher haben ein gesetzliches Widerrufsrecht (siehe Widerrufsbelehrung).'],
                ['h' => '7. Gewährleistung', 'body' => 'Es gelten die gesetzlichen Gewährleistungsrechte.'],
            ],
        ],
        'widerruf' => [
            'title' => 'Widerrufsbelehrung',
            'sections' => [
                ['h' => 'Widerrufsrecht',      'body' => 'Du hast das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.'],
                ['h' => 'Ausübung des Widerrufs', 'body' => "Um dein Widerrufsrecht auszuüben, informiere uns ($shop, [Anschrift], $email) mittels einer eindeutigen Erklärung."],
                ['h' => 'Folgen des Widerrufs', 'body' => 'Wenn du diesen Vertrag widerrufst, erstatten wir dir alle erhaltenen Zahlungen unverzüglich zurück.'],
            ],
        ],
    ];
}
